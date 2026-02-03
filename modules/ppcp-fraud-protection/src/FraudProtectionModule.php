<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\FraudProtection;

use WC_Order;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\Recaptcha;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\RecaptchaIntegration;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WP_Error;
class FraudProtectionModule implements ServiceModule, ExtendingModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    public function run(ContainerInterface $container): bool
    {
        $this->init_recaptcha($container);
        return \true;
    }
    protected function init_recaptcha(ContainerInterface $container): void
    {
        add_filter(
            'woocommerce_integrations',
            /**
             * @param array $integrations
             * @returns array
             * @psalm-suppress MissingClosureParamType
             * @psalm-suppress MissingClosureReturnType
             */
            static function ($integrations) use ($container) {
                // WC always creates a new instance here.
                $integrations[] = RecaptchaIntegration::class;
                return $integrations;
            }
        );
        add_filter('woocommerce_generate_ppcp_recaptcha_log_html', function () use ($container): string {
            $recaptcha = $container->get('fraud-protection.recaptcha');
            assert($recaptcha instanceof Recaptcha);
            return $recaptcha->render_settings_page_log();
        });
        add_action('wp_enqueue_scripts', static function () use ($container): void {
            $recaptcha = $container->get('fraud-protection.recaptcha');
            assert($recaptcha instanceof Recaptcha);
            $recaptcha->enqueue_scripts();
        });
        foreach (array('woocommerce_review_order_before_submit' => 10, 'woocommerce_pay_order_before_submit' => 10, 'woocommerce_after_cart_totals' => 10, 'woocommerce_single_product_summary' => 32) as $hook => $priority) {
            add_action($hook, static function () use ($container): void {
                $recaptcha = $container->get('fraud-protection.recaptcha');
                assert($recaptcha instanceof Recaptcha);
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $recaptcha->render_v2_container();
            }, $priority);
        }
        foreach (array('render_block_woocommerce/checkout-express-payment-block', 'render_block_woocommerce/proceed-to-checkout-block') as $filter) {
            add_filter(
                $filter,
                /**
                 * @param string $block_html
                 * @returns string
                 * @psalm-suppress MissingClosureParamType
                 * @psalm-suppress MissingClosureReturnType
                 */
                static function (string $block_html) use ($container) {
                    $recaptcha = $container->get('fraud-protection.recaptcha');
                    assert($recaptcha instanceof Recaptcha);
                    return $block_html . $recaptcha->render_v2_container();
                }
            );
        }
        add_action('woocommerce_paypal_payments_create_order_request_started', static function (array $data) use ($container): void {
            $recaptcha = $container->get('fraud-protection.recaptcha');
            assert($recaptcha instanceof Recaptcha);
            $recaptcha->intercept_paypal_ajax($data);
        });
        add_action('woocommerce_checkout_process', static function () use ($container): void {
            $recaptcha = $container->get('fraud-protection.recaptcha');
            assert($recaptcha instanceof Recaptcha);
            $recaptcha->validate_classic_checkout();
        });
        add_action('woocommerce_blocks_loaded', function (): void {
            $this->register_recaptcha_blocks_extension();
        });
        add_filter(
            'rest_authentication_errors',
            /**
             * @param WP_Error|null|true $errors
             * @return WP_Error|null|true WP_Error
             * @psalm-suppress MissingClosureParamType
             * @psalm-suppress MissingClosureReturnType
             */
            static function ($errors) use ($container) {
                $recaptcha = $container->get('fraud-protection.recaptcha');
                assert($recaptcha instanceof Recaptcha);
                return $recaptcha->validate_blocks_request($errors);
            },
            99
        );
        add_action(
            'woocommerce_new_order',
            /**
             * @param int $order_id
             * @param WC_Order $order
             * @psalm-suppress MissingClosureParamType
             * @psalm-suppress MissingClosureReturnType
             */
            static function ($order_id, $order) use ($container): void {
                $recaptcha = $container->get('fraud-protection.recaptcha');
                assert($recaptcha instanceof Recaptcha);
                $recaptcha->add_result_meta($order);
            },
            10,
            2
        );
        add_action('add_meta_boxes', static function () use ($container): void {
            $recaptcha = $container->get('fraud-protection.recaptcha');
            assert($recaptcha instanceof Recaptcha);
            $recaptcha->add_metabox();
        });
    }
    private function register_recaptcha_blocks_extension(): void
    {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        woocommerce_store_api_register_endpoint_data(array('endpoint' => 'checkout', 'namespace' => 'ppcp_recaptcha', 'schema_callback' => static function (): array {
            return array('token' => array('description' => 'reCAPTCHA token', 'type' => 'string', 'readonly' => \false), 'version' => array('description' => 'reCAPTCHA version', 'type' => 'string', 'readonly' => \false));
        }));
    }
}
