<?php

use humhub\modules\admin\widgets\AdminMenu;

return [
    'id' => 'updater',
    'class' => 'humhub\modules\updater\Module',
    'namespace' => 'humhub\modules\updater',
    'events' => [
        ['class' => AdminMenu::className(), 'event' => AdminMenu::EVENT_INIT, 'callback' => ['humhub\modules\updater\Events', 'onAdminMenuInit']],
    ],
];
?>