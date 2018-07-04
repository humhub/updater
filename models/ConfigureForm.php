<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\models;

use Yii;

class ConfigureForm extends \yii\base\Model
{

    public $channel;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['channel'], 'in', 'range' => array_keys(static::getChannels())],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'channel' => Yii::t('UpdaterModule.base', 'Channel'),
        ];
    }


    public static function getChannels()
    {
        return [
            'stable' => Yii::t('UpdaterModule.base', 'Stable versions only'),
            'beta' => Yii::t('UpdaterModule.base', 'Stable and beta versions'),
        ];
    }

    public function loadSettings()
    {
        $this->channel = Yii::$app->getModule('updater')->getUpdateChannel();
        return true;
    }

    public function saveSettings()
    {
        $settings = Yii::$app->getModule('updater')->settings;
        $settings->set('channel', $this->channel);

        return true;
    }

}
