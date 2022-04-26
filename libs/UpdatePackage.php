<?php

namespace humhub\modules\updater\libs;

use humhub\widgets\Link;
use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\helpers\FileHelper;

/**
 * UpdatePackage
 *
 * @author luke
 */
class UpdatePackage
{

    /**
     * @var string the download package file name
     */
    public $fileName;

    /**
     * Constructor
     * 
     * @param string $fileName
     * @throws Exception
     */
    public function __construct($fileName)
    {
        $file = $this->getTempPath() . DIRECTORY_SEPARATOR . $fileName;
        $fileParts = pathinfo($file);

        if ($fileParts['dirname'] != $this->getTempPath()) {
            throw new Exception('Invalid update package temp path!');
        }

        if ($fileParts['extension'] != 'zip') {
            throw new Exception('Invalid update package zip extension!');
        }

        if ($fileParts['basename'] != $fileName) {
            throw new Exception('Invalid update package filename!');
        }

        if (!is_file($this->getTempPath() . DIRECTORY_SEPARATOR . $fileName)) {
            throw new Exception('Invalid update package!');
        }

        $this->fileName = $fileName;
    }

    /**
     * Get config for restrictions depending on installed modules
     *
     * @return array Key - Module Id, Value - array of restrictions where key is type of possible restriction:
     *         1. 'HumHubVersion'
     *            'condition' - Sign to compare new installing HumHub version with allowed version for the modules, example: '>='
     *            'version' - Allowed version, example: '1.10'
     *            'message' - Error message in case of the restriction is applied
     */
    private function getModuleRestrictions(): array
    {
        return [
            'enterprise' => [
                'HumHubVersion' => [
                    'condition' => '>=',
                    'version' => '1.10',
                    'message' => Yii::t('UpdaterModule.base', 'This HumHub version no longer supports the deprecated Enterprise Module. Please contact our support: {email}', [
                        'email' => Link::to('hello@humhub.com', 'mailto:hello@humhub.com'),
                    ])
                ]
            ]
        ];
    }

