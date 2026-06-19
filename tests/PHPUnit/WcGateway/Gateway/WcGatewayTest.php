<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\CapturePayPalPayment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcPaymentTokens\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use Mockery;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class WcGatewayTest extends TestCase
{
	private $isAdmin = false;
	private $sessionHandler;
	private $fundingSource = null;

	private $funding_source_renderer;
	private $orderProcessor;
	private $settings;
	private $settingsProvider;
	private $refundProcessor;
	private $isConnected;
	private $transactionUrlProvider;
	private $subscriptionHelper;
	private $environment;
	private $logger;
	private $apiShopCountry;
	private $paymentTokensEndpoint;
	private $wcPaymentTokens;
	private $assetGetter;
	private $context;

	public function setUp(): void {
		parent::setUp();

		expect('is_admin')->andReturnUsing(function () {
			return $this->isAdmin;
		});
		when('wc_clean')->returnArg();

		$this->orderProcessor = Mockery::mock(OrderProcessor::class);
		$this->settings = Mockery::mock(Settings::class);
		$this->settingsProvider = Mockery::mock(SettingsProvider::class);
		$this->sessionHandler = Mockery::mock(SessionHandler::class);
		$this->refundProcessor = Mockery::mock(RefundProcessor::class);
		$this->isConnected = true;
		$this->transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
		$this->subscriptionHelper = Mockery::mock(SubscriptionHelper::class);
		$this->environment = Mockery::mock(Environment::class);
		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->settingsProvider->shouldReceive('paypal_gateway_title')->andReturn('PayPal');
		$this->settingsProvider->shouldReceive('paypal_gateway_description')->andReturn('Pay via PayPal.');
		$this->settingsProvider->shouldReceive('merchant_email')->andReturn('');
		$this->funding_source_renderer = new FundingSourceRenderer(
			$this->settingsProvider,
			['venmo' => 'Venmo', 'paylater' => 'Pay Later', 'blik' => 'BLIK']
		);
		$this->apiShopCountry = 'DE';
		$this->assetGetter = new AssetGetter('http://example.com', '/plugin/', 'module');

		$this->sessionHandler
			->shouldReceive('funding_source')
			->andReturnUsing(function () {
				return $this->fundingSource;
			});
		$order = Mockery::mock(Order::class);
		$order->shouldReceive('status')->andReturn(new OrderStatus(OrderStatus::APPROVED));
		$this->sessionHandler
			->shouldReceive('order')
			->andReturn($order);

		$this->settings->shouldReceive('has')->andReturnFalse();

		$this->logger->shouldReceive('info');
		$this->logger->shouldReceive('error');

		$this->paymentTokensEndpoint = Mockery::mock(PaymentTokensEndpoint::class);
		$this->wcPaymentTokens = Mockery::mock(WooCommercePaymentTokens::class);
		$this->context = Mockery::mock(Context::class);
	}

	private function createGateway()
	{
		return new PayPalGateway(
			$this->funding_source_renderer,
			$this->orderProcessor,
			$this->settingsProvider,
			$this->sessionHandler,
			$this->refundProcessor,
			$this->isConnected,
			$this->transactionUrlProvider,
			$this->subscriptionHelper,
			$this->environment,
			$this->logger,
			$this->apiShopCountry,
			static fn ($id) => 'checkoutnow=' . $id,
			'Pay via PayPal',
			$this->paymentTokensEndpoint,
			$this->wcPaymentTokens,
			$this->assetGetter,
			false,
			Mockery::mock(CapturePayPalPayment::class),
			Mockery::mock(OrderEndpoint::class),
			'WC-',
			$this->context
		);
	}

	private function createSpyGateway(): SpyablePayPalGateway
	{
		return new SpyablePayPalGateway(
			$this->funding_source_renderer,
			$this->orderProcessor,
			$this->settingsProvider,
			$this->sessionHandler,
			$this->refundProcessor,
			$this->isConnected,
			$this->transactionUrlProvider,
			$this->subscriptionHelper,
			$this->environment,
			$this->logger,
			$this->apiShopCountry,
			static fn ($id) => 'checkoutnow=' . $id,
			'Pay via PayPal',
			$this->paymentTokensEndpoint,
			$this->wcPaymentTokens,
			$this->assetGetter,
			false,
			Mockery::mock(CapturePayPalPayment::class),
			Mockery::mock(OrderEndpoint::class),
			'WC-',
			$this->context
		);
	}

	public function testProcessPaymentSuccess() {
        $orderId = 1;
        $wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_customer_id')->andReturn(1);
		$wcOrder->shouldReceive('get_meta')->andReturn('');
		$this->orderProcessor
            ->expects('process')
            ->andReturnUsing(
                function(\WC_Order $order) use ($wcOrder) : bool {
                    return $order === $wcOrder;
                }
            );
		$this->sessionHandler
	        ->shouldReceive('destroy_session_data');
		$this->subscriptionHelper
            ->shouldReceive('has_subscription')
            ->with($orderId)
            ->andReturn(true)
			->andReturn(false);
		$this->subscriptionHelper
            ->shouldReceive('is_subscription_change_payment')
            ->andReturn(true);

        $testee = $this->createGateway();

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);

        when('wc_get_checkout_url')
			->justReturn('test');

		$woocommerce = Mockery::mock(\WooCommerce::class);
		$cart = Mockery::mock(\WC_Cart::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->cart = $cart;
		$cart->shouldReceive('empty_cart');

		$session = Mockery::mock(\WC_Session::class);
		$woocommerce->session = $session;
		$session->shouldReceive('get');
		$session->shouldReceive('set');

        $result = $testee->process_payment($orderId);

        $this->assertIsArray($result);

        $this->assertEquals('success', $result['result']);
        $this->assertEquals($result['redirect'], $wcOrder);
    }

    public function testProcessPaymentOrderNotFound() {
        $orderId = 1;

	    $testee = $this->createGateway();

		$woocommerce = Mockery::mock(\WooCommerce::class);
		$session = Mockery::mock(\WC_Session::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->session = $session;
		$session->shouldReceive('set')->andReturn([]);

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn(false);

        $redirectUrl = 'http://example.com/checkout';

        when('wc_get_checkout_url')
			->justReturn($redirectUrl);

		$this->sessionHandler
			->shouldReceive('destroy_session_data');

        expect('wc_add_notice');

		$result = $testee->process_payment($orderId);

		$this->assertArrayHasKey('errorMessage', $result);
		unset($result['errorMessage']);

        $this->assertEquals(
        	[
        		'result' => 'failure',
				'redirect' => $redirectUrl,
			],
			$result
		);
    }


    public function testProcessPaymentFails() {
        $orderId = 1;
        $wcOrder = Mockery::mock(\WC_Order::class);
        $error = 'some-error';
		$this->orderProcessor
            ->expects('process')
            ->andThrow(new Exception($error));
		$this->subscriptionHelper->shouldReceive('has_subscription')->with($orderId)->andReturn(true);
		$this->subscriptionHelper->shouldReceive('is_subscription_change_payment')->andReturn(true);
        $wcOrder->shouldReceive('update_status')->andReturn(true);

        $testee = $this->createGateway();

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);
		$this->sessionHandler
			->shouldReceive('destroy_session_data');
        expect('wc_add_notice')
            ->with($error, 'error');

		$redirectUrl = 'http://example.com/checkout';

		when('wc_get_checkout_url')
			->justReturn($redirectUrl);
		when('is_checkout_pay_page')->justReturn(false);

		$woocommerce = Mockery::mock(\WooCommerce::class);
		when('WC')->justReturn($woocommerce);
		$session = Mockery::mock(\WC_Session::class);
		$woocommerce->session = $session;
		$session->shouldReceive('get');
		$session->shouldReceive('set');

		$result = $testee->process_payment($orderId);

		$this->assertArrayHasKey('errorMessage', $result);
		unset($result['errorMessage']);

		$this->assertEquals(
			[
				'result' => 'failure',
				'redirect' => $redirectUrl,
			],
			$result
		);
    }

	/**
	 * GIVEN a freshly-created order exists in WooCommerce (normal checkout flow)
	 *   AND the session flag `ppcp_delete_wc_order_on_payment_failure` is set to true
	 *   AND the current page is NOT the Pay-for-Order page
	 * WHEN capture validation throws an exception during process_payment()
	 * THEN the order is force-deleted from the database to clean up the transient checkout order
	 *   AND a failure result is returned to the caller
	 */
	public function testProcessPaymentFailureDeletesFreshOrderDuringCheckout(): void {
		$orderId = 1;
		$wcOrder = Mockery::mock( \WC_Order::class );
		$error   = 'capture-validation-error';

		$this->orderProcessor
			->expects( 'process' )
			->andThrow( new Exception( $error ) );
		$this->subscriptionHelper->shouldReceive( 'has_subscription' )
			->with( $orderId )
			->andReturn( false );
		$this->subscriptionHelper->shouldReceive( 'is_subscription_change_payment' )
			->andReturn( false );

		$wcOrder->shouldReceive( 'update_status' )->andReturn( true );

		// Normal checkout: the freshly-created order MUST be force-deleted.
		$wcOrder->shouldReceive( 'delete' )->once()->with( true );

		$testee = $this->createGateway();

		expect( 'wc_get_order' )->with( $orderId )->andReturn( $wcOrder );
		$this->sessionHandler->shouldReceive( 'destroy_session_data' );
		expect( 'wc_add_notice' )->with( $error, 'error' );

		when( 'wc_get_checkout_url' )->justReturn( 'http://example.com/checkout' );
		when( 'is_checkout_pay_page' )->justReturn( false );

		$woocommerce = Mockery::mock( \WooCommerce::class );
		$session     = Mockery::mock( \WC_Session::class );

		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );

		// The delete flag is true, so the deletion path is reached.
		$session->shouldReceive( 'get' )
			->with( 'ppcp_delete_wc_order_on_payment_failure' )
			->andReturn( true );
		$session->shouldReceive( 'get' )->andReturn( null );
		$session->shouldReceive( 'set' );

		$result = $testee->process_payment( $orderId );

		$this->assertSame( 'failure', $result['result'] );
	}

	/**
	 * GIVEN a pre-existing WooCommerce order opened on the Pay-for-Order page
	 *   AND the session flag `ppcp_delete_wc_order_on_payment_failure` is set to true
	 *   AND the current page IS the Pay-for-Order page (`is_checkout_pay_page()` returns true)
	 * WHEN capture validation throws an exception during process_payment()
	 * THEN the pre-existing order is NOT deleted — it is marked as failed so the customer can retry
	 *   AND a failure result is returned to the caller
	 */
	public function testProcessPaymentFailureKeepsOrderOnPayForOrderPage(): void {
		$orderId = 1;
		$wcOrder = Mockery::mock( \WC_Order::class );
		$error   = 'capture-validation-error';

		$this->orderProcessor
			->expects( 'process' )
			->andThrow( new Exception( $error ) );
		$this->subscriptionHelper->shouldReceive( 'has_subscription' )
			->with( $orderId )
			->andReturn( false );
		$this->subscriptionHelper->shouldReceive( 'is_subscription_change_payment' )
			->andReturn( false );

		$wcOrder->shouldReceive( 'update_status' )->andReturn( true );

		// Pay-for-Order page: the pre-existing order must NEVER be deleted.
		$wcOrder->shouldReceive( 'delete' )->never();

		$testee = $this->createGateway();

		expect( 'wc_get_order' )->with( $orderId )->andReturn( $wcOrder );
		$this->sessionHandler->shouldReceive( 'destroy_session_data' );
		expect( 'wc_add_notice' )->with( $error, 'error' );

		when( 'wc_get_checkout_url' )->justReturn( 'http://example.com/checkout' );
		when( 'is_checkout_pay_page' )->justReturn( true );

		$woocommerce = Mockery::mock( \WooCommerce::class );
		$session     = Mockery::mock( \WC_Session::class );

		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );

		// The delete flag is true; the guard on is_checkout_pay_page() should prevent the deletion.
		$session->shouldReceive( 'get' )
			->with( 'ppcp_delete_wc_order_on_payment_failure' )
			->andReturn( true );
		$session->shouldReceive( 'get' )->andReturn( null );
		$session->shouldReceive( 'set' );

		$result = $testee->process_payment( $orderId );

		$this->assertSame( 'failure', $result['result'] );
	}

    /**
     * @dataProvider dataForFundingSource
     */
    public function testFundingSource($fundingSource, $title, $description)
    {
		$this->fundingSource = $fundingSource;

		$testee = $this->createGateway();

		self::assertEquals( $title, $testee->title );
		self::assertEquals( $description, $testee->description );
	}

	public function dataForTestCaptureAuthorizedPaymentNoActionableFailures(): array {
		return [
			'inaccessible' => [
				AuthorizedPaymentsProcessor::INACCESSIBLE,
				AuthorizeOrderActionNotice::NO_INFO,
			],
			'not_found'    => [
				AuthorizedPaymentsProcessor::NOT_FOUND,
				AuthorizeOrderActionNotice::NOT_FOUND,
			],
			'not_mapped'   => [
				'some-other-failure',
				AuthorizeOrderActionNotice::FAILED,
			],
		];
	}

	public function dataForFundingSource(): array {
		return [
			[ null, 'PayPal', 'Pay via PayPal.' ],
			[ 'venmo', 'Venmo', 'Pay via Venmo.' ],
			[ 'paylater', 'Pay Later', 'Pay via Pay Later.' ],
			[ 'blik', 'BLIK (via PayPal)', 'Pay via BLIK.' ],
			[ 'qwerty', 'PayPal', 'Pay via PayPal.' ],
		];
	}

	/**
	 * GIVEN a customer has already initiated PayPal payment (continuation flow)
	 * WHEN payment_fields() is rendered on the checkout page
	 * THEN the saved-method UI (tokenization_script + saved_payment_methods) must NOT be rendered
	 *   AND the payment fields render without the vault UI to avoid double-render during
	 *   continuation
	 */
	public function test_payment_fields_during_continuation_flow_suppresses_saved_method_ui(): void {
		$this->context->shouldReceive( 'is_paypal_continuation' )->andReturn( true );
		$this->settingsProvider->shouldReceive( 'save_paypal_and_venmo' )->andReturn( true );

		when( 'is_checkout' )->justReturn( true );
		when( 'wp_kses_post' )->returnArg();
		when( 'wpautop' )->returnArg();
		when( 'wptexturize' )->returnArg();
		when( 'apply_filters' )->returnArg( 2 );

		$gateway = $this->createSpyGateway();
		$gateway->set_test_supports( [ 'tokenization' ] );

		$gateway->payment_fields();

		$this->assertSame( 0, $gateway->tokenization_script_call_count, 'tokenization_script() must not be called during PayPal continuation flow' );
		$this->assertSame( 0, $gateway->saved_payment_methods_call_count, 'saved_payment_methods() must not be called during PayPal continuation flow' );
	}

	/**
	 * GIVEN a normal checkout (no PayPal continuation in progress) with vaulting enabled
	 * WHEN payment_fields() is rendered on the checkout page
	 * THEN the saved-method UI (tokenization_script + saved_payment_methods) IS rendered
	 */
	public function test_payment_fields_on_normal_checkout_renders_saved_method_ui(): void {
		$this->context->shouldReceive( 'is_paypal_continuation' )->andReturn( false );
		$this->settingsProvider->shouldReceive( 'save_paypal_and_venmo' )->andReturn( true );

		when( 'is_checkout' )->justReturn( true );
		when( 'wp_kses_post' )->returnArg();
		when( 'wpautop' )->returnArg();
		when( 'wptexturize' )->returnArg();
		when( 'apply_filters' )->returnArg( 2 );

		$gateway = $this->createSpyGateway();
		$gateway->set_test_supports( [ 'tokenization' ] );

		$gateway->payment_fields();

		$this->assertSame( 1, $gateway->tokenization_script_call_count, 'tokenization_script() must be called on a normal checkout with vaulting enabled' );
		$this->assertSame( 1, $gateway->saved_payment_methods_call_count, 'saved_payment_methods() must be called on a normal checkout with vaulting enabled' );
	}

	/**
	 * GIVEN vaulting is disabled (save_paypal_and_venmo returns false)
	 * WHEN payment_fields() is rendered on the checkout page
	 * THEN the saved-method UI is NOT rendered regardless of continuation state
	 */
	public function test_payment_fields_with_vaulting_disabled_suppresses_saved_method_ui(): void {
		$this->context->shouldReceive( 'is_paypal_continuation' )->andReturn( false );
		$this->settingsProvider->shouldReceive( 'save_paypal_and_venmo' )->andReturn( false );

		when( 'is_checkout' )->justReturn( true );
		when( 'wp_kses_post' )->returnArg();
		when( 'wpautop' )->returnArg();
		when( 'wptexturize' )->returnArg();
		when( 'apply_filters' )->returnArg( 2 );

		$gateway = $this->createSpyGateway();
		$gateway->set_test_supports( [ 'tokenization' ] );

		$gateway->payment_fields();

		$this->assertSame( 0, $gateway->tokenization_script_call_count, 'tokenization_script() must not be called when vaulting is disabled' );
		$this->assertSame( 0, $gateway->saved_payment_methods_call_count, 'saved_payment_methods() must not be called when vaulting is disabled' );
	}
}

/**
 * Testable subclass that replaces the WC parent's side-effectful
 * saved-payment-method methods with simple call-count spies.
 */
class SpyablePayPalGateway extends PayPalGateway {
	public int $saved_payment_methods_call_count = 0;
	public int $tokenization_script_call_count = 0;

	/** @param string[] $supports */
	public function set_test_supports( array $supports ): void {
		$this->supports = $supports;
	}

	public function supports( $feature ): bool {
		return in_array( $feature, $this->supports, true );
	}

	public function saved_payment_methods(): void {
		$this->saved_payment_methods_call_count ++;
	}

	public function tokenization_script(): void {
		$this->tokenization_script_call_count ++;
	}

	public function get_description(): string {
		return '';
	}
}
