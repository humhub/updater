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
            $fileList = implode(', ', $notWritable);
            $files = (strlen($fileList) > 255) ? substr($fileList, 0, 255) . '...' : $fileList;

            throw new \Exception(Yii::t('UpdaterModule.base', 'Make sure all files are writable! (' . $files . ')'));
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

        // Remove all compiled files from opcode or apc(u) cache.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        } elseif (function_exists('apc_clear_cache')) {
            @apc_clear_cache();
            @apc_clear_cache('user');
            @apc_clear_cache('opcode');
        } elseif (function_exists('apcu_clear_cache')) {
            @apcu_clear_cache();
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
