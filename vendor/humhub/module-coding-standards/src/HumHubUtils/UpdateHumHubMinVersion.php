<?php

namespace HumHubUtils;

class UpdateHumHubMinVersion
{
    public static function increaseVersion($minHumHubVersion)
    {
        $moduleJsonPath = getcwd() . '/module.json';
        if (file_exists($moduleJsonPath)) {
            $moduleJson = json_decode(file_get_contents($moduleJsonPath), true);
            if (self::mustBeIncreased($moduleJson['humhub']['minVersion'], $minHumHubVersion)) {
                $moduleJson['humhub']['minVersion'] = $minHumHubVersion;
                file_put_contents(
                    $moduleJsonPath,
                    json_encode($moduleJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                );
            }
        } else {
            print "********** Module JSON not found!\n\n";
        }
    }

    private static function mustBeIncreased(string $currentVersion, string $minRequiredVersion): bool
    {
        return version_compare(
            self::normalizeVersion($currentVersion),
            self::normalizeVersion($minRequiredVersion),
            '<',
        );
    }

    private static function normalizeVersion(string $version): string
    {
        // Normalize a beta version e.g. from 1.18-beta.6 to 1.18.0.6,
        // because version_compare() decides version 1.18 as stable and higher than beta.
        return preg_replace('/[^0-9.]+/', '.0', $version);
    }
}