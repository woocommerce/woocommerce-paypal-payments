<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration;

use WC_Order;
use WC_Order_Item_Product;
use WC_Payment_Token_CC;
use WC_Subscription;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\Helper\RedirectorStub;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\Tests\Integration\Traits\CreateTestOrders;
use WooCommerce\PayPalCommerce\Tests\Integration\Traits\CreateTestProducts;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

class IntegrationMockedTestCase extends TestCase
{
    use MockeryPHPUnitIntegration, CreateTestOrders, CreateTestProducts;

    public function setUp(): void
    {
        parent::setUp();
        $this->customer_id = $this->createCustomerIfNotExists();
        $this->createTestProducts();
        $this->createTestCoupons();
    }

    public function tearDown(): void
    {
        // This cleans up everything created during tests
        //$this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * @param array<string, callable> $overriddenServices
     * @return ContainerInterface
     */
    protected function bootstrapModule(array $overriddenServices = []): ContainerInterface
    {
        $overriddenServices = array_merge([
            'http.redirector' => function () {
                return new RedirectorStub();
            }
        ], $overriddenServices);


        $module = new class ($overriddenServices) implements ServiceModule, ExecutableModule {
            use ModuleClassNameIdTrait;

            public function __construct(array $services)
            {
                $this->services = $services;
            }

            public function services(): array
            {
                return $this->services;
            }

            public function run(ContainerInterface $c): bool
            {
                return true;
            }
        };

        $rootDir = ROOT_DIR;
        $bootstrap = require("$rootDir/bootstrap.php");
        $appContainer = $bootstrap($rootDir, [], [$module]);

        PPCP::init($appContainer);

        return $appContainer;
    }

    public function createCustomerIfNotExists(int $customer_id = 1): int
    {
        $customer = new \WC_Customer($customer_id);
        if (empty($customer->get_email())) {
            $customer->set_email('customer' . $customer_id . '@example.com');
            $customer->set_first_name('John');
            $customer->set_last_name('Doe');
            $customer->save();
        }
        return $customer->get_id();
    }

    /**
     * Creates a payment token for a customer.
     *
     * @param int $customer_id The customer ID.
     * @return WC_Payment_Token_CC The created payment token.
     * @throws \Exception
     */
    public function createAPaymentTokenForTheCustomer(int $customer_id = 1, $gateway_id = 'ppcp-gateway'): WC_Payment_Token_CC
    {
        $this->createCustomerIfNotExists($customer_id);

        $token = new WC_Payment_Token_CC();
        $token->set_token('test_token_' . uniqid()); // Unique token ID
        $token->set_gateway_id($gateway_id);
        $token->set_user_id($customer_id);

        // These fields are required for WC_Payment_Token_CC
        $token->set_card_type('visa'); // lowercase is often expected
        $token->set_last4('1234');
        $token->set_expiry_month('12');
        $token->set_expiry_year('2030'); // Missing expiry year in your original code

        $result = $token->save();

        if (!$result || is_wp_error($result)) {
            throw new \Exception('Failed to save payment token: ' .
                (is_wp_error($result) ? $result->get_error_message() : 'Unknown error'));
        }

        $saved_token = \WC_Payment_Tokens::get($token->get_id());
        if (!$saved_token || $saved_token->get_id() !== $token->get_id()) {
            throw new \Exception('Token was not saved correctly');
        }

        return $token;
    }

    /**
     * Helper method to create a subscription for testing.
     *
     * @param int $customer_id The customer ID
     * @param string $payment_method The payment method
     * @param string $sku
     * @return WC_Subscription
     * @throws \WC_Data_Exception
     */
    public function createSubscription(int $customer_id = 1, string $payment_method = 'ppcp-gateway', $sku = 'DUMMY SUB SKU'): WC_Subscription
    {
        $product_id = wc_get_product_id_by_sku($sku);

        $order = $this->getConfiguredOrder(
            $this->customer_id,
            $payment_method,
            ['subscription']
        );
        $subscription = new WC_Subscription();
        $subscription->set_customer_id($customer_id);
        $subscription->set_payment_method($payment_method);
        $subscription->set_status('active');
        $subscription->set_parent_id($order->get_id());
        $subscription->set_billing_period('month');
        $subscription->set_billing_interval(1);

        // Add a product to the subscription
        $subscription_item = new WC_Order_Item_Product();
        $subscription_item->set_props([
            'product_id' => $product_id,
            'quantity' => 1,
            'subtotal' => 10,
            'total' => 10,
        ]);
        $subscription->add_item($subscription_item);
        $subscription->set_date_created(current_time('mysql'));
        $subscription->set_start_date(current_time('mysql'));
        $subscription->set_next_payment_date(date('Y-m-d H:i:s', strtotime('+1 month', current_time('timestamp'))));
        $subscription->save();

        return $subscription;
    }

    /**
     * Creates a renewal order for testing
     *
     * @param int $customer_id
     * @param string $gateway_id
     * @param int $subscription_id
     * @return WC_Order
     */
    protected function createRenewalOrder(int $customer_id, string $gateway_id, int $subscription_id): WC_Order
    {
        $renewal_order = $this->getConfiguredOrder(
            $customer_id,
            $gateway_id,
            ['subscription'],
            [],
            false
        );
        $renewal_order->update_meta_data('_subscription_renewal', $subscription_id);
        $renewal_order->update_meta_data('_subscription_renewal', $subscription_id);
        $renewal_order->save();

        return $renewal_order;
    }

    /**
     * Mocks the OrderEndpoint to return a successful/failed order.
     *
     * @param string $intent The order intent (CAPTURE or AUTHORIZE)
     * @param bool $success Whether the order was successful
     * @return object The mocked OrderEndpoint
     */
    public function mockOrderEndpoint(string $intent = 'CAPTURE', bool $order_success = true, bool $capture_success = true): object
    {
        $order_endpoint = \Mockery::mock(OrderEndpoint::class);
        $order = \Mockery::mock(Order::class)->shouldIgnoreMissing();

        $order->shouldReceive('id')->andReturn('TEST-ORDER-' . uniqid());
        $order->shouldReceive('intent')->andReturn($intent);

        $order_status = \Mockery::mock(OrderStatus::class);
        $order_status->shouldReceive('is')->andReturn($order_success);
        $order_status->shouldReceive('name')->andReturn($order_success ? 'COMPLETED' : 'FAILED');
        $order->shouldReceive('status')->andReturn($order_status);
        $card_properties = new \stdClass();
        $card_properties->brand = 'VISA';
        $card_properties->last_digits = '1234';
        $card_properties->expiry = '2026-12';
        $payment_source = \Mockery::mock(PaymentSource::class);
        $payment_source->shouldReceive('name')->andReturn('card');
        $payment_source->shouldReceive('properties')->andReturn($card_properties);
        $order->shouldReceive('payment_source')->andReturn($payment_source);

        $purchase_unit = \Mockery::mock(PurchaseUnit::class)->shouldIgnoreMissing();
        $payments = \Mockery::mock(Payments::class)->shouldIgnoreMissing();
        $capture = \Mockery::mock(Capture::class)->shouldIgnoreMissing();

        $capture->shouldReceive('id')->andReturn('TEST-CAPTURE-' . uniqid());
        $capture_status = \Mockery::mock(CaptureStatus::class)->shouldIgnoreMissing();

        $capture_status->shouldReceive('name')->andReturn($capture_success ? 'COMPLETED' : 'DECLINED');
        $capture_status->shouldReceive('details')->andReturn(null);
        $capture->shouldReceive('status')->andReturn($capture_status);

        // Mock authorizations for AUTHORIZE intent
        if ($intent === 'AUTHORIZE') {
            $authorization = \Mockery::mock(\WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization::class)->shouldIgnoreMissing();

            $authorization->shouldReceive('id')->andReturn('TEST-AUTH-' . uniqid());
            $auth_status = \Mockery::mock(\WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus::class)->shouldIgnoreMissing();

            $auth_status->shouldReceive('name')->andReturn($capture_success ? 'CREATED' : 'DENIED');
            $auth_status->shouldReceive('is')->andReturn($capture_success);
            $authorization->shouldReceive('status')->andReturn($auth_status);
            $payments->shouldReceive('authorizations')->andReturn([$authorization]);
            $payments->shouldReceive('captures')->andReturn([]);
        } else {
            // For CAPTURE intent, set up captures but no authorizations
            $payments->shouldReceive('captures')->andReturn([$capture]);
            $payments->shouldReceive('authorizations')->andReturn([]);
        }

        $purchase_unit->shouldReceive('payments')->andReturn($payments);
        $order->shouldReceive('purchase_units')->andReturn([$purchase_unit]);

        // Set up the order endpoint methods
        $order_endpoint->shouldReceive('create')->andReturn($order);
        if ($intent === 'AUTHORIZE') {
            $order_endpoint->shouldReceive('authorize')->andReturn($order);
        } else {
            $order_endpoint->shouldReceive('capture')->andReturn($order);
        }
        $order_endpoint->shouldReceive('order')->andReturn($order);
        $order_endpoint->shouldReceive('patch_order_with')->andReturn($order);
        return $order_endpoint;
    }
}
