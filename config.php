<?php

use humhub\modules\admin\widgets\AdminMenu;
use yii\web\Application;

/** @noinspection MissedFieldInspection */
return [
    'id' => 'updater',
    'class' => 'humhub\modules\updater\Module',
    'namespace' => 'humhub\modules\updater',
    'events' => [
        ['class' => AdminMenu::className(), 'event' => AdminMenu::EVENT_INIT, 'callback' => ['humhub\modules\updater\Events', 'onAdminMenuInit']],
        ['class' => 'yii\web\Application', 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => ['humhub\modules\updater\modules\packageinstaller\Module', 'onApplicationInit']],
        ['class' => '\humhub\commands\CronController', 'event'  => 'daily', 'callback' => ['humhub\modules\updater\Events', 'onCronRun']],
    ],
];
?>