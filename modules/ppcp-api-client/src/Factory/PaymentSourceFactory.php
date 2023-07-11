<?php
/**
 * The PaymentSource factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Bancontact;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Blik;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Card;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Eps;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Giropay;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Ideal;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\MyBank;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\P24;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\PaymentSourceInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\PayPal;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Sofort;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Trustly;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class PaymentSourceFactory
 */
class PaymentSourceFactory {
	/**
	 * The experience context factory.
	 *
	 * @var ExperienceContextFactory
	 */
	private $experience_context_factory;

	/**
	 * PaymentSourceFactory constructor.
	 *
	 * @param ExperienceContextFactory $experience_context_factory The experience context factory.
	 */
	public function __construct( ExperienceContextFactory $experience_context_factory ) {
		$this->experience_context_factory = $experience_context_factory;
	}

	/**
	 * Returns a PaymentSource for a PayPal Response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return PaymentSource
	 */
	public function from_paypal_response( stdClass $data ): PaymentSource {

		$sources = array();

		if ( isset( $data->card ) ) {
			$authentication_result = null;
			if ( isset( $data->card->authentication_result ) ) {
				$authentication_result = new CardAuthenticationResult(
					isset( $data->card->authentication_result->liability_shift ) ?
						(string) $data->card->authentication_result->liability_shift : '',
					isset( $data->card->authentication_result->three_d_secure->enrollment_status ) ?
						(string) $data->card->authentication_result->three_d_secure->enrollment_status : '',
					isset( $data->card->authentication_result->three_d_secure->authentication_status ) ?
						(string) $data->card->authentication_result->three_d_secure->authentication_status : ''
				);
			}
			$sources[] = new Card(
				isset( $data->card->last_digits ) ? (string) $data->card->last_digits : '',
				isset( $data->card->brand ) ? (string) $data->card->brand : '',
				isset( $data->card->type ) ? (string) $data->card->type : '',
				$authentication_result
			);
		}
		// For now not handling other payment source objects here since we don't need them.

		return new PaymentSource( ...$sources );
	}

	/**
	 * Creates payment source for the current checkout.
	 *
	 * @param string     $payment_method The payment gateway ID.
	 * @param string     $funding_source The funding source ID.
	 * @param Payer|null $payer The payer.
	 * @param string     $shipping_preferences One of ExperienceContext::SHIPPING_PREFERENCE_ values.
	 * @param string     $user_action The user action.
	 *
	 * @throws RuntimeException When cannot create.
	 */
	public function from_checkout(
		string $payment_method,
		string $funding_source,
		?Payer $payer = null,
		string $shipping_preferences = ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		string $user_action = ExperienceContext::USER_ACTION_CONTINUE
	): PaymentSource {
		$context = $this->experience_context_factory->current_context( $shipping_preferences, $user_action );

		$source = null;
		switch ( $payment_method ) {
			case PayPalGateway::ID:
				$source = $this->create_apm_object( $funding_source, $context, $payer );
				if ( ! $source ) {
					$source = $this->create_paypal_object( $context );
				}
				break;
			case CreditCardGateway::ID:
				// TODO: currently unclear how to handle ACDC properly here.
				$source = new Card( '', '', '' );
				break;
		}

		if ( ! $source ) {
			throw new RuntimeException( "Cannot create payment source for $payment_method $funding_source" );
		}

		return new PaymentSource( $source );
	}

	/**
	 * Creates the PayPal payment source object.
	 *
	 * @param ExperienceContext $experience_context The experience context.
	 */
	private function create_paypal_object(
		ExperienceContext $experience_context
	): PaymentSourceInterface {
		return new PayPal(
			$experience_context
		);
	}

	/**
	 * Creates payment source object for the APM corresponding to the funding source ID.
	 *
	 * @param string            $funding_source The funding source ID.
	 * @param ExperienceContext $experience_context The experience context.
	 * @param Payer|null        $payer The payer.
	 */
	private function create_apm_object(
		string $funding_source,
		ExperienceContext $experience_context,
		?Payer $payer = null
	): ?PaymentSourceInterface {
		if ( ! $payer ) {
			return null;
		}

		$address   = $payer->address();
		$name      = $payer->name();
		$full_name = $name ? $name->full_name() : '';

		switch ( $funding_source ) {
			case 'bancontact':
				if ( $address && $address->country_code() && $full_name ) {
					return new Bancontact(
						$full_name,
						$address->country_code(),
						$experience_context
					);
				}
				break;
			case 'blik':
				if ( $address && $address->country_code() && $full_name ) {
					return new Blik(
						$full_name,
						$address->country_code(),
						$payer->email_address(),
						$experience_context
					);
				}
				break;
			case 'eps':
				if ( $address && $address->country_code() && $full_name ) {
					return new Eps(
						$full_name,
						$address->country_code(),
						$experience_context
					);
				}
				break;
			case 'giropay':
				if ( $address && $address->country_code() && $full_name ) {
					return new Giropay(
						$full_name,
						$address->country_code(),
						$experience_context
					);
				}
				break;
			case 'ideal':
				if ( $address && $address->country_code() && $full_name ) {
					return new Ideal(
						$full_name,
						$address->country_code(),
						'',
						$experience_context
					);
				}
				break;
			case 'mybank':
				if ( $address && $address->country_code() && $full_name ) {
					return new MyBank(
						$full_name,
						$address->country_code(),
						$experience_context
					);
				}
				break;
			case 'p24':
				if ( $address && $address->country_code() && $full_name && $payer->email_address() ) {
					return new P24(
						$full_name,
						$address->country_code(),
						$payer->email_address(),
						$experience_context
					);
				}
				break;
			case 'sofort':
				if ( $address && $address->country_code() && $full_name ) {
					return new Sofort(
						$full_name,
						$address->country_code(),
						$experience_context
					);
				}
				break;
			case 'trustly':
				if ( $address && $address->country_code() && $full_name ) {
					return new Trustly(
						$full_name,
						$address->country_code(),
						$experience_context
					);
				}
				break;
		}

		return null;
	}
}
