<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2026 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\libs;

use InvalidArgumentException;

/**
 * Evaluates the package rules (`config/package-rules.php`), see `docs/PACKAGE-RULES.md`.
 *
 * Framework independent, so it can be tested without a HumHub installation.
 */
class PackageRules
{
    private const TYPES = ['replace', 'keep', 'delete'];

    /**
     * @var array<string, array> rule sections by core version, ascending
     */
    private array $sections;

    public function __construct(array $sections)
    {
        foreach ($sections as $version => $section) {
            if (!preg_match('/^\d+\.\d+\.\d+$/', (string)$version)) {
                throw new InvalidArgumentException('Invalid package rules version: ' . $version);
            }
            if (!is_array($section)) {
                throw new InvalidArgumentException('Invalid package rules section: ' . $version);
            }
            foreach ($section as $type => $paths) {
                if (!in_array($type, self::TYPES, true)) {
                    throw new InvalidArgumentException('Invalid package rules type: ' . $type);
                }
                if (!is_array($paths)) {
                    throw new InvalidArgumentException('Invalid package rules list: ' . $version . ' ' . $type);
                }
                foreach ($paths as $path) {
                    self::validatePath($type, $path);
                }
            }
        }

        uksort($sections, 'version_compare');
        $this->sections = $sections;
    }

    public static function fromFile(string $file): self
    {
        return new self(require $file);
    }

    /**
     * @return string[] directories replaced as a whole for the given target version
     */
    public function getReplace(string $toVersion): array
    {
        return $this->getState('replace', $toVersion);
    }

    /**
     * @return string[] keep patterns (incl. `!` exclusions) for the given target version
     */
    public function getKeep(string $toVersion): array
    {
        return $this->getState('keep', $toVersion);
    }

    /**
     * Pre-release target versions use their base version, so entering a new layout with a pre-release
     * applies its deletes (they are applied again with the final release, which finds nothing).
     *
     * @return string[] delete patterns of all sections crossed by the update, in ascending version order
     */
    public function getDelete(string $fromVersion, string $toVersion): array
    {
        $toVersion = self::getBaseVersion($toVersion);

        $paths = [];
        foreach ($this->sections as $version => $section) {
            if (version_compare($version, $fromVersion, '>') && version_compare($version, $toVersion, '<=')) {
                $paths = array_merge($paths, $section['delete'] ?? []);
            }
        }

        return $paths;
    }

    /**
     * Checks if a relative path is protected by the keep patterns (`!` patterns exclude again).
     */
    public static function isKept(string $path, array $keep): bool
    {
        $include = array_filter($keep, fn($p) => !str_starts_with($p, '!'));
        $exclude = array_map(fn($p) => substr($p, 1), array_filter($keep, fn($p) => str_starts_with($p, '!')));

        return self::matches($path, $include) && !self::matches($path, $exclude);
    }

    /**
     * Checks if a relative path matches one of the patterns. A pattern ending with `/*` matches the
     * directory itself and its whole subtree; other patterns match a single path (`*` within a name allowed).
     */
    public static function matches(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '/*')) {
                $dir = substr($pattern, 0, -2);
                if ($path === $dir || str_starts_with($path, $dir . '/')) {
                    return true;
                }
            } elseif (fnmatch($pattern, $path, FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Uses the section with the highest version <= target version which defines the given type.
     * Pre-release target versions use the rules of their base version (`1.20.0-beta.1` → `1.20.0`).
     */
    private function getState(string $type, string $toVersion): array
    {
        $toVersion = self::getBaseVersion($toVersion);

        $result = [];
        foreach ($this->sections as $version => $section) {
            if (version_compare($version, $toVersion, '>')) {
                break;
            }
            if (isset($section[$type])) {
                $result = $section[$type];
            }
        }

        return $result;
    }

    /**
     * Strips a pre-release or build suffix (`1.20.0-beta.1` → `1.20.0`).
     */
    private static function getBaseVersion(string $version): string
    {
        return preg_match('/^\d+\.\d+\.\d+/', $version, $match) ? $match[0] : $version;
    }

    private static function validatePath(string $type, mixed $path): void
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Invalid package rules path in ' . $type);
        }

        $pattern = $path;
        if (str_starts_with($pattern, '!')) {
            if ($type !== 'keep') {
                throw new InvalidArgumentException('Exclusions are only allowed in keep: ' . $path);
            }
            $pattern = substr($pattern, 1);
        }

        foreach (explode('/', $pattern) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Invalid package rules path: ' . $path);
            }
        }

        if ($type === 'replace' && strpbrk($pattern, '*?[') !== false) {
            throw new InvalidArgumentException('Wildcards are not allowed in replace: ' . $path);
        }
    }
}
