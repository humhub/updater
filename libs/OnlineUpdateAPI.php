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
     * Returns the next available update for the installed version
     *
     * @return AvailableUpdate|null $version
     */
    public static function getAvailableUpdate()
    {
        /* @var Module $module */
        $module = Yii::$app->getModule('updater');

        $info = HumHubAPI::request('v1/core/releases', [
            'channel' => $module->getUpdateChannel(),
        ]);

        $release = UpdateSelector::select($info['releases'] ?? [], Yii::$app->version);
        if ($release === null || !isset($release['packages']['zip']['url'], $release['packages']['zip']['sha256'])) {
            return null;
        }

        $package = new AvailableUpdate();
        $package->fileName = 'humhub-' . $release['version'] . '.zip';
        $package->versionFrom = Yii::$app->version;
        $package->versionTo = $release['version'];
        $package->downloadUrl = $release['packages']['zip']['url'];
        $package->releaseNotes = $release['releaseNotes'] ?? '';
        $package->sha256 = $release['packages']['zip']['sha256'];
        return $package;
    }

}
