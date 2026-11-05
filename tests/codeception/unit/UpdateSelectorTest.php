<?php

namespace humhub\modules\updater\tests\codeception\unit;

use Codeception\Test\Unit;
use humhub\modules\updater\libs\UpdateSelector;

class UpdateSelectorTest extends Unit
{
    public function testInstalledMajorLineFirst()
    {
        $releases = [$this->release('1.20', '1.20.0'), $this->release('1.19', '1.19.4'), $this->release('1.18', '1.18.5')];

        $this->assertSelected('1.19.4', $releases, '1.19.3', 'newer major listed first');
        $this->assertSelected('1.19.4', array_reverse($releases), '1.19.0');
    }

    public function testNextMajorLineWhenInstalledLineIsUpToDate()
    {
        $releases = [$this->release('1.19', '1.19.4'), $this->release('1.20', '1.20.2'), $this->release('1.21', '1.21.0')];

        $this->assertSelected('1.20.2', $releases, '1.19.4');
        $this->assertSelected('1.20.2', array_reverse($releases), '1.19.4', 'order independent');
    }

    public function testMajorLinesAreNeverSkipped()
    {
        $releases = [$this->release('1.21', '1.21.0'), $this->release('1.20', '1.20.2')];

        $this->assertSelected('1.20.2', $releases, '1.18.5', 'installed line missing in list');
        $this->assertSelected('1.21.0', $releases, '1.20.2');
    }

    public function testNoUpdate()
    {
        $releases = [$this->release('1.19', '1.19.4'), $this->release('1.18', '1.18.5')];

        $this->assertSelected(null, $releases, '1.19.4', 'up to date');
        $this->assertSelected(null, $releases, '1.20.0', 'newer than all releases');
        $this->assertSelected(null, [], '1.19.4', 'empty list');
    }

    public function testInvalidEntriesAreIgnored()
    {
        $releases = [['version' => '1.19.9'], ['majorVersion' => '1.19'], [], $this->release('1.19', '1.19.4')];

        $this->assertSelected('1.19.4', $releases, '1.19.3');
    }

    public function testPreReleases()
    {
        $this->assertSelected('1.20.0', [$this->release('1.20', '1.20.0')], '1.20.0-beta.1', 'pre-release to final');
        $this->assertSelected('1.20.0-beta.2', [$this->release('1.20', '1.20.0-beta.2')], '1.20.0-beta.1', 'pre-release to newer pre-release');
        $this->assertSelected('1.20.0-beta.1', [$this->release('1.20', '1.20.0-beta.1')], '1.19.4', 'next major as pre-release');
        $this->assertSelected(null, [$this->release('1.20', '1.20.0-beta.1')], '1.20.0', 'final is newer than pre-release');
    }

    public function testVersionsAreComparedNumerically()
    {
        $releases = [$this->release('1.9', '1.9.9'), $this->release('1.10', '1.10.1')];

        $this->assertSelected('1.10.1', $releases, '1.10.0');
        $this->assertSelected('1.10.1', $releases, '1.9.9');
    }

    private function assertSelected(?string $expected, array $releases, string $installedVersion, string $message = ''): void
    {
        $this->assertSame($expected, UpdateSelector::select($releases, $installedVersion)['version'] ?? null, $message);
    }

    private function release(string $majorVersion, string $version): array
    {
        return ['majorVersion' => $majorVersion, 'version' => $version, 'downloadUrl' => 'https://example.com/humhub-' . $version . '.zip'];
    }
}
