<?php

require dirname(__DIR__).'/vendor/autoload.php';

// RefreshDatabase is destructive. This suite may only run with the isolated test role.
if (getenv('DB_DATABASE') !== 'webarchitect_test' || getenv('DB_USERNAME') !== 'webarchitect_test_app') {
    throw new RuntimeException('CMS tests require the isolated webarchitect_test database and role.');
}
