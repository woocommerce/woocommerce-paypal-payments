<?php
declare(strict_types=1);

define('INTEGRATION_TESTS_ROOT_DIR', dirname(__DIR__));
define('ROOT_DIR', dirname(dirname(INTEGRATION_TESTS_ROOT_DIR)));

require_once ROOT_DIR . '/vendor/autoload.php';

if (file_exists(ROOT_DIR . '/.env.integration')) {
	$dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR, '.env.integration');
	$dotenv->load();
}

if (!isset($_ENV['PPCP_INTEGRATION_WP_DIR'])) {
	exit('Copy .env.integration.example to .env.integration or define the environment variables.' . PHP_EOL);
}
$wpRootDir =  str_replace('${ROOT_DIR}', ROOT_DIR, $_ENV['PPCP_INTEGRATION_WP_DIR']);

define('WP_ROOT_DIR', $wpRootDir);

$_SERVER['HTTP_HOST'] = ''; // just to avoid a warning

require_once WP_ROOT_DIR . '/wp-load.php';
// Ensure the TestCase class is loaded
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/IntegrationMockedTestCase.php';
