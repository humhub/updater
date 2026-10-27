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
            if (file_exists($target) && PackageRules::isKept($relPath, $this->getKeep())) {
                continue;
            }
            if (!$this->isWritable($target)) {
                $notWritable[] = $target;
            }
        }

        foreach ($this->getDeleteTargets() as $target) {
            if (!is_writable(dirname($target))) {
                $notWritable[] = $target;
            }
        }

        if (!$this->isWritable($this->backupDir)) {
            $notWritable[] = $this->backupDir;
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

        foreach ($this->getReplace() as $dir) {
            $this->replaceDirectory($dir);
        }

        foreach ($this->getPackageFiles() as $relPath) {
            $this->installFile($relPath);
        }

        foreach ($this->getDeleteTargets() as $target) {
            $this->moveToBackup($target);
            $this->removeEmptyParents(dirname($target));
        }
    }

    private function replaceDirectory(string $dir): void
    {
        $target = $this->baseDir . '/' . $dir;
        if (file_exists($target) && !rename($target, $this->backupDir . '/' . basename($dir) . '_' . $this->backupId)) {
            throw new RuntimeException('Could not move to backup: ' . $target);
        }
        if (!rename($this->packageDir . '/' . $dir, $target)) {
            throw new RuntimeException('Could not install: ' . $target);
        }
    }

    private function installFile(string $relPath): void
    {
        $source = $this->packageDir . '/' . $relPath;
        $target = $this->baseDir . '/' . $relPath;

        if (file_exists($target)) {
            if (PackageRules::isKept($relPath, $this->getKeep())) {
                return;
            }
            if (is_file($target) && filesize($target) === filesize($source) && hash_file('sha256', $target) === hash_file('sha256', $source)) {
                return;
            }
            $this->copyToBackup($target, $relPath);
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
     * @return string[] existing absolute paths matched by the delete rules
     */
    private function getDeleteTargets(): array
    {
        $targets = [];
        foreach ($this->getDelete() as $pattern) {
            $path = str_ends_with($pattern, '/*') ? substr($pattern, 0, -2) : $pattern;
            foreach (glob($this->baseDir . '/' . $path, GLOB_NOSORT) ?: [] as $target) {
                $targets[] = $target;
            }
        }

        return $targets;
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
