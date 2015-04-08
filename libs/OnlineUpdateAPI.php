<?php

/**
 * OnlineUpdateAPI
 *
 * @author luke
 */
class OnlineUpdateAPI {

    /**
     * Returns all available updates for a given version
     * 
     * @param type $version
     */
    public static function getAvailableUpdate() {

        try {
            $url = Yii::app()->getModule('updater')->getApiUrl()."getHumHubUpdates?version=".urlencode(HVersion::VERSION)."&updaterVersion=".Yii::app()->getModule('updater')->getVersion();

            $http = new Zend_Http_Client($url, array(
                'adapter' => 'Zend_Http_Client_Adapter_Curl',
                'curloptions' => Yii::app()->getModule('updater')->getCurlOptions(),
                'timeout' => 30
            ));
            $response = $http->request();
            $body = $response->getBody();
            
            if ($body == "") {
                return null;
            }
            
            $info = CJSON::decode($body);

            if (!isset($info['fromVersion'])) {
                return null;
            }
            
            $package = new UpdatePackage();
            $package->versionFrom = $info['fromVersion'];
            $package->versionTo = $info['toVersion'];
            $package->fileName = $info['fileName'];
            $package->md5 = $info['md5'];
            $package->downloadUrl = $info['downloadUrl'];

            return $package;
                    
            
        } catch (Exception $ex) {
            throw new CHttpException('500', Yii::t('UpdaterModule.base', 'Could not get update info online! (%error%)', array('%error%' => $ex->getMessage())));
        }

        return null;
    }

}
