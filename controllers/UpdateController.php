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
        $migration = \humhub\commands\MigrateController::webMigrateAll();

        // Flush caches
        Yii::$app->moduleManager->flushCache();
        Yii::$app->cache->flush();

        return $this->render('run', array('updatePackage' => $updatePackage, 'warnings' => $warnings, 'migration' => $migration));
    }

}
