<?php

namespace humhub\modules\updater\tests\codeception\unit;

use Codeception\Test\Unit;
use humhub\modules\updater\libs\PackageRules;
use InvalidArgumentException;

class PackageRulesTest extends Unit
{
    /**
     * @dataProvider invalidRulesProvider
     */
    public function testInvalidRulesAreRejected(array $sections)
    {
        $this->expectException(InvalidArgumentException::class);
        new PackageRules($sections);
    }

    public static function invalidRulesProvider(): array
    {
        return [
            'version without patch' => [['1.19' => []]],
            'pre-release version' => [['1.20.0-beta.1' => []]],
            'section not an array' => [['1.19.0' => 'replace']],
            'unknown type' => [['1.19.0' => ['remove' => ['a']]]],
            'list not an array' => [['1.19.0' => ['keep' => 'a']]],
            'path not a string' => [['1.19.0' => ['keep' => [1]]]],
            'leading slash' => [['1.19.0' => ['keep' => ['/a']]]],
            'trailing slash' => [['1.19.0' => ['keep' => ['a/']]]],
            'empty segment' => [['1.19.0' => ['keep' => ['a//b']]]],
            'dot segment' => [['1.19.0' => ['keep' => ['a/./b']]]],
            'dot dot segment' => [['1.19.0' => ['delete' => ['../a']]]],
            'empty path' => [['1.19.0' => ['keep' => ['']]]],
            'exclusion in replace' => [['1.19.0' => ['replace' => ['!a']]]],
            'exclusion in delete' => [['1.19.0' => ['delete' => ['!a']]]],
            'wildcard in replace' => [['1.19.0' => ['replace' => ['a/*']]]],
            'question mark in replace' => [['1.19.0' => ['replace' => ['a?']]]],
            'bracket in replace' => [['1.19.0' => ['replace' => ['[a]']]]],
            'invalid exclusion' => [['1.19.0' => ['keep' => ['!../a']]]],
        ];
    }

    public function testShippedRulesAreValid()
    {
        $rules = PackageRules::fromFile(dirname(__DIR__, 3) . '/config/package-rules.php');

        $this->assertSame(['protected/humhub', 'protected/vendor'], $rules->getReplace('1.19.3'));
        $this->assertContains('robots.txt', $rules->getKeep('1.19.3'));
        $this->assertContains('.env', $rules->getKeep('1.20.0'));
        $this->assertNotContains('.env', $rules->getKeep('1.19.3'));
        $this->assertSame([], $rules->getDelete('1.19.2', '1.19.3'));
        $this->assertSame(['index-test.php', '.htaccess.dist'], $rules->getDelete('1.19.3', '1.20.0'));
    }

    public function testReplaceAndKeepFromHighestSectionBelowTarget()
    {
        // unordered on purpose
        $rules = new PackageRules([
            '1.20.0' => ['replace' => ['b'], 'keep' => ['k20']],
            '1.19.0' => ['replace' => ['a'], 'keep' => ['k19']],
            '1.21.0' => ['delete' => ['x']],
        ]);

        $this->assertSame([], $rules->getReplace('1.18.9'), 'below lowest section');
        $this->assertSame(['a'], $rules->getReplace('1.19.0'));
        $this->assertSame(['a'], $rules->getReplace('1.19.9'));
        $this->assertSame(['b'], $rules->getReplace('1.20.0'));
        $this->assertSame(['k20'], $rules->getKeep('1.20.5'));
        $this->assertSame(['b'], $rules->getReplace('1.21.0'), 'delete-only section inherits replace');
        $this->assertSame(['k20'], $rules->getKeep('1.21.3'), 'delete-only section inherits keep');
        $this->assertSame(['b'], $rules->getReplace('1.20.0-beta.1'), 'pre-release uses base version');
        $this->assertSame(['k20'], $rules->getKeep('1.20.0-rc.2'), 'pre-release uses base version');
        $this->assertSame(['a'], $rules->getReplace('1.19.10'), 'versions are compared numerically');
    }

