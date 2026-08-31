<?php
/**
 * A minimal stand-in for WordPress' WP_Post, which does not exist in the
 * unit-test runtime (WordPress core is not loaded). Only needed so
 * `$GLOBALS['post'] instanceof WP_Post` checks can be made true in a test.
 *
 * Guarded because integration tests boot a different bootstrap
 * (tests/integration/PHPUnit/bootstrap.php) that loads real WordPress via
 * wp-load.php; this file is only required from the unit-test bootstrap, but
 * the guard keeps that safe by construction rather than by coincidence.
 */

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
	}
}
