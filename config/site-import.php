<?php

return [
    // Personal ASUS workspace. Access must be granted explicitly before adding another account.
    'user_ids' => [2],
    'max_files' => 2500,
    'max_bytes' => 150 * 1024 * 1024,
    'max_source_bytes' => 512 * 1024,
    // Verified against the ASUS virtual-host configuration. Aliases share one document root.
    'sources' => [
        'postovna.online' => [
            'name' => 'Poštovňa Online',
            'domain' => 'postovna.online',
            'root' => '/www/wwwroot/postovna.online',
            'aliases' => ['www.postovna.online'],
        ],
    ],
];
