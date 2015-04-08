<?php

Yii::app()->moduleManager->register(array(
    'id' => 'updater',
    'class' => 'application.modules.updater.UpdaterModule',
    'import' => array(
        'application.modules.updater.*',
        'application.modules.updater.libs.*',
    ),
    // Events to Catch
    'events' => array(
        array('class' => 'AdminMenuWidget', 'event' => 'onInit', 'callback' => array('UpdaterModule', 'onAdminMenuInit')),
        array('class' => 'CConsoleApplication', 'event' => 'onInit', 'callback' => array('UpdaterModule', 'onConsoleApplicationInit')),
    ),
));
?>