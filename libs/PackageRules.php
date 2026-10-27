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
            foreach ($section as $type => $paths) {
                if (!in_array($type, self::TYPES, true)) {
                    throw new InvalidArgumentException('Invalid package rules type: ' . $type);
                }
                foreach ($paths as $path) {
                    $path = ltrim($path, '!');
                    if ($path === '' || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path)) {
                        throw new InvalidArgumentException('Invalid package rules path: ' . $path);
                    }
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
     * @return string[] delete patterns of all sections crossed by the update, in ascending version order
     */
    public function getDelete(string $fromVersion, string $toVersion): array
    {
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
     */
    private function getState(string $type, string $toVersion): array
    {
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
}
