<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2015 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater;

use Yii;
use yii\helpers\Url;

class Events extends \yii\base\Object
{

    public static function onAdminMenuInit($event)
    {
        $event->sender->addItem(array(
            'label' => Yii::t('UpdaterModule.base', 'Update HumHub <sup>BETA</sup>'),
            'url' => Url::to(['/updater/update']),
            'icon' => '<i class="fa fa-cloud-download"></i>',
            'group' => 'manage',
            'sortOrder' => 9000,
            'isActive' => (Yii::$app->controller->module && Yii::$app->controller->module->id == 'updater')
        ));
    }

}
