<?php
declare( strict_types = 1 );

define( 'TESTS_ROOT_DIR', dirname( __DIR__ ) );
define( 'ROOT_DIR', dirname( TESTS_ROOT_DIR ) );

require_once TESTS_ROOT_DIR . '/inc/wp_functions.php';
require_once ROOT_DIR . '/vendor/autoload.php';

Hamcrest\Util::registerGlobalFunctions();
