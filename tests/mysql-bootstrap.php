<?php

require dirname(__DIR__).'/vendor/autoload.php';

// RefreshDatabase may rebuild tables. Only the explicitly named test database is allowed.
if (getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_DATABASE') !== 'TourDB_codex_test' || getenv('DB_URL')) {
    throw new RuntimeException('MySQL tests require the dedicated TourDB_codex_test database.');
}
