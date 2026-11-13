<?php

namespace humhub\modules\updater\tests\codeception\unit;

use Codeception\Test\Unit;
use FilesystemIterator;
use humhub\modules\updater\libs\PackageInstaller;
use humhub\modules\updater\libs\PackageRules;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class PackageInstallerTest extends Unit
{
    private array $tempDirs = [];

    protected function _after()
    {
        foreach ($this->tempDirs as $dir) {
            $this->removePath($dir);
        }
        $this->tempDirs = [];
    }

    public function testInstallWithinMajorLine()
    {
        [$base, $package, $backup] = $this->setUpInstallation();

        $installer = new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.19.4');
        $this->assertSame([], $installer->getNotWritable());
        $installer->install();

        // replaced directories
        $this->assertStringEqualsFile($base . '/protected/humhub/Module.php', 'new core');
        $this->assertFileDoesNotExist($base . '/protected/humhub/removed.php', 'replaced as a whole');
        $this->assertStringEqualsFile($base . '/protected/vendor/lib.php', 'new vendor');
        $this->assertFileDoesNotExist($base . '/protected/vendor/manual.php');
        $this->assertDirectoryDoesNotExist($package . '/protected/humhub', 'package directory moved');

        // overwritten
        $this->assertStringEqualsFile($base . '/index.php', 'new index');
        $this->assertStringEqualsFile($base . '/uploads/.htaccess', 'new htaccess', 'keep exclusion');
        $this->assertStringEqualsFile($base . '/new/dir/file.php', 'new file', 'new file with directories');
        $this->assertDirectoryExists($base . '/public/assets', 'empty package directory created');

        // kept
        $this->assertStringEqualsFile($base . '/robots.txt', 'custom robots');
        $this->assertStringEqualsFile($base . '/protected/config/common.php', 'user config');
        $this->assertStringEqualsFile($base . '/protected/config/new.php', 'new config file', 'missing kept file created');
        $this->assertStringEqualsFile($base . '/uploads/file/a.jpg', 'image');
        $this->assertStringEqualsFile($base . '/local-only.txt', 'local', 'file not in package');

        $this->assertSame([
            'files_ID/index.php',
            'files_ID/uploads/.htaccess',
            'humhub_ID/Module.php',
            'humhub_ID/removed.php',
            'vendor_ID/lib.php',
            'vendor_ID/manual.php',
        ], $this->getBackupFiles($backup), 'identical and kept files are not backed up');
    }

    public function testDeleteRulesWhenCrossingVersion()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        $this->createFiles($base, [
            'index-test.php' => 'test entry',
            'old/a/b.php' => 'old dir',
            'legacy/one.txt' => '1',
            'legacy/two.txt' => '2',
            'legacy/keep.php' => 'not matched',
        ]);

        (new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.20.0'))->install();

        $this->assertFileDoesNotExist($base . '/index-test.php');
        $this->assertFileDoesNotExist($base . '/old', 'directory removed');
        $this->assertFileDoesNotExist($base . '/legacy/one.txt');
        $this->assertFileDoesNotExist($base . '/legacy/two.txt');
        $this->assertStringEqualsFile($base . '/legacy/keep.php', 'not matched');
        $this->assertStringEqualsFile($base . '/robots.txt', 'custom robots');

        $files = $this->getBackupFiles($backup);
        $this->assertContains('files_ID/index-test.php', $files);
        $this->assertContains('files_ID/old/a/b.php', $files);
        $this->assertContains('files_ID/legacy/one.txt', $files);
    }

    public function testDeleteRemovesEmptyParents()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        $this->createFiles($base, ['legacy/sub/x.txt' => 'x', 'legacy/a.txt' => 'a']);
        $rules = new PackageRules(['1.20.0' => ['delete' => ['legacy/sub/x.txt', 'legacy/*.txt']]]);

        (new PackageInstaller($base, $package, $backup, $rules, '1.19.3', '1.20.0'))->install();

        $this->assertFileDoesNotExist($base . '/legacy', 'empty parents removed');
        $this->assertDirectoryExists($base, 'installation directory not removed');
    }

    public function testNoDeletesWithinMajorLine()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        $this->createFiles($base, ['index-test.php' => 'test entry']);

        (new PackageInstaller($base, $package, $backup, $this->getRules(), '1.20.0', '1.20.1'))->install();

        $this->assertStringEqualsFile($base . '/index-test.php', 'test entry');
    }

    public function testDeletesWithPreReleaseTarget()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        $this->createFiles($base, ['index-test.php' => 'test entry']);

        (new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.20.0-beta.1'))->install();

        $this->assertFileDoesNotExist($base . '/index-test.php');
        $this->assertStringEqualsFile($base . '/robots.txt', 'custom robots', 'keep rules of base version');
    }

    public function testGlobSpecialCharactersInInstallationPath()
    {
        [$base, $package, $backup] = $this->setUpInstallation('in[st]*all?');
        $this->createFiles($base, ['index-test.php' => 'test entry']);
        // would match the unescaped pattern
        $this->createFiles(dirname($base), ['ins/index-test.php' => 'other installation']);

        (new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.20.0'))->install();

        $this->assertFileDoesNotExist($base . '/index-test.php');
        $this->assertStringEqualsFile(dirname($base) . '/ins/index-test.php', 'other installation');
        $this->assertStringEqualsFile($base . '/index.php', 'new index');
    }

    public function testSymlinks()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        $outside = $this->createTempDir();
        file_put_contents($outside . '/index.php', 'outside file');
        unlink($base . '/index.php');
        symlink($outside . '/index.php', $base . '/index.php');
        unlink($base . '/unchanged.php');
        symlink($outside . '/missing.php', $base . '/unchanged.php');

        (new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.19.4'))->install();

        $this->assertFalse(is_link($base . '/index.php'), 'symlinked file replaced by a file');
        $this->assertStringEqualsFile($base . '/index.php', 'new index');
        $this->assertStringEqualsFile($outside . '/index.php', 'outside file', 'symlink target not written');
        $this->assertFalse(is_link($base . '/unchanged.php'), 'dangling symlink replaced');
        $this->assertStringEqualsFile($base . '/unchanged.php', 'same');
        $this->assertContains('files_ID/index.php', $this->getBackupFiles($backup));
    }

    public function testSymlinkedKeptDirectory()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        $outside = $this->createTempDir();
        rename($base . '/uploads', $outside . '/uploads');
        symlink($outside . '/uploads', $base . '/uploads');

        (new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.19.4'))->install();

        $this->assertTrue(is_link($base . '/uploads'), 'symlink kept');
        $this->assertStringEqualsFile($outside . '/uploads/file/a.jpg', 'image');
        $this->assertStringEqualsFile($outside . '/uploads/.htaccess', 'new htaccess');
    }

    public function testDeleteTargetOutsideOfInstallation()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        $outside = $this->createTempDir();
        $this->createFiles($outside, ['one.txt' => 'outside']);
        symlink($outside, $base . '/legacy');

        $installer = new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.20.0');

        try {
            $installer->getNotWritable();
            $this->fail('getNotWritable() must fail');
        } catch (RuntimeException) {
        }

        try {
            $installer->install();
            $this->fail('install() must fail');
        } catch (RuntimeException) {
        }

        $this->assertStringEqualsFile($outside . '/one.txt', 'outside');
    }

    public function testPackageWithoutReplaceDirectory()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        rename($package . '/protected/vendor', dirname($package) . '/vendor-moved');

        try {
            (new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.19.4'))->install();
            $this->fail('install() must fail');
        } catch (RuntimeException) {
        }

        $this->assertStringEqualsFile($base . '/protected/humhub/Module.php', 'old core', 'nothing changed');
        $this->assertStringEqualsFile($base . '/index.php', 'old index');
    }

    public function testRollbackWhenDirectorySwapFails()
    {
        [$base, $package, $backup] = $this->setUpInstallation();

        $installer = $this->createInstaller($base, $package, $backup, fn($from) => $from !== $package . '/protected/vendor');

        try {
            $installer->install();
            $this->fail('install() must fail');
        } catch (RuntimeException) {
        }

        $this->assertStringEqualsFile($base . '/protected/humhub/Module.php', 'old core', 'first swap rolled back');
        $this->assertStringEqualsFile($base . '/protected/vendor/lib.php', 'old vendor', 'failed swap restored');
        $this->assertStringEqualsFile($package . '/protected/humhub/Module.php', 'new core', 'package restored');
        $this->assertStringEqualsFile($package . '/protected/vendor/lib.php', 'new vendor');
        $this->assertStringEqualsFile($base . '/index.php', 'old index', 'files not installed');
        $this->assertSame([], $this->getBackupFiles($backup), 'backups moved back');
    }

    public function testRollbackWhenMovingToBackupFails()
    {
        [$base, $package, $backup] = $this->setUpInstallation();

        $installer = $this->createInstaller($base, $package, $backup, fn($from) => $from !== $base . '/protected/vendor');

        try {
            $installer->install();
            $this->fail('install() must fail');
        } catch (RuntimeException) {
        }

        $this->assertStringEqualsFile($base . '/protected/humhub/Module.php', 'old core');
        $this->assertStringEqualsFile($base . '/protected/vendor/lib.php', 'old vendor');
        $this->assertStringEqualsFile($package . '/protected/humhub/Module.php', 'new core');
    }

    public function testReplaceDirectoryMissingInInstallation()
    {
        [$base, $package, $backup] = $this->setUpInstallation();
        rename($base . '/protected/vendor', dirname($base) . '/vendor-moved');

        (new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.19.4'))->install();

        $this->assertStringEqualsFile($base . '/protected/vendor/lib.php', 'new vendor');
    }

    public function testNotWritablePaths()
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can write everywhere');
        }

        [$base, $package, $backup] = $this->setUpInstallation();
        chmod($base . '/protected/vendor', 0555);
        chmod($base . '/index.php', 0444);
        chmod($base . '/unchanged.php', 0444);
        chmod($base . '/robots.txt', 0444);

        $installer = new PackageInstaller($base, $package, $backup, $this->getRules(), '1.19.3', '1.19.4');
        $notWritable = $installer->getNotWritable();
        sort($notWritable);

        $this->assertSame([$base . '/index.php', $base . '/protected/vendor'], $notWritable, 'identical and kept files ignored');

        chmod($base . '/protected', 0555);
        $this->assertContains($base . '/protected/humhub', $installer->getNotWritable(), 'parent of replaced directory');
        chmod($base . '/protected', 0777);
    }

    public function testReplaceAcrossFilesystems()
    {
        [$base, $package, $backup] = $this->setUpInstallation();

        $this->assertSame([$package], $this->createInstaller($base, $package, $backup, null, $package)->getNotWritable());
        $this->assertSame([$backup], $this->createInstaller($base, $package, $backup, null, $backup)->getNotWritable());

        $rules = new PackageRules(['1.19.0' => ['keep' => []]]);
        $this->assertSame([], $this->createInstaller($base, $package, $backup, null, $package, $rules)->getNotWritable(), 'no check without replace');
    }

    /**
     * Installer with injectable rename and device failures.
     *
     * @param callable|null $renameFilter fn(string $from, string $to): bool, false lets the rename fail
     * @param string|null $foreignDeviceDir directory reported on another filesystem
     */
    private function createInstaller(string $base, string $package, string $backup, ?callable $renameFilter, ?string $foreignDeviceDir = null, ?PackageRules $rules = null): PackageInstaller
    {
        return new class ($base, $package, $backup, $rules ?? $this->getRules(), '1.19.3', '1.19.4', $renameFilter, $foreignDeviceDir) extends PackageInstaller {
            private $renameFilter;

            public function __construct(string $base, string $package, string $backup, PackageRules $rules, string $from, string $to, ?callable $renameFilter, private readonly ?string $foreignDeviceDir)
            {
                parent::__construct($base, $package, $backup, $rules, $from, $to);
                $this->renameFilter = $renameFilter;
            }

            protected function renamePath(string $from, string $to): bool
            {
                if ($this->renameFilter !== null && !($this->renameFilter)($from, $to)) {
                    return false;
                }

                return parent::renamePath($from, $to);
            }

            protected function getDevice(string $path): ?int
            {
                return $path === $this->foreignDeviceDir ? -1 : parent::getDevice($path);
            }
        };
    }

    private function getRules(): PackageRules
    {
        return new PackageRules([
            '1.19.0' => [
                'replace' => ['protected/humhub', 'protected/vendor'],
                'keep' => ['protected/config/*', 'uploads/*', '!uploads/.htaccess', 'robots.txt'],
            ],
            '1.20.0' => [
                'replace' => ['protected/humhub', 'protected/vendor'],
                'keep' => ['protected/config/*', 'uploads/*', '!uploads/.htaccess', 'robots.txt', 'config/*'],
                'delete' => ['index-test.php', 'old/*', 'legacy/*.txt'],
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string} installation, package and backup directory
     */
    private function setUpInstallation(string $baseName = 'base'): array
    {
        $root = $this->createTempDir();
        $base = $root . '/' . $baseName;
        $package = $root . '/package/humhub';
        $backup = $base . '/protected/runtime/updater/backups';

        $this->createFiles($base, [
            'index.php' => 'old index',
            'robots.txt' => 'custom robots',
            'local-only.txt' => 'local',
            'protected/humhub/Module.php' => 'old core',
            'protected/humhub/removed.php' => 'removed in new core',
            'protected/vendor/lib.php' => 'old vendor',
            'protected/vendor/manual.php' => 'added by admin',
            'protected/config/common.php' => 'user config',
            'uploads/file/a.jpg' => 'image',
            'uploads/.htaccess' => 'old htaccess',
            'unchanged.php' => 'same',
            // created by UpdatePackage::getBackupPath()
            'protected/runtime/updater/backups' => null,
        ]);

        $this->createFiles($package, [
            'index.php' => 'new index',
            'robots.txt' => 'default robots',
            'protected/humhub/Module.php' => 'new core',
            'protected/vendor/lib.php' => 'new vendor',
            'protected/config/common.php' => 'default config',
            'protected/config/new.php' => 'new config file',
            'uploads/.htaccess' => 'new htaccess',
            'uploads/file/a.jpg' => 'package image',
            'unchanged.php' => 'same',
            'new/dir/file.php' => 'new file',
            'public/assets' => null,
        ]);

        return [$base, $package, $backup];
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/updater-test-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    /**
     * @param array<string, string|null> $files relative path => content, null creates a directory
     */
    private function createFiles(string $dir, array $files): void
    {
        foreach ($files as $path => $content) {
            $path = $dir . '/' . $path;
            if ($content === null) {
                @mkdir($path, 0777, true);
                continue;
            }
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, $content);
        }
    }

    /**
     * @return string[] relative paths of the backed up files, with the backup id replaced by `ID`
     */
    private function getBackupFiles(string $backup): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backup, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $files[] = preg_replace('/_\d+/', '_ID', substr($file->getPathname(), strlen($backup) + 1));
            }
        }
        sort($files);

        return $files;
    }

    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        chmod($path, 0777);
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removePath($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
