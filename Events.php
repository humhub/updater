<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2015 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater;

use humhub\helpers\ControllerHelper;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
    public static function onAdminMenuInit($event)
    {
        /* @var AdminMenu $menu */
        $menu = $event->sender;

        $menu->addEntry(new MenuLink([
            'label' => Yii::t('UpdaterModule.base', 'Update HumHub'),
            'url' => ['/updater/update'],
            'icon' => 'cloud-download',
            'sortOrder' => 90000,
            'isActive' => ControllerHelper::isActivePath('updater'),
        ]));
    }

    public static function onCronRun($event)
    {
        Yii::$app->queue->push(new jobs\CleanupJob());
    }

}
