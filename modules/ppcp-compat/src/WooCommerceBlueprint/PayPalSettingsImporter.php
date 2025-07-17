<?php
/**
 * PayPal Settings Blueprint Importer
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use Automattic\WooCommerce\Blueprint\StepProcessor;
use Automattic\WooCommerce\Blueprint\StepProcessorResult;
use Automattic\WooCommerce\Blueprint\Steps\SetSiteOptions;

/**
 * PayPal Settings Importer
 */
class PayPalSettingsImporter implements StepProcessor {

	/**
	 * Process PayPal settings import
	 *
	 * @param object $schema Schema object.
	 * @return StepProcessorResult
	 */
	public function process( $schema ): StepProcessorResult {
		if ( ! isset( $schema->options ) || ! is_object( $schema->options ) ) {
			return StepProcessorResult::error(
				'setSiteOptions',
				'Invalid PayPal options data'
			);
		}

		$options        = (array) $schema->options;
		$imported_count = 0;
		$errors         = [];

		foreach ( $options as $option_name => $option_value ) {
			// Validate option name is PayPal related.
			if ( ! $this->is_paypal_option( $option_name ) ) {
				$errors[] = "Skipped non-PayPal option: {$option_name}";
				continue;
			}

			// Update the option.
			$result = update_option( $option_name, $option_value );
			if ( $result ) {
				$imported_count++;
			} else {
				// Check if option already exists with same value.
				if ( get_option( $option_name ) === $option_value ) {
					$imported_count++;
				} else {
					$errors[] = "Failed to update option: {$option_name}";
				}
			}
		}

		if ( ! empty( $errors ) ) {
			$message = "Imported {$imported_count} PayPal options with warnings: " . implode( ', ', $errors );
			return StepProcessorResult::warning( 'setSiteOptions', $message );
		}

		return StepProcessorResult::success(
			'setSiteOptions',
			"Successfully imported {$imported_count} PayPal options"
		);
	}

	/**
	 * Get step class
	 *
	 * @return string
	 */
	public function get_step_class(): string {
		return SetSiteOptions::class;
	}

	/**
	 * Check capabilities
	 *
	 * @param object $schema Schema object.
	 * @return bool
	 */
	public function check_step_capabilities( $schema ): bool {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'manage_options' );
	}

	/**
	 * Check if option is PayPal related
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	private function is_paypal_option( string $option_name ): bool {
		return false !== strpos( $option_name, 'ppcp' ) ||
			false !== strpos( $option_name, 'paypal' ) ||
			false !== strpos( $option_name, 'venmo' ) ||
			false !== strpos( $option_name, 'pay-later' );
	}
}
