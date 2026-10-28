<?php

return [
    'types' => [
        'laravel13' => 'Laravel 13',
        'php' => 'PHP',
        'python' => 'Python',
        'nodejs' => 'Node.js',
        'static' => 'Statický web (HTML/CSS/JS)',
    ],
    'templates' => [
        'blank' => ['name' => 'Základný web', 'description' => 'Prázdny základ s úvodnou stránkou.'],
        'business' => ['name' => 'Firemný web', 'description' => 'Úvod, služby, o nás a kontakt.'],
        'admin' => ['name' => 'Administrácia / CMS', 'description' => 'Administrácia s navigáciou a pracovnou plochou.'],
    ],
    'databases' => ['pgsql' => 'PostgreSQL', 'mysql' => 'MariaDB / MySQL', 'sqlite' => 'SQLite'],
    'environments' => ['development' => 'Vývoj', 'staging' => 'Testovanie', 'production' => 'Produkcia'],
    'defaults' => [
        'site_type' => 'laravel13',
        'description' => '',
        'git_repository' => '',
        'environment' => 'development',
        'setup' => [
            'from_template' => false,
            'template' => 'blank',
            'login_page' => false,
            'create_database' => false,
            'database_engine' => 'pgsql',
        ],
    ],
];
