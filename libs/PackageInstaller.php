<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2026 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\libs;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Installs the files of an extracted full release package into the installation directory.
 *
 * Everything from the package overwrites the installation, except the exceptions defined by the
 * package rules (see `docs/PACKAGE-RULES.md`). Replaced, overwritten and deleted files are moved to
 * the backup directory.
 *
 * Framework independent, so it can be tested without a HumHub installation.
 */
class PackageInstaller
{
    private string $backupId;

    /**
     * @param string $baseDir installation directory
     * @param string $packageDir extracted package directory (`humhub-<version>`)
     * @param string $backupDir backup directory
     */
    public function __construct(
        private string $baseDir,
        private string $packageDir,
        private string $backupDir,
        private PackageRules $rules,
        private string $fromVersion,
        private string $toVersion,
    ) {
        $this->baseDir = rtrim($baseDir, '/\\');
        $this->packageDir = rtrim($packageDir, '/\\');
        $this->backupId = (string)time();
    }

    /**
     * @return string[] absolute paths which must be writable but are not
     */
    public function getNotWritable(): array
    {
        $notWritable = [];

        foreach ($this->getReplace() as $dir) {
            $target = $this->baseDir . '/' . $dir;
            if (!$this->isWritable(dirname($target)) || (file_exists($target) && !is_writable($target))) {
                $notWritable[] = $target;
            }
        }

        foreach ($this->getPackageFiles() as $relPath) {
            $target = $this->baseDir . '/' . $relPath;
            if ($this->exists($target) && PackageRules::isKept($relPath, $this->getKeep())) {
                continue;
            }
            if ($this->isIdentical($this->packageDir . '/' . $relPath, $target)) {
                continue;
            }
            if (is_link($target) ? !is_writable(dirname($target)) : !$this->isWritable($target)) {
                $notWritable[] = $target;
            }
        }

        foreach ($this->getPackageDirectories() as $relPath) {
            $target = $this->baseDir . '/' . $relPath;
            if (!$this->exists($target) && !$this->isWritable($target)) {
                $notWritable[] = $target;
            }
        }

        foreach ($this->getDeleteTargets() as $target) {
            if (!is_writable(dirname($target)) || (is_dir($target) && !is_link($target) && !is_writable($target))) {
                $notWritable[] = $target;
            }
        }

        if (!$this->isWritable($this->backupDir)) {
            $notWritable[] = $this->backupDir;
        }

        // directories are swapped with rename(), which does not work across filesystems
        if ($this->getReplace() !== []) {
            $device = $this->getDevice($this->baseDir);
            foreach ([$this->packageDir, $this->backupDir] as $dir) {
                if ($device === null || $this->getDevice($dir) !== $device) {
                    $notWritable[] = $dir;
                }
            }
        }

        return array_values(array_unique($notWritable));
    }

    public function install(): void
    {
        foreach ($this->getReplace() as $dir) {
            if (!is_dir($this->packageDir . '/' . $dir)) {
                throw new RuntimeException('Package does not contain: ' . $dir);
            }
        }

        $replaced = [];
        try {
            foreach ($this->getReplace() as $dir) {
                $replaced[$dir] = $this->replaceDirectory($dir);
            }
        } catch (RuntimeException $e) {
            foreach (array_reverse($replaced, true) as $dir => $backup) {
                $this->restoreDirectory($dir, $backup);
            }
            throw $e;
        }

        foreach ($this->getPackageFiles() as $relPath) {
            $this->installFile($relPath);
        }

        foreach ($this->getPackageDirectories() as $relPath) {
            $target = $this->baseDir . '/' . $relPath;
            if (!$this->exists($target) && !mkdir($target, 0777, true)) {
                throw new RuntimeException('Could not create directory: ' . $target);
            }
        }

        foreach ($this->getDeleteTargets() as $target) {
            if (!$this->exists($target)) {
                // already removed with a parent directory matched by another pattern
                continue;
            }
            $this->moveToBackup($target);
            $this->removeEmptyParents(dirname($target));
        }
    }

    /**
     * Swaps an installation directory with the package directory. On failure the installation directory
     * is restored.
     *
     * @return string|null backup path of the previous directory, null if it did not exist
     */
    private function replaceDirectory(string $dir): ?string
    {
        $target = $this->baseDir . '/' . $dir;
        $backup = null;

        if ($this->exists($target)) {
            $backup = $this->backupDir . '/' . basename($dir) . '_' . $this->backupId;
            if (!$this->renamePath($target, $backup)) {
                throw new RuntimeException('Could not move to backup: ' . $target);
            }
        }

        if (!$this->renamePath($this->packageDir . '/' . $dir, $target)) {
            if ($backup !== null && !$this->renamePath($backup, $target)) {
                throw new RuntimeException('Could not install: ' . $target . ', restore from backup failed: ' . $backup);
            }
            throw new RuntimeException('Could not install: ' . $target);
        }

        return $backup;
    }

    /**
     * Undoes a directory swap: the installed directory goes back to the package, the backup is restored.
     */
    private function restoreDirectory(string $dir, ?string $backup): void
    {
        $target = $this->baseDir . '/' . $dir;
        if (!$this->renamePath($target, $this->packageDir . '/' . $dir)) {
            throw new RuntimeException('Rollback failed, could not remove: ' . $target);
        }
        if ($backup !== null && !$this->renamePath($backup, $target)) {
            throw new RuntimeException('Rollback failed, could not restore: ' . $backup);
        }
    }

