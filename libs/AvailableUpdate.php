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
                $fh = fopen($targetFile, 'w');
                $client = new \yii\httpclient\Client([
                    'transport' => 'yii\httpclient\CurlTransport'
                ]);
                $response = $client->createRequest()
                    ->setMethod('GET')
                    ->setUrl($this->downloadUrl)
                    ->setOutputFile($fh)
                    ->setOptions(\humhub\libs\CURLHelper::getOptions())
                    ->send();

                if (!$response->isOk) {
                    Yii::error('Could not download upgrade package: ' . $response->getStatusCode());
                    throw new \Exception('Download Response is not ok!');
                }
            } catch (\Exception $ex) {
                throw new \Exception(Yii::t('UpdaterModule.libs_UpdatePackage', 'Update download failed! (%error%)', array('%error%' => $ex->getMessage())));
            }
        }

        if (md5_file($targetFile) != $this->md5) {
            throw new \Exception(Yii::t('UpdaterModule.base', 'Update package invalid!'));
        }
    }

}
