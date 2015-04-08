<?php

/**
 * UpdatePackage
 *
 * @author luke
 */
class UpdatePackage {

    public $versionTo;
    public $versionFrom;
    public $downloadUrl;
    public $fileName;
    public $md5;

    private function getDirectory() {
        $packageDir = $this->getUpdaterDirectory() . DIRECTORY_SEPARATOR . $this->versionTo;

        if (!is_dir($packageDir)) {
            throw new CException("Could not get package directory!");
        }

        return $packageDir;
    }

    private function getUpdaterDirectory() {
        $workDir = Yii::app()->getRuntimePath() . DIRECTORY_SEPARATOR . 'updater';
        if (!is_dir($workDir)) {
            if (!@mkdir($workDir)) {
                throw new CException("Could not create updater runtime folder!");
            }
        }
        return $workDir;
    }

    private function getNewFileDirectory() {
        $fileDir = $this->getDirectory() . DIRECTORY_SEPARATOR . 'files';

        if (!is_dir($fileDir)) {
            throw new CException("Could not get package file directory!");
        }

        return $fileDir;
    }

    public function download() {
        $targetFile = $this->getUpdaterDirectory() . DIRECTORY_SEPARATOR . $this->fileName;

        if (is_file($targetFile) && md5_file($targetFile) != $this->md5) {
            unlink($targetFile);
        }

        try {
            $http = new Zend_Http_Client($this->downloadUrl, array(
                'adapter' => 'Zend_Http_Client_Adapter_Curl',
                'curloptions' => Yii::app()->getModule('updater')->getCurlOptions(),
                'timeout' => 300
            ));
            $response = $http->request();
            file_put_contents($targetFile, $response->getBody());
        } catch (Exception $ex) {
            throw new CHttpException('500', Yii::t('UpdaterModule.libs_UpdatePackage', 'Update download failed! (%error%)', array('%error%' => $ex->getMessage())));
        }

        if (md5_file($targetFile) != $this->md5) {
            throw new CHttpException('500', Yii::t('UpdaterModule.base', 'Update package invalid!'));
        }
    }

    public function extract() {
        $targetFile = $this->getUpdaterDirectory() . DIRECTORY_SEPARATOR . $this->fileName;

        if (!is_file($targetFile) || md5_file($targetFile) != $this->md5) {
            unlink($targetFile);
        }

        $zip = new ZipArchive;
        $res = $zip->open($targetFile);
        if ($res === TRUE) {
            $zip->extractTo($this->getUpdaterDirectory());
            $zip->close();
        } else {
            throw new CHttpException('500', Yii::t('UpdaterModule.base', 'Could not extract update package!'));
        }
    }

    /**
     * Returns the results of package validations
     */
    public function validate() {
        $results = array();
        $results['notWritable'] = array();
        $results['modified'] = array();

        $changedFiles = $this->getChangedFiles();
        foreach ($changedFiles as $fileName => $info) {
            $realFilePath = Yii::getPathOfAlias('webroot') . DIRECTORY_SEPARATOR . $fileName;

            if ($info['changeType'] == 'D') {
                if (is_file($realFilePath) && !$this->isWritable($realFilePath)) {
                    $results['notWritable'][] = $realFilePath;
                    continue;
                }
                if (is_file($realFilePath) && md5_file($realFilePath) != $info['oldFileMD5']) {
                    $results['modified'][] = $realFilePath;
                }
            } elseif ($info['changeType'] == 'A') {
                if (!$this->isWritable($realFilePath) || !$this->isWritable(dirname($realFilePath))) {
                    $results['notWritable'][] = $realFilePath;
                }
                if (is_file($realFilePath) && md5_file($realFilePath) != $info['newFileMD5']) {
                    $results['modified'][] = $realFilePath;
                }
            } else {
                if (!$this->isWritable($realFilePath)) {
                    $results['notWritable'][] = $realFilePath;
                }
                if (is_file($realFilePath) && md5_file($realFilePath) != $info['oldFileMD5']) {
                    $results['modified'][] = $realFilePath;
                }
            }
        }
        return $results;
    }

    public function install() {
        $warnings = array();

        $changedFiles = $this->getChangedFiles();
        foreach ($changedFiles as $fileName => $info) {
            $realFilePath = Yii::getPathOfAlias('webroot') . DIRECTORY_SEPARATOR . $fileName;
            if ($info['changeType'] == 'D') {
                if ($this->deleteFile($realFilePath)) {
                    $warnings[] = "Deletion of " . $realFilePath . " failed!";
                }
            } elseif ($info['changeType'] == 'A' || $info['changeType'] == 'M') {
                $newFile = $this->getNewFileDirectory() . DIRECTORY_SEPARATOR . $info['newFileMD5'];
                if (!$this->installFile($newFile, $realFilePath)) {
                    $warnings[] = "Failed to install new version of " . $realFilePath . "!";
                }
            }
        }

        return $warnings;
    }

    public function getChangedFiles() {
        $file = $this->getDirectory() . DIRECTORY_SEPARATOR . "update.json";
        if (!is_file($file)) {
            throw new CException("Could not open update.json!");
        }
        $update = CJSON::decode(file_get_contents($file));
        return $update['changedFiles'];
    }

    public function getReleaseNotes() {
        $releaseNotesFile = $this->getDirectory() . DIRECTORY_SEPARATOR . 'changes.md';
        if (is_file($releaseNotesFile)) {
            return file_get_contents($releaseNotesFile);
        }
        return "";
    }

    /**
     * Checks if given file is writeable, can be created or deleted
     * If the parent directory doesn't exists yet - check it can be created
     * 
     * @param String $f 
     */
    private function isWritable($f) {

        if (is_dir($f) || is_file($f)) {
            return is_writable($f);
        }
        do {
            $f = dirname($f);

            // If directory exists, be we have no access
            if (is_dir($f)) {
                if (is_writable($f)) {
                    return true;
                } else {
                    return false;
                }
            }
        } while ($f != "/" && $f != "." && $f != "" && $f != "\\");

        return false;
    }

    /**
     * Installs a file
     * 
     * @param String $file
     */
    private function installFile($source, $target) {

        $directory = dirname($target);
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0777, true)) {
                Yii::log("InstallFile - Could not create folder: " . $directory, CLogger::LEVEL_ERROR);
                return false;
            }
        }

        if (!is_file($source)) {
            Yii::log("InstallFile - Could not find source: " . $source, CLogger::LEVEL_ERROR);
            return false;
        }

        if (@copy($source, $target) == false) {
            Yii::log("InstallFile - Could not copy to: " . $target, CLogger::LEVEL_ERROR);
            return false;
        }
        return true;
    }

    /**
     * Delete a file 
     * 
     * @todo Also delete parent directories - when empty
     * @param type $file
     */
    private function deleteFile($file) {
        return @unlink($realFilePath);
    }

}
