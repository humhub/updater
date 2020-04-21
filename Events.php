<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2015 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater;

use Yii;
use yii\helpers\Url;

class Events
{

    public static function onAdminMenuInit($event)
    {
        $event->sender->addItem(array(
            'label' => Yii::t('UpdaterModule.base', 'Update HumHub'),
            'url' => Url::to(['/updater/update']),
            'icon' => '<i class="fa fa-cloud-download"></i>',
            'group' => 'manage',
            'sortOrder' => 90000,
            'isActive' => (Yii::$app->controller->module && Yii::$app->controller->module->id == 'updater')
        ));
    }

    public static function onCronRun($event)
    {
        Yii::$app->queue->push(new jobs\CleanupJob());
    }

}