    /**
     * Returns the package directory
     * 
     * @return string
     * @throws Exception
     */
    private function getDirectory()
    {

        $dirName = basename(str_replace('.zip', '', $this->fileName));
        $packageDir = $this->getTempPath() . DIRECTORY_SEPARATOR . $dirName;

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

    public function extract()
    {
        $targetFile = $this->getTempPath() . DIRECTORY_SEPARATOR . $this->fileName;

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
    public function checkFilePermissions()
    {
        $notWritable = array();
        $changedFiles = $this->getChangedFiles();
        foreach ($changedFiles as $fileName => $info) {
            $realFilePath = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $fileName;
            if ($info['changeType'] == 'D') {
                if (is_file($realFilePath) && !$this->isWritable($realFilePath)) {
                    $notWritable[] = $realFilePath;
                    continue;
                }
            } elseif ($info['changeType'] == 'A') {
                if (!$this->isWritable($realFilePath) || !$this->isWritable(dirname($realFilePath))) {
                    $notWritable[] = $realFilePath;
                }
            } else {
                if (!$this->isWritable($realFilePath)) {
                    $notWritable[] = $realFilePath;
                }
            }
        }

        $vendorPath = Yii::getAlias('@webroot/protected/vendor');
        if (!$this->isWritable($vendorPath)) {
            $notWritable[] = $vendorPath;
        }

        $webrootStaticPath = Yii::getAlias('@webroot/static');
        if (!$this->isWritable($webrootStaticPath)) {
            $notWritable[] = $webrootStaticPath;
        }

        // Test Backup Path
        $this->getBackupPath();


        return $notWritable;
    }

    /**
     * Check if new HumHub version supports the current PHP version
     *
     * @return bool|string True - on supporting, String as new minimum supported PHP version
     */
    public function checkPhpVersion()
    {
        $newMinPhpVersion = $this->getNewConfigValue('minSupportedPhpVersion');
        if ($newMinPhpVersion === null || version_compare(PHP_VERSION, $newMinPhpVersion, '>=')) {
            return true;
        }

        return $newMinPhpVersion;
    }

    /**
     * Check for restricted modules
     *
     * @return true|array TRUE - if no restriction, Array - error messages
     */
    public function checkRestrictedModules()
    {
        $errors = [];
        foreach ($this->getModuleRestrictions() as $moduleId => $restrictions) {
            if (!Yii::$app->getModule($moduleId)) {
                continue;
            }
            foreach ($restrictions as $type => $restriction) {
                switch ($type) {
                    case 'HumHubVersion':
                        if (version_compare($this->getNewConfigValue('version'), $restriction['version'], $restriction['condition'])) {
                            $errors[] = $restriction['message'];
                        }
                        break;
                }
            }
        }

        return empty($errors) ? true : $errors;
    }

    private function getNewConfigValue($configVarName, $defaultValue = null)
    {
        $changedFiles = $this->getChangedFiles();

        $configFileName = 'protected/humhub/config/common.php';
        if (isset($changedFiles[$configFileName]['newFileMD5'])) {
            $newConfigFilePath = $this->getNewFileDirectory() . DIRECTORY_SEPARATOR . $changedFiles[$configFileName]['newFileMD5'];
            if (file_exists($newConfigFilePath)) {
                if (preg_match('/[\'"]' . preg_quote($configVarName) . '[\'"]\s+=>\s+[\'"](.+?)[\'"]/', file_get_contents($newConfigFilePath), $match)) {
                    return $match[1];
                }
            }
        }

        return $defaultValue;
    }

    public function install()
    {
        $warnings = array();

        // Complete vendor package provided
        if (is_dir($this->getNewFileDirectory() . DIRECTORY_SEPARATOR . 'vendor')) {
            rename(Yii::getAlias('@webroot/protected/vendor'), $this->getBackupPath() . DIRECTORY_SEPARATOR . 'vendor_' . time());
            rename($this->getNewFileDirectory() . DIRECTORY_SEPARATOR . 'vendor', Yii::getAlias('@webroot/protected/vendor'));
        }

        // Complete HumHub package provided
        if (is_dir($this->getNewFileDirectory() . DIRECTORY_SEPARATOR . 'humhub')) {
            rename(Yii::getAlias('@webroot/protected/humhub'), $this->getBackupPath() . DIRECTORY_SEPARATOR . 'humhub_' . time());
            rename($this->getNewFileDirectory() . DIRECTORY_SEPARATOR . 'humhub', Yii::getAlias('@webroot/protected/humhub'));
        }

        // Complete static files package provided
        if (is_dir($this->getNewFileDirectory() . DIRECTORY_SEPARATOR . 'static')) {

            // Not exists prior 1.2
            if (is_dir(Yii::getAlias('@webroot/static'))) {
                rename(Yii::getAlias('@webroot/static'), $this->getBackupPath() . DIRECTORY_SEPARATOR . 'static_' . time());
            }
            rename($this->getNewFileDirectory() . DIRECTORY_SEPARATOR . 'static', Yii::getAlias('@webroot/static'));
        }

        $changedFiles = $this->getChangedFiles();
        foreach ($changedFiles as $fileName => $info) {
            $realFilePath = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $fileName;

            if ($info['changeType'] == 'A' || $info['changeType'] == 'M') {
                $newFile = $this->getNewFileDirectory() . DIRECTORY_SEPARATOR . $info['newFileMD5'];
                if (!$this->installFile($newFile, $realFilePath)) {
                    Yii::warning('Update of file:' . $realFilePath . ' failed!', 'updater');
                }
            } elseif ($info['changeType'] == 'D') {
                if (!$this->deleteFile($realFilePath)) {
                    Yii::warning('Deletion of file:' . $realFilePath . ' failed!', 'updater');
                }
            }
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
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
                Yii::error("InstallFile - Could not create folder: " . $directory);
                return false;
            }
        }

        if (!is_file($source)) {
            Yii::error("InstallFile - Could not find source: " . $source);
            return false;
        }

        if (@copy($source, $target) == false) {
            Yii::error("InstallFile - Could not copy to: " . $target);
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

    protected function getTempPath()
    {
        return Yii::$app->getModule('updater')->getTempPath();
    }

    /**
     * Delete update package and temp files
     */
    public function delete()
    {

        $packageFile = $this->getTempPath() . DIRECTORY_SEPARATOR . $this->fileName;

        if (!is_file($packageFile)) {
            throw new Exception("Package file not found!");
        }

        if (!is_dir($this->getDirectory())) {
            throw new Exception("Package directory not found!");
        }

        $this->deleteFile($packageFile);

        try {
            FileHelper::removeDirectory($this->getDirectory(), ['traverseSymlinks' => true]);
        } catch (\Exception $ex) {
            Yii::error('Could not remove directory: ' . $this->getDirectory(), 'updater');
        }
    }

    protected function getBackupPath()
    {
        if (!is_dir(Yii::getAlias('@runtime/updater'))) {
            mkdir(Yii::getAlias('@runtime/updater'));
        }

        $path = Yii::getAlias('@runtime/updater/backups');
        if (!is_dir($path)) {
            mkdir($path);
        }

        if (!$this->isWritable($path)) {
            Yii::error('Backup directory not writable: ' . $path);
        }

        return $path;
    }

}
