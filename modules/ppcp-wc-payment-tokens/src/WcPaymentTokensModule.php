<?php

/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\WcPaymentTokens
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcPaymentTokens;

use RuntimeException;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class WcPaymentTokensModule
 *
 * @psalm-suppress MissingConstructor
 */
class WcPaymentTokensModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * Session Handler
     *
     * @var SessionHandler
     */
    protected SessionHandler $session_handler;
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * {@inheritDoc}
     *
     * @param ContainerInterface $container A services container instance.
     * @throws NotFoundException When service could not be found.
     */
    public function run(ContainerInterface $container): bool
    {
        add_filter(
            'woocommerce_payment_token_class',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($type) {
                if ($type === 'WC_Payment_Token_PayPal') {
                    return \WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenPayPal::class;
                }
                if ($type === 'WC_Payment_Token_Venmo') {
                    return \WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenVenmo::class;
                }
                if ($type === 'WC_Payment_Token_ApplePay') {
                    return \WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenApplePay::class;
                }
                return $type;
            }
        );
        $this->session_handler = $container->get('session.handler');
        add_filter(
            'woocommerce_get_customer_payment_tokens',
            /**
             * Filter available payment tokens depending on context.
             *
             * @psalm-suppress MissingClosureParamType
             * @psalm-suppress MissingClosureReturnType
             */
            function ($tokens) use ($container) {
                if (!is_array($tokens)) {
                    return $tokens;
                }
                //phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (isset($_GET['change_payment_method']) && is_wc_endpoint_url('order-pay')) {
                    return $tokens;
                }
                $is_post = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
                // Exclude ApplePay tokens from payment pages.
                if ((is_checkout() || is_cart() || is_product()) && !$is_post) {
                    foreach ($tokens as $index => $token) {
                        if ($token instanceof \WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenApplePay) {
                            unset($tokens[$index]);
                        }
                    }
                }
                $context = $container->get('button.helper.context');
                if (is_checkout() && !$is_post && $context->is_paypal_continuation()) {
                    foreach ($tokens as $index => $token) {
                        unset($tokens[$index]);
                    }
                }
                return $tokens;
            }
        );
        add_filter(
            'woocommerce_payment_methods_list_item',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($item, $payment_token) {
                if (!is_array($item) || !$payment_token instanceof WC_Payment_Token) {
                    return $item;
                }
                if ($payment_token instanceof \WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenPayPal) {
                    $item['method']['brand'] = 'PayPal / ' . $payment_token->get_email();
                    return $item;
                }
                if ($payment_token instanceof \WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenVenmo) {
                    $item['method']['brand'] = 'Venmo / ' . $payment_token->get_email();
                    return $item;
                }
                if ($payment_token instanceof \WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenApplePay) {
                    $item['method']['brand'] = 'ApplePay #' . (string) $payment_token->get_id();
                    return $item;
                }
                return $item;
            },
            10,
            2
        );
        add_action('wp', function () use ($container) {
            global $wp;
            if (!isset($wp->query_vars['delete-payment-method'])) {
                return;
            }
            $token_id = absint($wp->query_vars['delete-payment-method']);
            $token = WC_Payment_Tokens::get($token_id);
            if (is_null($token) || $token->get_gateway_id() !== PayPalGateway::ID && $token->get_gateway_id() !== CreditCardGateway::ID) {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification
            $wpnonce = wc_clean(wp_unslash($_REQUEST['_wpnonce'] ?? ''));
            $token_id_string = (string) $token_id;
            $action = 'delete-payment-method-' . $token_id_string;
            if ($token->get_user_id() !== get_current_user_id() || !isset($wpnonce) || !is_string($wpnonce) || wp_verify_nonce($wpnonce, $action) === \false) {
                wc_add_notice(__('Invalid payment method.', 'woocommerce-paypal-payments'), 'error');
                wp_safe_redirect(wc_get_account_endpoint_url('payment-methods'));
                exit;
            }
            try {
                do_action('woocommerce_paypal_payments_before_delete_payment_token', $token->get_token());
                $payment_tokens_endpoint = $container->get('api.endpoint.payment-tokens');
                $payment_tokens_endpoint->delete($token->get_token());
            } catch (RuntimeException $exception) {
                wc_add_notice(__('Could not delete payment token. ', 'woocommerce-paypal-payments') . $exception->getMessage(), 'error');
                return;
            }
        });
        add_filter(
            'woocommerce_available_payment_gateways',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($methods) {
                global $wp;
                if (!is_array($methods)) {
                    return $methods;
                }
                if (isset($wp->query_vars['add-payment-method']) && apply_filters('woocommerce_paypal_payments_disable_add_payment_method', \true)) {
                    unset($methods[PayPalGateway::ID]);
                }
                return $methods;
            }
        );
        return \true;
    }
}
