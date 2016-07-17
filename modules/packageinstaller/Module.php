<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\modules\packageinstaller;

use Yii;

/**
 * Description of Module
 *
 * @author Luke
 */
class Module extends \yii\base\Module
{

    public static function onApplicationInit()
    {
        Yii::$app->setModule(
                'package-installer', ['class' => 'humhub\modules\updater\modules\packageinstaller\Module']
        );
    }

}
