<?php

namespace humhub\modules\updater\libs;

use humhub\widgets\bootstrap\Link;
use Yii;
use yii\base\Exception;
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
     * Creates a new secret install token, which is required by all steps of the package installer.
     * The token is stored in a file (not in the database), because the application is replaced and migrated
     * while the package is installed.
     *
     * @return string
     * @throws Exception
     */
    public function createToken(): string
    {
        $token = Yii::$app->security->generateRandomString(40);
        $file = $this->getTokenFile();

        if (file_put_contents($file, $token, LOCK_EX) === false) {
            throw new Exception('Could not create update package token!');
        }
        @chmod($file, 0600);

        return $token;
    }

    /**
     * Checks the given install token against the token created while downloading the package.
     *
     * @param mixed $token
     * @return bool
     */
    public function verifyToken($token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $file = $this->getTokenFile();
        if (!is_file($file)) {
            return false;
        }

        return hash_equals((string) file_get_contents($file), $token);
    }

    /**
     * Checks if a token was created for this package (the token file exists).
     */
    public function hasToken(): bool
    {
        return is_file($this->getTokenFile());
    }

    private function getTokenFile(): string
    {
        return $this->getTempPath() . DIRECTORY_SEPARATOR . $this->fileName . '.token';
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
                    ]),
                ],
            ],
        ];
    }

    /**
     * Returns the extracted package directory (`humhub-<version>`)
     *
     * @return string
     * @throws Exception
     */
    private function getDirectory()
    {
        $packageDir = $this->getTempPath() . DIRECTORY_SEPARATOR . basename($this->fileName, '.zip');

        if (!is_dir($packageDir)) {
            throw new Exception('Could not get package directory! ' . $packageDir);
        }

        return $packageDir;
    }

    public function extract()
    {
        $targetFile = $this->getTempPath() . DIRECTORY_SEPARATOR . $this->fileName;
        $packageDir = $this->getTempPath() . DIRECTORY_SEPARATOR . basename($this->fileName, '.zip');

        if (is_dir($packageDir)) {
            FileHelper::removeDirectory($packageDir);
        }

        $zip = new \ZipArchive();
        if ($zip->open($targetFile) !== true || !$zip->extractTo($this->getTempPath())) {
            throw new Exception(Yii::t('UpdaterModule.base', 'Could not extract update package!'));
        }
        $zip->close();

        if ($this->getNewConfigValue('version') !== $this->getPackageVersion()) {
            throw new Exception(Yii::t('UpdaterModule.base', 'Update package invalid!'));
        }
    }

    /**
     * Returns files and directories which must be writable for the update
     *
     * @return string[]
     */
    public function checkFilePermissions()
    {
        return $this->getInstaller()->getNotWritable();
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

    public function install()
    {
        $this->getInstaller()->install();

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return [];
    }

    protected function getTempPath()
    {
        return Yii::$app->getModule('updater')->getTempPath();
    }

    /**
     * Delete update package and temp files (also used for legacy update packages)
     */
    public function delete()
    {
        $packageFile = $this->getTempPath() . DIRECTORY_SEPARATOR . $this->fileName;
        if (is_file($packageFile)) {
            @unlink($packageFile);
        }
        @unlink($this->getTokenFile());

        $packageDir = $this->getTempPath() . DIRECTORY_SEPARATOR . basename($this->fileName, '.zip');
        if (is_dir($packageDir)) {
            try {
                FileHelper::removeDirectory($packageDir, ['traverseSymlinks' => true]);
            } catch (\Exception) {
                Yii::error('Could not remove directory: ' . $packageDir, 'updater');
            }
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

        if (!is_writable($path)) {
            Yii::error('Backup directory not writable: ' . $path);
        }

        return $path;
    }

    /**
     * Installation directory, which contains `protected/humhub`.
     * Not `@webroot`, which is the `public` directory since HumHub 1.20.
     */
    public static function getBaseDirectory(): string
    {
        return dirname(Yii::getAlias('@humhub'), 2);
    }

    private function getInstaller(): PackageInstaller
    {
        /* @var \humhub\modules\updater\Module $module */
        $module = Yii::$app->getModule('updater');

        return new PackageInstaller(
            self::getBaseDirectory(),
            $this->getDirectory(),
            $this->getBackupPath(),
            PackageRules::fromFile($module->getBasePath() . '/config/package-rules.php'),
            Yii::$app->version,
            $this->getPackageVersion(),
        );
    }

    private function getPackageVersion(): string
    {
        return substr(basename($this->fileName, '.zip'), strlen('humhub-'));
    }

    private function getNewConfigValue($configVarName, $defaultValue = null)
    {
        $configFile = $this->getTempPath() . DIRECTORY_SEPARATOR . basename($this->fileName, '.zip') . '/protected/humhub/config/common.php';
        if (is_file($configFile)
            && preg_match('/[\'"]' . preg_quote((string)$configVarName, '/') . '[\'"]\s+=>\s+[\'"](.+?)[\'"]/', file_get_contents($configFile), $match)) {
            return $match[1];
        }

        return $defaultValue;
    }

}
