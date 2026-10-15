<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\modules\packageinstaller\controllers;

use Exception;
use humhub\helpers\ThemeHelper;
use humhub\modules\marketplace\services\ModuleService;
use humhub\modules\updater\libs\UpdatePackage;
use humhub\services\MigrationService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;

/**
 * Installs a update package
 *
 * @author Luke
 */
class InstallController extends \yii\base\Controller
{
    /**
     * @var \humhub\modules\updater\libs\UpdatePackage
     */
    public $updatePackage = null;

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        set_time_limit(0);

        Yii::$app->response->format = 'json';

        if (!Yii::$app->request->isPost) {
            throw new MethodNotAllowedHttpException();
        }

        // This controller is also used while the application files are being replaced, so a regular login check
        // is not reliable. The secret token is created for the admin while downloading the package instead.
        $fileName = Yii::$app->request->post('fileName');
        if (!is_string($fileName)) {
            throw new ForbiddenHttpException();
        }

        try {
            $this->updatePackage = new UpdatePackage($fileName);
        } catch (Exception) {
            throw new ForbiddenHttpException();
        }

        if (!$this->updatePackage->verifyToken(Yii::$app->request->post('token'))
            && !$this->isTokenlessStepAllowed($action)) {
            throw new ForbiddenHttpException();
        }

        $this->flushCaches();

        return parent::beforeAction($action);
    }

    /**
     * Fallback for the self-update transition from older Updater versions (HumHub < 1.18).
     *
     * If the Updater module updates itself during the "module" step, the already loaded old UI (which has no
     * knowledge of the token and never created a token file) still sends the remaining "module" and "cleanup"
     * requests without a token. Only these two steps are accepted in that case, and only if no token file exists
     * for the package (otherwise the token stays mandatory for all steps), for a logged-in system administrator
     * with a valid CSRF token.
     *
     * @param \yii\base\Action $action
     * @return bool
     */
    protected function isTokenlessStepAllowed($action): bool
    {
        return in_array($action->id, ['module', 'cleanup'], true)
            && !$this->updatePackage->hasToken()
            && !Yii::$app->user->isGuest
            && Yii::$app->user->isAdmin()
            && Yii::$app->request->validateCsrfToken();
    }

    public function actionExtract()
    {
        $this->updatePackage->extract();
        return ['status' => 'ok'];
    }

    public function actionValidate()
    {
        $notWritable = $this->updatePackage->checkFilePermissions();

        if (count($notWritable)) {
            $fileList = implode(', ', $notWritable);
            $files = (strlen($fileList) > 255) ? substr($fileList, 0, 255) . '...' : $fileList;

            throw new Exception(Yii::t('UpdaterModule.base', 'Make sure all files are writable! ({files})', ['files' => $files]));
        }

        return ['status' => 'ok'];
    }

    public function actionPrepare()
    {
        $result = $this->updatePackage->checkPhpVersion();
        if ($result !== true) {
            return ['message' => Yii::t('UpdaterModule.base', 'Your installed PHP version is too old. The new minimum required PHP version is: {version}', ['version' => $result])];
        }

        // Make sure the Installation State is set to installed
        // This is important when upgrading from v1.17 to v1.18
        Yii::$app->settings->set('humhub\components\InstallationState', 3);

        $result = $this->updatePackage->checkRestrictedModules();
        if ($result !== true) {
            return ['message' => implode('<br>', $result)];
        }

        return ['status' => 'ok'];

    }

    public function actionInstallFiles()
    {
        $this->updatePackage->install();

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return ['status' => 'ok'];
    }

    public function actionMigrate()
    {
        MigrationService::create()->migrateUp();
        $this->flushCaches();

        /* @var \humhub\modules\marketplace\Module $marketplaceModule */
        $marketplaceModule = Yii::$app->getModule('marketplace');

        return [
            'status' => 'ok',
            'modules' => array_values(array_map(fn($updateModule) => [
                'id' => $updateModule->id,
                'name' => $updateModule->getName(),
            ], $marketplaceModule->onlineModuleManager->getAvailableUpdateModules())),
        ];
    }

    public function actionModule()
    {
        try {
            (new ModuleService(Yii::$app->request->post('moduleId')))->update();
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        return ['status' => 'ok'];
    }

    public function actionCleanup()
    {
        $this->updatePackage->delete();
        $this->flushCaches();

        if (Yii::$app->request->post('theme') == 'true') {
            $this->switchToDefaultTheme();
        }

        return ['status' => 'ok'];
    }

    protected function switchToDefaultTheme()
    {
        $theme = ThemeHelper::getThemeByName('HumHub');
        if ($theme !== null) {
            $theme->activate();
        }
    }

    protected function flushCaches()
    {
        Yii::$app->moduleManager->flushCache();
        Yii::$app->cache->flush();

        try {
            Yii::$app->assetManager->clear();
        } catch (Exception $ex) {
            Yii::error('Could not clear assetManager: ' . $ex->getMessage(), 'updater');
        }

    }
}
