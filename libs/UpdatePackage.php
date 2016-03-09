<?php

namespace humhub\modules\updater\libs;

use Yii;
use yii\base\Exception;
use yii\helpers\Json;

/**
 * UpdatePackage
 *
 * @author luke
 */
class UpdatePackage
{

    public $versionTo;
    public $versionFrom;
    public $downloadUrl;

    /**
     * @var string the download package file name
     */
    public $fileName;
    public $md5;

    /**
     * @var array list of warnings to ingore
     */
    public $ignoreWarnings = [
        'protected/vendor/',
        'composer.json',
        '.gitignore',
        'm151010_124437_group_permissions.php',
        'm150928_103711_permissions.php',
        'm150928_134934_groups.php'
    ];

    public function __construct($fileName, $versionFrom, $versionTo)
    {
        $this->fileName = $fileName;
        $this->versionFrom = $versionFrom;
        $this->versionTo = $versionTo;
    }

    /**
     * Returns the package directory
     * 
     * @return string
     * @throws Exception
     */
    private function getDirectory()
    {
        $packageDir = Yii::$app->getModule('updater')->getTempPath() . DIRECTORY_SEPARATOR . basename(str_replace('.zip', '', $this->fileName));

        if (!is_dir($packageDir)) {
            throw new Exception("Could not get package directory!" . $packageDir);
        }

        return $packageDir;
    }

    /**
     * Returns the package directory which contains all changed files
     * 
     * @return string
     * @throws Exception
     */
    private function getNewFileDirectory()
    {
        $fileDir = $this->getDirectory() . DIRECTORY_SEPARATOR . 'files';

        if (!is_dir($fileDir)) {
            throw new Exception("Could not get package file directory!");
        }

        return $fileDir;
    }

    /**
     * Downloads the update package
     * 
     * @throws Exception
     */
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
                $response = $http->send();
                file_put_contents($targetFile, $response->getBody());
            } catch (Exception $ex) {
                throw new Exception(Yii::t('UpdaterModule.libs_UpdatePackage', 'Update download failed! (%error%)', array('%error%' => $ex->getMessage())));
            }
        }

        if (md5_file($targetFile) != $this->md5) {
            throw new Exception(Yii::t('UpdaterModule.base', 'Update package invalid!'));
        }
    }

    public function extract()
    {
        $targetFile = Yii::$app->getModule('updater')->getTempPath() . DIRECTORY_SEPARATOR . $this->fileName;

        if (!is_file($targetFile) || md5_file($targetFile) != $this->md5) {
            throw new Exception("Invalid package file!");
        }

        $zip = new \ZipArchive();
        $res = $zip->open($targetFile);
        if ($res === TRUE) {
            $zip->extractTo(Yii::$app->getModule('updater')->getTempPath());
            $zip->close();
        } else {
            throw new Exception(Yii::t('UpdaterModule.base', 'Could not extract update package!'));
        }
    }

    /**
     * Returns the results of package validations
     */
    public function validate()
    {
        $results = array();
        $results['notWritable'] = array();
        $results['modified'] = array();

        $changedFiles = $this->getChangedFiles();
        foreach ($changedFiles as $fileName => $info) {

            if (!$this->showWarningForFile($fileName)) {
                continue;
            }
            $realFilePath = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $fileName;

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

    public function install()
    {
        $warnings = array();

        $changedFiles = $this->getChangedFiles();
        foreach ($changedFiles as $fileName => $info) {
            $realFilePath = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $fileName;
            if ($info['changeType'] == 'D') {
                if (!$this->deleteFile($realFilePath)) {
                    if ($this->showWarningForFile($fileName)) {
                        $warnings[] = "Deletion of " . $realFilePath . " failed!";
                    }
                }
            } elseif ($info['changeType'] == 'A' || $info['changeType'] == 'M') {
                $newFile = $this->getNewFileDirectory() . DIRECTORY_SEPARATOR . $info['newFileMD5'];
                if (!$this->installFile($newFile, $realFilePath)) {
                    if ($this->showWarningForFile($fileName)) {
                        $warnings[] = "Failed to install new version of " . $realFilePath . "!";
                    }
                }
            }
        }

        return $warnings;
    }

    public function getChangedFiles()
    {
        $file = $this->getDirectory() . DIRECTORY_SEPARATOR . "update.json";
        if (!is_file($file)) {
            throw new Exception("Could not open update.json!");
        }
        $update = Json::decode(file_get_contents($file));
        return $update['changedFiles'];
    }

    public function getReleaseNotes()
    {
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
    private function isWritable($f)
    {

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
    private function installFile($source, $target)
    {

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
    private function deleteFile($file)
    {
        return @unlink($file);
    }

    /**
     * Check if a warning should displayed for given file
     * 
     * @param string $fileName
     * @return boolean show warning
     */
    protected function showWarningForFile($fileName)
    {
        // Ignore warnings
        foreach ($this->ignoreWarnings as $ignoreFilePart) {
            if (strpos($fileName, $ignoreFilePart) !== false) {
                return false;
            }
        }

        return true;
    }

}
