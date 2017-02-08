<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\libs;

use Yii;

/**
 * AvailableUpdate
 *
 * @author Luke
 */
class AvailableUpdate
{

    public $releaseNotes;
    public $versionFrom;
    public $fileName;
    public $versionTo;
    public $downloadUrl;
    public $md5;

    public function download()
    {
        $targetFile = Yii::$app->getModule('updater')->getTempPath() . DIRECTORY_SEPARATOR . $this->fileName;

        // Unlink download if exists and not matches the md5
        if (is_file($targetFile) && md5_file($targetFile) != $this->md5) {
            unlink($targetFile);
        }

        // Download Package
        if (!is_file($targetFile)) {
            try {
                $http = new \Zend\Http\Client($this->downloadUrl, array(
                    'adapter' => '\Zend\Http\Client\Adapter\Curl',
                    'curloptions' => Yii::$app->getModule('updater')->getCurlOptions(),
                    'timeout' => 300
                ));
                $http->setStream();
                $response = $http->send();
                copy($response->getStreamName(), $targetFile);
            } catch (Exception $ex) {
                throw new Exception(Yii::t('UpdaterModule.libs_UpdatePackage', 'Update download failed! (%error%)', array('%error%' => $ex->getMessage())));
            }
        }

        if (md5_file($targetFile) != $this->md5) {
            throw new \Exception(Yii::t('UpdaterModule.base', 'Update package invalid!'));
        }
    }

}