    /**
     * Renames a file or directory (seam for tests).
     */
    protected function renamePath(string $from, string $to): bool
    {
        return @rename($from, $to);
    }

    private function installFile(string $relPath): void
    {
        $source = $this->packageDir . '/' . $relPath;
        $target = $this->baseDir . '/' . $relPath;

        if ($this->exists($target)) {
            if (PackageRules::isKept($relPath, $this->getKeep())) {
                return;
            }
            if ($this->isIdentical($source, $target)) {
                return;
            }
            if (file_exists($target)) {
                $this->copyToBackup($target, $relPath);
            }
            // never write through a symlink into a file outside the installation
            if (is_link($target) && !unlink($target)) {
                throw new RuntimeException('Could not remove symlink: ' . $target);
            }
        }

        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0777, true)) {
            throw new RuntimeException('Could not create directory: ' . dirname($target));
        }
        if (!copy($source, $target)) {
            throw new RuntimeException('Could not install: ' . $target);
        }
    }

    /**
     * @return string[] relative paths of all package files outside the replaced directories
     */
    private function getPackageFiles(): array
    {
        $replaced = array_map(fn($dir) => $dir . '/*', $this->getReplace());

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->packageDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($this->packageDir) + 1));
            if (!PackageRules::matches($relPath, $replaced)) {
                $files[] = $relPath;
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @return string[] relative paths of all package directories outside the replaced directories
     */
    private function getPackageDirectories(): array
    {
        $replaced = array_map(fn($dir) => $dir . '/*', $this->getReplace());

        $dirs = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->packageDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                continue;
            }
            $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($this->packageDir) + 1));
            if (!PackageRules::matches($relPath, $replaced)) {
                $dirs[] = $relPath;
            }
        }
        sort($dirs);

        return $dirs;
    }

    /**
     * @return string[] existing absolute paths matched by the delete rules
     */
    private function getDeleteTargets(): array
    {
        $realBase = realpath($this->baseDir);
        $base = preg_replace('/[\\[\\]*?]/', '\\\\$0', $this->baseDir);

        $targets = [];
        foreach ($this->getDelete() as $pattern) {
            $path = str_ends_with($pattern, '/*') ? substr($pattern, 0, -2) : $pattern;
            foreach (glob($base . '/' . $path, GLOB_NOSORT) ?: [] as $target) {
                $realParent = realpath(dirname($target));
                if ($realBase === false || $realParent === false
                    || ($realParent !== $realBase && !str_starts_with($realParent, $realBase . '/'))) {
                    throw new RuntimeException('Delete target outside of the installation: ' . $target);
                }
                $targets[] = $target;
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * Checks if the installed file has the same content as the package file.
     */
    private function isIdentical(string $source, string $target): bool
    {
        return is_file($target) && !is_link($target)
            && filesize($target) === filesize($source)
            && hash_file('sha256', $target) === hash_file('sha256', $source);
    }

    /**
     * Checks if a path exists, including dangling symlinks.
     */
    private function exists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    /**
     * @return int|null device id of the filesystem of the path, or of its nearest existing parent
     */
    protected function getDevice(string $path): ?int
    {
        while (!file_exists($path)) {
            $parent = dirname($path);
            if ($parent === $path) {
                return null;
            }
            $path = $parent;
        }

        $stat = @stat($path);

        return $stat === false ? null : $stat['dev'];
    }

    private function copyToBackup(string $target, string $relPath): void
    {
        $backup = $this->backupDir . '/files_' . $this->backupId . '/' . $relPath;
        if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0777, true)) {
            throw new RuntimeException('Could not create backup directory: ' . dirname($backup));
        }
        if (!copy($target, $backup)) {
            throw new RuntimeException('Could not back up: ' . $target);
        }
    }

    private function moveToBackup(string $target): void
    {
        $backup = $this->backupDir . '/files_' . $this->backupId . '/' . substr($target, strlen($this->baseDir) + 1);
        if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0777, true)) {
            throw new RuntimeException('Could not create backup directory: ' . dirname($backup));
        }
        if (!rename($target, $backup)) {
            throw new RuntimeException('Could not delete: ' . $target);
        }
    }

    /**
     * Removes directories left empty by deletions, up to the installation directory.
     */
    private function removeEmptyParents(string $dir): void
    {
        while ($dir !== $this->baseDir && str_starts_with($dir, $this->baseDir . '/') && @rmdir($dir)) {
            $dir = dirname($dir);
        }
    }

    /**
     * Checks if a path is writable, or can be created in the nearest existing parent directory.
     */
    private function isWritable(string $path): bool
    {
        while (!file_exists($path)) {
            $parent = dirname($path);
            if ($parent === $path) {
                return false;
            }
            $path = $parent;
        }

        return is_writable($path);
    }

    private function getReplace(): array
    {
        return $this->rules->getReplace($this->toVersion);
    }

    private function getKeep(): array
    {
        return $this->rules->getKeep($this->toVersion);
    }

    private function getDelete(): array
    {
        return $this->rules->getDelete($this->fromVersion, $this->toVersion);
    }
}
