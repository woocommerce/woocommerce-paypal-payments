<?php

/**
 * Payment Methods eligibility service.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

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
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
/**
 * Manages eligibility checks for various PayPal Commerce features.
 */
class PaymentMethodsEligibilityService
{
    /**
     * PayPal Country (or Woo Country if not onboarded)
     */
    private string $merchant_country;
    /**
     * If alternative payment methods are eligible.
     */
    private bool $is_apm_eligible;
    /**
     * Array of Merchant capabilities (true: enabled / false: disabled)
     */
    private array $merchant_capabilities;
    /**
     * ACDC Seller status.
     */
    private DCCProductStatus $dcc_product_status;
    /**
     * Whether Axo is eligible.
     *
     * @var callable
     */
    private $axo_eligible;
    /**
     * Whether Card Fields is eligible.
     *
     * @var callable
     */
    private $card_fields_eligible;
    /**
     * Whether Apple Pay is available
     */
    private bool $apple_pay_available;
    /**
     * Whether Google Pay is available
     */
    private bool $google_pay_available;
    public function __construct(string $merchant_country, bool $is_apm_eligible, array $merchant_capabilities, DCCProductStatus $dcc_product_status, callable $axo_eligible, callable $card_fields_eligible, bool $apple_pay_available, bool $google_pay_available)
    {
        $this->merchant_country = $merchant_country;
        $this->is_apm_eligible = $is_apm_eligible;
        $this->merchant_capabilities = $merchant_capabilities;
        $this->dcc_product_status = $dcc_product_status;
        $this->axo_eligible = $axo_eligible;
        $this->card_fields_eligible = $card_fields_eligible;
        $this->apple_pay_available = $apple_pay_available;
        $this->google_pay_available = $google_pay_available;
    }
    /**
     * Returns all eligibility checks as callables.
     *
     * @return array<string, callable>
     */
    public function get_eligibility_checks(): array
    {
        return array(BancontactGateway::ID => fn() => !$this->is_mexico_merchant() && $this->is_apm_eligible, BlikGateway::ID => fn() => !$this->is_mexico_merchant() && $this->is_apm_eligible, EPSGateway::ID => fn() => !$this->is_mexico_merchant() && $this->is_apm_eligible, IDealGateway::ID => fn() => !$this->is_mexico_merchant() && $this->is_apm_eligible, MyBankGateway::ID => fn() => !$this->is_mexico_merchant() && $this->is_apm_eligible, P24Gateway::ID => fn() => !$this->is_mexico_merchant() && $this->is_apm_eligible, TrustlyGateway::ID => fn() => !$this->is_mexico_merchant() && $this->is_apm_eligible, MultibancoGateway::ID => fn() => !$this->is_mexico_merchant() && $this->is_apm_eligible, OXXO::ID => fn() => $this->is_mexico_merchant() && $this->is_apm_eligible, PWCGateway::ID => fn() => $this->has_pwc_capability() && $this->is_apm_eligible, PayUponInvoiceGateway::ID => fn() => $this->merchant_country === 'DE', CreditCardGateway::ID => fn() => $this->is_mexico_merchant() || $this->is_card_fields_supported(), CardButtonGateway::ID => fn() => $this->is_mexico_merchant() || !$this->is_card_fields_supported(), GooglePayGateway::ID => fn() => $this->google_pay_available, ApplePayGateway::ID => fn() => $this->apple_pay_available, AxoGateway::ID => fn() => $this->dcc_product_status->is_active() && call_user_func($this->axo_eligible), 'venmo' => fn() => $this->merchant_country === 'US');
    }
    /**
     * Whether merchant country is mexico.
     *
     * @return bool
     */
    private function is_mexico_merchant(): bool
    {
        return $this->merchant_country === 'MX';
    }
    /**
     * Whether Card Fields is supported. It requires also ACDC to be supported.
     *
     * @return bool
     */
    private function is_card_fields_supported(): bool
    {
        return $this->dcc_product_status->is_active() && call_user_func($this->card_fields_eligible);
    }
    /**
     * Whether Pay With Crypto capability is enabled.
     *
     * @return bool
     */
    private function has_pwc_capability(): bool
    {
        return $this->merchant_capabilities[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO] ?? \false;
    }
}
