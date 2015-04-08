<?php

/**
 * UpdateController
 *
 * @author luke
 */
class UpdateController extends Controller {

    public $subLayout = "application.modules_core.admin.views._layout";

    /**
     * @return array action filters
     */
    public function filters() {
        return array(
            'accessControl', // perform access control for CRUD operations
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules() {
        return array(
            array('allow',
                'expression' => 'Yii::app()->user->isAdmin()'
            ),
            array('deny', // deny all users
                'users' => array('*'),
            ),
        );
    }

    public function actionIndex() {

        $updatePackage = OnlineUpdateAPI::getAvailableUpdate();
        if ($updatePackage === null) {
            return $this->render('no_update_available');
        }

        $showGitWarning = true;
        $this->render('index', array(
            'updatePackage' => $updatePackage,
            'showGitWarning' => $showGitWarning,
        ));
    }

    public function actionStart() {
        $this->forcePostRequest();

        $updatePackage = OnlineUpdateAPI::getAvailableUpdate();
        if ($updatePackage === null) {
            return $this->redirect($this->createUrl('index'));
        }

        $updatePackage->download();
        $updatePackage->extract();
        $validationResults = $updatePackage->validate();

        $this->render('start', array('updatePackage' => $updatePackage, 'validationResults' => $validationResults));
    }

    public function actionRun() {
        $this->forcePostRequest();

        $updatePackage = OnlineUpdateAPI::getAvailableUpdate();
        if ($updatePackage === null) {
            return $this->redirect($this->createUrl('index'));
        }

        $warnings = $updatePackage->install();

        $migration = "";
        Yii::import('application.commands.shell.HUpdateCommand');
        if (class_exists('HUpdateCommand') && method_exists('HUpdateCommand', 'AutoUpdate')) {
            $migration = HUpdateCommand::AutoUpdate();
        } else {
            // Old way
            Yii::import('application.commands.shell.ZMigrateCommand');
            $migration = ZMigrateCommand::AutoMigrate();
        }

        Yii::app()->cache->flush();
        ModuleManager::flushCache();
        
        $this->render('run', array('updatePackage' => $updatePackage, 'warnings' => $warnings, 'migration' => $migration));
    }

}
