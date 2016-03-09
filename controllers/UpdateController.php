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
        $updatePackage = OnlineUpdateAPI::getAvailableUpdate();
        if ($updatePackage === null) {
            return $this->render('no_update_available');
        }

        $showGitWarning = true;

        return $this->render('index', array(
                    'updatePackage' => $updatePackage,
                    'showGitWarning' => $showGitWarning,
        ));
    }

    public function actionStart()
    {
        $this->forcePostRequest();

        $updatePackage = OnlineUpdateAPI::getAvailableUpdate();
        if ($updatePackage === null) {
            return $this->redirect($this->createUrl('index'));
        }

        $updatePackage->download();
        $updatePackage->extract();
        $validationResults = $updatePackage->validate();

        return $this->render('start', array('updatePackage' => $updatePackage, 'validationResults' => $validationResults));
    }

    public function actionRun()
    {
        $this->forcePostRequest();

        $updatePackage = OnlineUpdateAPI::getAvailableUpdate();
        if ($updatePackage === null) {
            return $this->redirect(Url::to(['index']));
        }

        $warnings = $updatePackage->install();
        Yii::$app->getSession()->setFlash('updater_warnings', $warnings);
        Yii::$app->getSession()->setFlash('new_humhub_version', $updatePackage->versionTo);

        // Flush caches
        Yii::$app->moduleManager->flushCache();
        Yii::$app->cache->flush();
        $this->redirect(['migrate']);
    }

    public function actionMigrate()
    {

        $migration = \humhub\commands\MigrateController::webMigrateAll();

        Yii::$app->getSession()->setFlash('updater_migration', $migration);

        // Flush caches
        Yii::$app->moduleManager->flushCache();
        Yii::$app->cache->flush();
        $this->redirect(['finish']);
    }

    public function actionFinish()
    {
        Yii::$app->moduleManager->flushCache();
        Yii::$app->cache->flush();

        $migration = Yii::$app->session->getFlash('updater_migration', '');
        $warnings = Yii::$app->session->getFlash('updater_warnings', []);
        
        $version = Yii::$app->version;
        if (Yii::$app->getSession()->hasFlash('new_humhub_version')) {
            // Use updated version from flash, to avoid display of "cached" version 
            $version = Yii::$app->getSession()->getFlash('new_humhub_version');
        }
        
        return $this->render('run', array('warnings' => $warnings, 'migration' => $migration, 'version' => $version));
    }

}
