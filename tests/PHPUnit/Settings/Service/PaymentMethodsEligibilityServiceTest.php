<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Service;


use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\Settings\Service\PaymentMethodsEligibilityService;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use Mockery;

class PaymentMethodsEligibilityServiceTest extends TestCase {

	public function test_apm_mexico(): void {
		$service = $this->get_service( 'MX' );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks[ OXXO::ID ]() );
		$this->assertFalse( $eligibility_checks[ PWCGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ BancontactGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ BlikGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ EPSGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ IDealGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ MyBankGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ P24Gateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ TrustlyGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ MultibancoGateway::ID ]() );
	}

	public function test_apm_no_mexico(): void {
		$service = $this->get_service( 'US' );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks[ OXXO::ID ]() );
		$this->assertFalse( $eligibility_checks[ PWCGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ BancontactGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ BlikGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ EPSGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ IDealGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ MyBankGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ P24Gateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ TrustlyGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ MultibancoGateway::ID ]() );
	}

	public function test_apm_not_eligible(): void {
		$service = $this->get_service( 'US', false );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks[ OXXO::ID ]() );
		$this->assertFalse( $eligibility_checks[ PWCGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ BancontactGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ BlikGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ EPSGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ IDealGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ MyBankGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ P24Gateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ TrustlyGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ MultibancoGateway::ID ]() );
	}

	public function test_pwc_disabled(): void {
		$service = $this->get_service();
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks[ PWCGateway::ID ]() );
	}

	public function test_pwc_enabled(): void {
		$service = $this->get_service('US', true, true, true, true, true, true, [ FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO => true ]  );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks[ PWCGateway::ID ]() );
	}

	public function test_pay_upon_invoice_germany(): void {
		$service = $this->get_service( 'DE' );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks[ PayUponInvoiceGateway::ID ]() );
	}

	public function test_pay_upon_invoice_not_germany(): void {
		$service = $this->get_service( 'US' );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks[ PayUponInvoiceGateway::ID ]() );
	}

	public function test_is_card_fields_supported_mexico(): void {
		$service = $this->get_service( 'MX' );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks[ CreditCardGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ CardButtonGateway::ID ]() );
	}

	public function test_is_card_fields_not_supported_mexico(): void {
		$service = $this->get_service( 'MX', true, true, true, false );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks[ CreditCardGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ CardButtonGateway::ID ]() );
	}

	public function test_is_card_fields_supported(): void {
		$service = $this->get_service();
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks[ CreditCardGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ CardButtonGateway::ID ]() );
	}

	public function test_is_card_fields_not_supported(): void {
		$service = $this->get_service( 'US', true, true, true, false );;
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks[ CreditCardGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ CardButtonGateway::ID ]() );
	}

	public function test_dcc_status_inactive(): void {
		$service = $this->get_service( 'US', true, false );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks[ CreditCardGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ AxoGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ CardButtonGateway::ID ]() );
	}

	public function test_axo_active(): void {
		$service = $this->get_service();
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks[ AxoGateway::ID ]() );
	}

	public function test_axo_inactive(): void {
		$service = $this->get_service( 'US', true, true, false );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks[ AxoGateway::ID ]() );
	}

	public function test_apple_and_google_active(): void {
		$service = $this->get_service( 'US' );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks[ ApplePayGateway::ID ]() );
		$this->assertTrue( $eligibility_checks[ GooglePayGateway::ID ]() );
	}

	public function test_apple_and_google_inactive(): void {
		$service = $this->get_service( 'US', true, true, true, true, false, false );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks[ ApplePayGateway::ID ]() );
		$this->assertFalse( $eligibility_checks[ GooglePayGateway::ID ]() );
	}

	public function test_venmo_us(): void {
		$service = $this->get_service( 'US' );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertTrue( $eligibility_checks['venmo']() );
	}

	public function test_venmo_not_us(): void {
		$service = $this->get_service( 'CA' );
		$eligibility_checks = $service->get_eligibility_checks();

		$this->assertFalse( $eligibility_checks['venmo']() );
	}


	private function expect_dcc_product_status( bool $status ): DCCProductStatus {
		$dcc_product_status = Mockery::mock( DCCProductStatus::class );
		$dcc_product_status->shouldReceive( 'is_active' )->andReturn( $status );

		return $dcc_product_status;
	}

	private function get_service(
		string $country_code = 'US',
		bool $apm_enabled = true,
		bool $dcc_product_status_enabled = true,
		bool $axo_enabled = true,
		bool $card_fields_enabled = true,
		bool $apple_pay_enabled = true,
		bool $google_pay_enabled = true,
		array $merchant_capabilities = array()
	): PaymentMethodsEligibilityService {
		$dcc_product_status = $this->expect_dcc_product_status( $dcc_product_status_enabled );

		return new PaymentMethodsEligibilityService(
			$country_code,
			$apm_enabled,
			$merchant_capabilities,
			$dcc_product_status,
			fn() => $axo_enabled,
			fn() => $card_fields_enabled,
			$apple_pay_enabled,
			$google_pay_enabled
		);
	}
}
