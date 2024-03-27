<?php

namespace humhub\modules\updater\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\marketplace\components\OnlineModuleManager;
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

        return $this->render('index', [
            'versionTo' => $availableUpdate->versionTo,
            'releaseNotes' => $availableUpdate->releaseNotes,
            'newUpdaterAvailable' => $newUpdaterAvailable,
            'allowStart' => $allowStart,
            'errorMinimumPhpVersion' => $errorMinimumPhpVersion,
            'errorRootFolderNotWritable' => $errorRootFolderNotWritable
        ]);
    }

    public function actionStart()
    {
        $availableUpdate = OnlineUpdateAPI::getAvailableUpdate();

        return $this->renderAjax('start', [
            'versionTo' => $availableUpdate->versionTo,
            'fileName' => $availableUpdate->fileName,
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

        $onlineModuleManager = new OnlineModuleManager();
        $modules = $onlineModuleManager->getModuleUpdates();

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

}
