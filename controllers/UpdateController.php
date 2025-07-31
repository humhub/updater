<?php

namespace humhub\modules\updater\controllers;

use humhub\components\Module;
use humhub\modules\admin\components\Controller;
use humhub\modules\updater\libs\OnlineUpdateAPI;
use Yii;

/**
 * UpdateController
 *
 * @author luke
 */
class UpdateController extends Controller
{
    public function init()
    {
        set_time_limit(0);

        // Fix: Handle admin layout file change in (v1.0.0-beta.3 -> v1.0.0-beta.4)
        if ($this->subLayout == '@humhub/modules/admin/views/_layout') {
            if (!file_exists(Yii::getAlias($this->subLayout) . '.php')) {
                $this->subLayout = '@humhub/modules/admin/views/layouts/main';
            }
        }

        parent::init();
    }

    public function actionIndex()
    {
        $availableUpdate = OnlineUpdateAPI::getAvailableUpdate();
        if ($availableUpdate === null) {
            return $this->render('index_noupdate');
        }

        $allowStart = true;
        $newUpdaterAvailable = $this->isNewUpdaterModuleAvailable();
        if ($newUpdaterAvailable) {
            $allowStart = false;
        }

        $errorMinimumPhpVersion = false;
        if (version_compare(phpversion(), '5.6', '<')) {
            $allowStart = false;
            $errorMinimumPhpVersion = true;
        }

        $errorRootFolderNotWritable = !$this->isRootFolderWritable();
        if ($errorRootFolderNotWritable) {
            $allowStart = false;
        }

        $restrictedMaxVersionModules = $this->getModulesRestrictedByMaxVersion($availableUpdate->versionTo);
        if ($restrictedMaxVersionModules !== []) {
            $allowStart = false;
        }

        return $this->render('index', [
            'versionTo' => $availableUpdate->versionTo,
            'releaseNotes' => $availableUpdate->releaseNotes,
            'newUpdaterAvailable' => $newUpdaterAvailable,
            'allowStart' => $allowStart,
            'errorMinimumPhpVersion' => $errorMinimumPhpVersion,
            'errorRootFolderNotWritable' => $errorRootFolderNotWritable,
            'restrictedMaxVersionModules' => $restrictedMaxVersionModules,
        ]);
    }

    public function actionStart()
    {
        $availableUpdate = OnlineUpdateAPI::getAvailableUpdate();

        return $this->renderAjax('start', [
            'availableUpdate' => $availableUpdate,
        ]);
    }

    public function actionDownload()
    {
        $this->forcePostRequest();
        Yii::$app->response->format = 'json';

        $availableUpdate = OnlineUpdateAPI::getAvailableUpdate();
        $availableUpdate->download();

        return ['status' => 'ok'];
    }

    protected function isNewUpdaterModuleAvailable()
    {
        Yii::$app->cache->flush();
        $modules = null;

        if (class_exists('\humhub\modules\admin\libs\OnlineModuleManager')) {
            $onlineModuleManager = new \humhub\modules\admin\libs\OnlineModuleManager();
            $modules = $onlineModuleManager->getModuleUpdates();
        } elseif (class_exists('\humhub\modules\marketplace\libs\OnlineModuleManager')) {
            $onlineModuleManager = new \humhub\modules\marketplace\libs\OnlineModuleManager();
            $modules = $onlineModuleManager->getModuleUpdates();
        } elseif (class_exists('\humhub\modules\marketplace\components\OnlineModuleManager')) {
            $onlineModuleManager = new \humhub\modules\marketplace\components\OnlineModuleManager();
            $modules = $onlineModuleManager->getModuleUpdates();
        }

        if (isset($modules['updater'])) {
            return true;
        }

        return false;
    }

    protected function isRootFolderWritable()
    {
        $rootFolder = Yii::getAlias('@webroot');

        if (!is_writable($rootFolder)) {
            Yii::warning('Not writable: ' . $rootFolder, 'updater');
            return false;
        }

        $staticFolder = Yii::getAlias('@webroot/static');
        if (is_dir($staticFolder) && !is_writeable($staticFolder)) {
            Yii::warning('Not writable: ' . $staticFolder, 'updater');
            return false;
        }

        $vendorFolder = Yii::getAlias('@vendor');
        if (is_dir($vendorFolder) && !is_writeable($vendorFolder)) {
            Yii::warning('Not writable: ' . $vendorFolder, 'updater');
            return false;
        }

        return true;
    }

    protected function getModulesRestrictedByMaxVersion(string $newCoreVersion): array
    {
        /* @var Module[] $installedModules */
        $installedModules = Yii::$app->moduleManager->getModules();
        if ($installedModules === []) {
            return [];
        }

        /* @var \humhub\modules\marketplace\Module $marketplaceModule */
        $marketplaceModule = Yii::$app->getModule('marketplace');
        $onlineModules = $marketplaceModule->onlineModuleManager->getModules();
        if (!is_array($onlineModules) || $onlineModules === []) {
            return [];
        }

        // Extract major version like `1.18`
        $newCoreVersion = preg_replace('/^[a-z]+(\d+\.\d+).*$/i', '$1', $newCoreVersion);

        $restrictedModules = [];
        foreach ($installedModules as $installedModule) {
            $maxVersion = $onlineModules[$installedModule->id]['latestMaxHumHubVersion'] ?? null;
            if (!empty($maxVersion) && version_compare($newCoreVersion, $maxVersion, '>')) {
                $restrictedModules[$installedModule->name] = $maxVersion;
            }
        }

        return $restrictedModules;
    }

}
