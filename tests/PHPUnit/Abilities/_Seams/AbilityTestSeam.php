<?php
/**
 * Test-only seam that re-exposes `AbstractPpcpAbility`'s protected helpers
 * as public statics for direct assertion.
 *
 * Lives under a deliberately distinct sub-namespace
 * (`WooCommerce\PayPalCommerce\Abilities\_Seams`) so that even if a future
 * autoload-map change inadvertently wildcards the test directory into the
 * production autoload, this seam cannot land inside the production
 * `WooCommerce\PayPalCommerce\Abilities\Domain` namespace as a live
 * subclass of `AbstractPpcpAbility`.
 *
 * @internal Test-only; never autoloaded by production code.
 *
 * @package WooCommerce\PayPalCommerce\Abilities\Tests
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\_Seams;

use WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility;
use WP_Error;

/**
 * Test seam that re-exposes the protected helpers as public statics.
 *
 * @internal Test-only; never autoloaded by production code.
 */
class AbilityTestSeam extends AbstractPpcpAbility
{
	/**
	 * Public proxy for the protected envelope_error_or_null helper.
	 *
	 * @param array $payload         Decoded REST envelope.
	 * @param bool  $redact_message  See AbstractPpcpAbility::envelope_error_or_null().
	 * @return WP_Error|null
	 */
	public static function call_envelope_error_or_null( array $payload, bool $redact_message = true ): ?WP_Error
	{
		return self::envelope_error_or_null($payload, $redact_message);
	}
}
