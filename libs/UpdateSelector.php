<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2026 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\libs;

/**
 * Picks the next update from the `v1/core/releases` response (latest release per major line).
 *
 * Updates always go to the latest release of the installed major line first, and only then to the
 * latest release of the next major line. Major lines are never skipped.
 */
class UpdateSelector
{
    /**
     * @param array $releases release entries with `majorVersion` and `version`
     * @param string $installedVersion
     * @return array|null the release entry to update to
     */
    public static function select(array $releases, string $installedVersion): ?array
    {
        $installedMajor = preg_replace('/^(\d+\.\d+).*$/', '$1', $installedVersion);

        $next = null;
        foreach ($releases as $release) {
            if (!isset($release['majorVersion'], $release['version'])
                || !version_compare($release['version'], $installedVersion, '>')) {
                continue;
            }

            if ($release['majorVersion'] === $installedMajor) {
                return $release;
            }

            if (version_compare($release['majorVersion'], $installedMajor, '>')
                && ($next === null || version_compare($release['majorVersion'], $next['majorVersion'], '<'))) {
                $next = $release;
            }
        }

        return $next;
    }
}
