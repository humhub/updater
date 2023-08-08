<?php

namespace humhub\modules\updater;

use humhub\modules\updater\models\ConfigureForm;
use Yii;
use yii\base\Exception;
use yii\helpers\Url;

class Module extends \humhub\components\Module
{

    /**
     * @inheritdoc
     */
    public function getConfigUrl()
    {
        return Url::to(['/updater/admin']);
    }


    /**
     * Returns the temp path of updater
     *
     * @return string
     */
    public static function getTempPath()
    {
        $path = Yii::getAlias('@runtime/updater');

        if (!is_dir($path)) {
            if (!@mkdir($path)) {
                throw new Exception("Could not create updater runtime folder!");
            }
        }

        if (!is_writable($path)) {
            throw new Exception("Updater directory is not writeable!");
        }

        return $path;
    }

    /**
     * Get current update channel value
     *
     * @return string
     */
    public function getUpdateChannel()
    {
        return $this->settings->get('channel', 'stable');
    }

    /**
     * Get current update channel title
     *
     * @return string
     */
    public function getUpdateChannelTitle()
    {
        $updateChannel = $this->getUpdateChannel();
        $updateChannelTitles = ConfigureForm::getChannels();
        return isset($updateChannelTitles[$updateChannel]) ? $updateChannelTitles[$updateChannel] : $updateChannel;
    }
}
