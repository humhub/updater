<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\libs;

use humhub\widgets\bootstrap\Link;
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
    public $sha256;

    public function download()
    {
        $targetFile = Yii::$app->getModule('updater')->getTempPath() . DIRECTORY_SEPARATOR . $this->fileName;

        // Unlink download if exists and not matches the checksum
        if (is_file($targetFile) && !hash_equals(strtolower($this->sha256), hash_file('sha256', $targetFile))) {
            unlink($targetFile);
        }

        // Download Package
        if (!is_file($targetFile)) {
            try {
                $fh = fopen($targetFile, 'w');
                $client = new \yii\httpclient\Client([
                    'transport' => 'yii\httpclient\CurlTransport',
                ]);
                $response = $client->createRequest()
                    ->setMethod('GET')
                    ->setUrl($this->downloadUrl)
                    ->setOutputFile($fh)
                    ->setOptions(Yii::$app->getModule('updater')->getCurlOptions())
                    ->send();

                if (!$response->isOk) {
                    Yii::error('Could not download upgrade package: ' . $response->getStatusCode());
                    throw new \Exception('Download Response is not ok!');
                }
            } catch (\Exception $ex) {
                throw new \Exception(Yii::t('UpdaterModule.base', 'Update download failed! (%error%)', ['%error%' => $ex->getMessage()]));
            }
        }

        if (!hash_equals(strtolower($this->sha256), hash_file('sha256', $targetFile))) {
            throw new \Exception(Yii::t('UpdaterModule.base', 'Update package invalid!'));
        }
    }

    public function getWarningMessage(): ?string
    {
        $warningMessages = [
            '1.18'
                => Yii::t('UpdaterModule.base', 'IMPORTANT NOTES ABOUT UPDATING TO VERSION 1.18:') . '<br><br>'
                . Yii::t('UpdaterModule.base', 'All themes will be disabled, <code>.bs3.old</code> will be added to their names, and the default HumHub theme will be enabled.')
                . (Yii::$app->getModule('theme-builder') ? '<br><br>' . Yii::t('UpdaterModule.base', 'The Theme Builder module will be uninstalled.') : '') . '<br><br>'
                . Yii::t('UpdaterModule.base', 'Please read the {MigrationGuideLink}', ['MigrationGuideLink' => Link::to(Yii::t('UpdaterModule.base', 'Migration Guide'), 'https://docs.humhub.org/docs/theme/migrate')->target('_blank')]),
        ];

        foreach ($warningMessages as $warningVersion => $warningMessage) {
            if (
                version_compare($this->versionFrom, $warningVersion, '<')
                && version_compare($this->versionTo, $warningVersion, '>=')
            ) {
                return $warningMessage;
            }
        }

        return null;
    }

    public function hideSwitchDefaultThemeCheckbox(): bool
    {
        $targetVersionsToHide = [
            '1.18' => true,
        ];

        return
            Yii::$app->view->theme->name === 'HumHub'
            || ($targetVersionsToHide[$this->versionTo] ?? false);
    }
}
