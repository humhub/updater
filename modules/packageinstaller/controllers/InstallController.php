<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\modules\packageinstaller\controllers;

use Yii;
use humhub\modules\updater\libs\UpdatePackage;

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
            throw new \Exception(Yii::t('UpdaterModule.base', 'Make sure all files are writable!'));
        }
        return ['status' => 'ok'];
    }

    public function actionPrepare()
    {
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
        $migration = \humhub\commands\MigrateController::webMigrateAll();
        $this->flushCaches();
        return ['status' => 'ok'];
    }

    public function actionCleanup()
    {
        $this->updatePackage->delete();

        if (Yii::$app->request->post('theme') == 'true') {
            if (version_compare(Yii::$app->version, '1.1', '<')) {
                \humhub\models\Setting::Set('theme', 'HumHub');
            } else {
                Yii::$app->settings->set('theme', 'HumHub');
            }
            \humhub\libs\DynamicConfig::rewrite();
        }

        return ['status' => 'ok'];
    }

    protected function flushCaches()
    {
        Yii::$app->moduleManager->flushCache();
        Yii::$app->cache->flush();
    }

}
