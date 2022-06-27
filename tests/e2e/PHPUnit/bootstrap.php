<?php
declare(strict_types=1);

define('E2E_TESTS_ROOT_DIR', dirname(__DIR__));
define('ROOT_DIR', dirname(dirname(E2E_TESTS_ROOT_DIR)));

require_once ROOT_DIR . '/vendor/autoload.php';

if (file_exists(ROOT_DIR . '/.env.e2e')) {
	$dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR, '.env.e2e');
	$dotenv->load();
}

if (!isset($_ENV['PPCP_E2E_WP_DIR'])) {
	exit('Copy .env.e2e.example to .env.e2e or define the environment variables.' . PHP_EOL);
}
$wpRootDir =  str_replace('${ROOT_DIR}', ROOT_DIR, $_ENV['PPCP_E2E_WP_DIR']);

define('WP_ROOT_DIR', $wpRootDir);

$_SERVER['HTTP_HOST'] = ''; // just to avoid a warning

require_once WP_ROOT_DIR . '/wp-load.php';
