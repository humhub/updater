<?php

/**
 * Package rules for updates with full release packages.
 *
 * Everything from the package overwrites the installation; these rules only define the exceptions.
 * Format, evaluation and how to change rules: docs/PACKAGE-RULES.md
 */
return [
    '1.19.0' => [
        'replace' => [
            'protected/humhub',
            'protected/vendor',
        ],
        'keep' => [
            'protected/config/*',
            'protected/modules/*',
            'protected/runtime/*',
            'uploads/*',
            '!uploads/.htaccess',
            '!uploads/file/.htaccess',
            'assets/*',
            'themes/*',
            'robots.txt',
        ],
    ],
    '1.20.0' => [
        'replace' => [
            'protected/humhub',
            'protected/vendor',
        ],
        'keep' => [
            'protected/config/*',
            'protected/modules/*',
            'protected/runtime/*',
            'uploads/*',
            '!uploads/.htaccess',
            '!uploads/file/.htaccess',
            'assets/*',
            'themes/*',
            'config/*',
            '!config/*.example.php',
            'public/assets/*',
            'robots.txt',
            'public/robots.txt',
            '.env',
        ],
        'delete' => [
            'index-test.php',
            '.htaccess.dist',
        ],
    ],
];
