<?php
/**
 * Class MoneyFormatter.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * Class MoneyFormatter
 */
class MoneyFormatter {
	/**
	 * Currencies that does not support decimals.
	 *
	 * @var array
	 */
	private $currencies_without_decimals = array( 'HUF', 'JPY', 'TWD' );

	/**
	 * Returns the value formatted as string for API requests.
	 *
	 * @param float  $value The value.
	 * @param string $currency The 3-letter currency code.
	 * @param bool   $round_to_floor If value rounding should be floor.
	 *
	 * @return string
	 */
	public function format( float $value, string $currency, bool $round_to_floor = false ): string {
		if ( $round_to_floor ) {
			return in_array( $currency, $this->currencies_without_decimals, true )
				? (string) floor( $value )
				: number_format( $this->floor_with_decimals( $value, 2 ), 2, '.', '' );
		}

		return in_array( $currency, $this->currencies_without_decimals, true )
			? (string) round( $value, 0 )
			: number_format( $value, 2, '.', '' );
	}

	/**
	 * Rounds to floor with decimal precision.
	 *
	 * @param float $value The value.
	 * @param int   $decimals The number of decimals.
	 * @return float
	 */
	private function floor_with_decimals( float $value, int $decimals = 0 ): float {
		$adjustment = (float) pow( 10, $decimals );
		return floor( $value * $adjustment ) / $adjustment;
	}
}
