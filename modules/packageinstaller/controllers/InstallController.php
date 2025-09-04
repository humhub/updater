<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\modules\packageinstaller\controllers;

use Exception;
use humhub\helpers\ThemeHelper;
use humhub\models\Setting;
use humhub\modules\marketplace\services\ModuleService;
use humhub\modules\updater\libs\UpdatePackage;
use humhub\services\MigrationService;
use Yii;

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
        $this->flushCaches();

        Yii::$app->response->format = 'json';

        $fileName = Yii::$app->request->post('fileName');
        $this->updatePackage = new UpdatePackage($fileName);

        // Load update package
        return parent::beforeAction($action);
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
        if (version_compare(Yii::$app->version, '1.1', '<')) {
            Setting::Set('theme', 'HumHub');
        } elseif (version_compare(Yii::$app->version, '1.3.7', '<')) {
            Yii::$app->settings->set('theme', 'HumHub');
        } else {
            $theme = ThemeHelper::getThemeByName('HumHub');
            if ($theme !== null) {
                $theme->activate();
            }
        }

        // TODO: remove when humhub minVersion is 1.18 or higher
        if (version_compare(Yii::$app->version, '1.18', '<')) {
            \humhub\libs\DynamicConfig::rewrite();
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
