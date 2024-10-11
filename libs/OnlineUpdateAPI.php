<?php

namespace humhub\modules\updater\libs;

use humhub\modules\admin\libs\HumHubAPI;
use humhub\modules\updater\Module;
use Yii;

/**
 * OnlineUpdateAPI
 *
 * @author luke
 */
class OnlineUpdateAPI
{
    /**
     * Returns all available updates for a given version
     *
     * @return AvailableUpdate|null $version
     */
    public static function getAvailableUpdate()
    {
        /* @var Module $module */
        $module = Yii::$app->getModule('updater');

        $info = HumHubAPI::request('v1/modules/getHumHubUpdates', [
            'updaterVersion' => $module->version,
            'channel' => $module->getUpdateChannel(),
        ]);

        if (!isset($info['fromVersion'])) {
            return null;
        }

        $package = new AvailableUpdate();
        $package->fileName = $info['fileName'];
        $package->versionFrom = $info['fromVersion'];
        $package->versionTo = $info['toVersion'];
        $package->downloadUrl = $info['downloadUrl'];
        $package->releaseNotes = $info['releaseNotes'];
        $package->md5 = $info['md5'];
        return $package;
    }

}
