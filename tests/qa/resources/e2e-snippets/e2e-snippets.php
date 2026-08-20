<?php
/**
 * Plugin Name:	E2E Snippets
 *
 * Description:	Snippets used in E2E tests.
 */

/**
 * Disable the "Disable Welcome Messages" in the Gutenberg Editor.
 */
add_filter(
	'block_editor_settings_all',
	function (array $settings): array {
		$settings['welcomeGuide'] = false;
		return $settings;
	}
);

/**
 * Disable WooCommerce Setup Wizard
 */
delete_transient('_wc_activation_redirect');
add_filter('woocommerce_enable_setup_wizard', '__return_false');


/**
 * Disable webhook verification
 */
const PAYPAL_WEBHOOK_REQUEST_VERIFICATION = false;
if ( ! defined( 'PAYPAL_WEBHOOK_REQUEST_VERIFICATION' ) ) {
	define( 'PAYPAL_WEBHOOK_REQUEST_VERIFICATION', false );
}

/**
 * Disable nonce check
 */
add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

/**
 * Enable New PCP UI
 */
add_filter('woocommerce.feature-flags.woocommerce_paypal_payments.settings_enabled', '__return_true');

/**
 * Per README.md's "Webhooks" section: for testing webhooks with ngrok, the
 * site itself stays local — only the webhook listening URL is exposed via
 * the public tunnel, through the NGROK_HOST environment variable that
 * IncomingWebhookEndpoint::url() reads via getenv(). wp-env has no way to
 * pass an arbitrary host env var into the tests-wordpress container, so in
 * CI the workflow writes the bare tunnel host next to this file instead,
 * and this reads it back into the request's environment.
 */
if ( ! getenv( 'NGROK_HOST' ) ) {
	$ngrok_host_file = __DIR__ . '/ngrok-host.txt';
	if ( file_exists( $ngrok_host_file ) ) {
		$ngrok_host = trim( (string) file_get_contents( $ngrok_host_file ) );
		if ( $ngrok_host ) {
			putenv( 'NGROK_HOST=' . $ngrok_host );
		}
	}
}