    public function testSectionsAreNotMerged()
    {
        $rules = new PackageRules([
            '1.19.0' => ['replace' => ['a'], 'keep' => ['k1', 'k2']],
            '1.20.0' => ['keep' => ['k1']],
        ]);

        $this->assertSame(['k1'], $rules->getKeep('1.20.0'));
        $this->assertSame(['a'], $rules->getReplace('1.20.0'), 'replace not defined in 1.20.0');
    }

    public function testDeleteOfAllCrossedSections()
    {
        $rules = new PackageRules([
            '1.19.0' => ['delete' => ['d19']],
            '1.20.0' => ['delete' => ['d20a', 'd20b']],
            '1.21.0' => ['delete' => ['d21']],
        ]);

        // examples from docs/PACKAGE-RULES.md
        $this->assertSame([], $rules->getDelete('1.19.1', '1.19.3'));
        $this->assertSame(['d20a', 'd20b'], $rules->getDelete('1.19.3', '1.20.0-beta.1'));
        $this->assertSame(['d20a', 'd20b'], $rules->getDelete('1.20.0-beta.1', '1.20.0'));
        $this->assertSame(['d20a', 'd20b'], $rules->getDelete('1.19.3', '1.20.0'));
        $this->assertSame(['d20a', 'd20b'], $rules->getDelete('1.19.1', '1.20.2'));
        $this->assertSame([], $rules->getDelete('1.20.0', '1.20.1'));

        $this->assertSame(['d19', 'd20a', 'd20b', 'd21'], $rules->getDelete('1.18.0', '1.21.0'), 'ascending order');
        $this->assertSame(['d20a', 'd20b', 'd21'], $rules->getDelete('1.19.0', '1.21.0'), 'from version itself excluded');
    }

    public function testMatches()
    {
        $this->assertTrue(PackageRules::matches('uploads', ['uploads/*']), 'dir/* matches the directory itself');
        $this->assertTrue(PackageRules::matches('uploads/a.jpg', ['uploads/*']));
        $this->assertTrue(PackageRules::matches('uploads/a/b/c.jpg', ['uploads/*']), 'whole subtree');
        $this->assertFalse(PackageRules::matches('uploads2/a.jpg', ['uploads/*']), 'prefix of another name');
        $this->assertFalse(PackageRules::matches('x/uploads/a.jpg', ['uploads/*']), 'anchored at the root');

        $this->assertTrue(PackageRules::matches('robots.txt', ['robots.txt']));
        $this->assertFalse(PackageRules::matches('public/robots.txt', ['robots.txt']), 'exact path');
        $this->assertFalse(PackageRules::matches('config/common.php', ['config']), 'directory without /* does not match files');

        $this->assertTrue(PackageRules::matches('config/web.example.php', ['config/*.example.php']));
        $this->assertFalse(PackageRules::matches('config/sub/web.example.php', ['config/*.example.php']), '* does not cross directories');
        $this->assertTrue(PackageRules::matches('config/.env', ['config/*.php', 'config/*']), 'dotfiles in dir/*');
        $this->assertTrue(PackageRules::matches('.env', ['*']), '* matches dotfiles');

        $this->assertFalse(PackageRules::matches('a', []));
    }

    public function testIsKeptWithExclusions()
    {
        $keep = ['uploads/*', '!uploads/.htaccess', '!uploads/file/.htaccess', 'config/*', '!config/*.example.php'];

        $this->assertTrue(PackageRules::isKept('uploads/file/abc/file.jpg', $keep));
        $this->assertFalse(PackageRules::isKept('uploads/.htaccess', $keep));
        $this->assertFalse(PackageRules::isKept('uploads/file/.htaccess', $keep));
        $this->assertTrue(PackageRules::isKept('uploads/profile_image/.htaccess', $keep));
        $this->assertTrue(PackageRules::isKept('config/common.php', $keep));
        $this->assertFalse(PackageRules::isKept('config/common.example.php', $keep));
        $this->assertFalse(PackageRules::isKept('index.php', $keep));
        $this->assertFalse(PackageRules::isKept('index.php', []));
        $this->assertFalse(PackageRules::isKept('uploads/a', ['!uploads/*']), 'only exclusions');
    }
}
