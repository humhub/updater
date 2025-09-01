<?php

use humhub\commands\CronController;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\updater\Events;
use humhub\modules\updater\modules\packageinstaller\Module;
use yii\web\Application;

/** @noinspection MissedFieldInspection */
return [
    'id' => 'updater',
    'class' => 'humhub\modules\updater\Module',
    'namespace' => 'humhub\modules\updater',
    'events' => [
        ['class' => AdminMenu::class, 'event' => AdminMenu::EVENT_INIT, 'callback' => [Events::class, 'onAdminMenuInit']],
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Module::class, 'onApplicationInit']],
        ['class' => CronController::class, 'event'  => CronController::EVENT_ON_DAILY_RUN, 'callback' => [Events::class, 'onCronRun']],
    ],
];
