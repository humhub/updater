<?php

namespace humhub\modules\updater\libs;

use Yii;
use yii\helpers\Json;
use yii\base\Exception;
use humhub\modules\user\models\Setting;

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
     * @param type $version
     */
    public static function getAvailableUpdate()
    {

        $info = [];
        if (class_exists('\humhub\modules\admin\libs\HumHubAPI')) {
            $info = \humhub\modules\admin\libs\HumHubAPI::request('getHumHubUpdates', [
                'updaterVersion' => Yii::$app->getModule('updater')->version
            ]);
        } else {
            // older Versions
            try {
                $url = Yii::$app->getModule('admin')->marketplaceApiUrl . "getHumHubUpdates?version=" . Yii::$app->version . "&updaterVersion=" . Yii::$app->getModule('updater')->version . "&installId=" . Setting::Get('installationId', 'admin');
                $http = new \Zend\Http\Client($url, array(
                    'adapter' => '\Zend\Http\Client\Adapter\Curl',
                    'curloptions' => Yii::$app->getModule('updater')->getCurlOptions(),
                    'timeout' => 30
                ));
                $response = $http->send();
                $info = Json::decode($response->getBody());
            } catch (Exception $ex) {
                throw new Exception(Yii::t('UpdaterModule.base', 'Could not get update info online! (%error%)', array('%error%' => $ex->getMessage())));
            }
        }

        if (!isset($info['fromVersion'])) {
            return null;
        }

        $package = new UpdatePackage($info['fileName'], $info['fromVersion'], $info['toVersion']);
        $package->md5 = $info['md5'];
        $package->downloadUrl = $info['downloadUrl'];

        return $package;
    }

}
