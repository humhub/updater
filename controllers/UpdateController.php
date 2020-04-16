<?php

namespace humhub\modules\updater\controllers;

use Yii;
use yii\helpers\Url;
use humhub\modules\updater\libs\OnlineUpdateAPI;

/**
 * UpdateController
 *
 * @author luke
 */
class UpdateController extends \humhub\modules\admin\components\Controller
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

        $releaseNotes = \humhub\widgets\MarkdownView::widget(['markdown' => $availableUpdate->releaseNotes]);

        // Fix older release notes
        if (strpos($releaseNotes, '<li>') === false) {
            $releaseNotes = nl2br($availableUpdate->releaseNotes) . '<br />';
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
            'releaseNotes' => $releaseNotes,
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

}
