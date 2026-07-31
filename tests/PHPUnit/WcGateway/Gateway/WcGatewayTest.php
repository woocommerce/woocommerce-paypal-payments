<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
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
	private $sessionOrder;
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
	private $capturePayPalPayment;

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
		$this->sessionOrder = Mockery::mock(Order::class);
		$this->sessionOrder->shouldReceive('status')->andReturn(new OrderStatus(OrderStatus::APPROVED));
		$this->sessionHandler
			->shouldReceive('order')
			->andReturn($this->sessionOrder)
			->byDefault();

		$this->settings->shouldReceive('has')->andReturnFalse();

		$this->logger->shouldReceive('info');
		$this->logger->shouldReceive('error');

		$this->paymentTokensEndpoint = Mockery::mock(PaymentTokensEndpoint::class);
		$this->wcPaymentTokens = Mockery::mock(WooCommercePaymentTokens::class);
		$this->context = Mockery::mock(Context::class);
		$this->capturePayPalPayment = Mockery::mock(CapturePayPalPayment::class);
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
			$this->capturePayPalPayment,
			Mockery::mock(OrderEndpoint::class),
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
			$this->capturePayPalPayment,
			Mockery::mock(OrderEndpoint::class),
			$this->context
		);
	}

	private function createReturnUrlStubGateway(): PayPalGatewayReturnUrlStub
	{
		return new PayPalGatewayReturnUrlStub(
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
			$this->capturePayPalPayment,
			Mockery::mock(OrderEndpoint::class),
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

		$this->orderProcessor->allows( 'process' )->andThrow( new Exception( $error ) );

		$wcOrder->allows( 'update_status' )->andReturn( true );

		// Normal checkout: the freshly-created order MUST be force-deleted.
		$wcOrder->shouldReceive( 'delete' )->once()->with( true );

		$testee = $this->createGateway();

		when( 'wc_get_order' )->justReturn( $wcOrder );
		$this->sessionHandler->allows( 'destroy_session_data' );
		when( 'wc_add_notice' )->justReturn( null );
		when( 'wc_get_checkout_url' )->justReturn( 'http://example.com/checkout' );
		when( 'is_checkout_pay_page' )->justReturn( false );

		$woocommerce = Mockery::mock( \WooCommerce::class );
		$session     = Mockery::mock( \WC_Session::class );

		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );

		// The delete flag is true, so the deletion path is reached.
		$session->allows( 'get' )
			->with( 'ppcp_delete_wc_order_on_payment_failure' )
			->andReturn( true );
		$session->allows( 'get' )->andReturn( null );
		$session->allows( 'set' );

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

		$this->orderProcessor->allows( 'process' )->andThrow( new Exception( $error ) );

		$wcOrder->allows( 'update_status' )->andReturn( true );

		// Pay-for-Order page: the pre-existing order must NEVER be deleted.
		$wcOrder->shouldReceive( 'delete' )->never();

		$testee = $this->createGateway();

		when( 'wc_get_order' )->justReturn( $wcOrder );
		$this->sessionHandler->allows( 'destroy_session_data' );
		when( 'wc_add_notice' )->justReturn( null );
		when( 'wc_get_checkout_url' )->justReturn( 'http://example.com/checkout' );
		when( 'is_checkout_pay_page' )->justReturn( true );

		$woocommerce = Mockery::mock( \WooCommerce::class );
		$session     = Mockery::mock( \WC_Session::class );

		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );

		// The delete flag is true; the guard on is_checkout_pay_page() should prevent the deletion.
		$session->allows( 'get' )
			->with( 'ppcp_delete_wc_order_on_payment_failure' )
			->andReturn( true );
		$session->allows( 'get' )->andReturn( null );
		$session->allows( 'set' );

		$result = $testee->process_payment( $orderId );

		$this->assertSame( 'failure', $result['result'] );
	}

	/**
	 * GIVEN a capture attempt throws a PayPalApiException carrying a PAYER_ACTION_REQUIRED detail
	 *   AND the retry attempt count is still below the give-up threshold
	 *   AND a PayPal order is still present in the session to retry against
	 * WHEN process_payment() handles the exception
	 * THEN the order is NOT marked failed, since the payer action/retry is still recoverable
	 *   AND an order note records the reason instead
	 *   AND the customer is redirected back to PayPal to complete the action
	 */
	public function test_process_payment_keeps_order_pending_on_recoverable_payer_action_required(): void {
		$orderId = 1;
		$wcOrder = Mockery::mock( \WC_Order::class );

		$response          = new \stdClass();
		$response->details = array( (object) array( 'issue' => 'PAYER_ACTION_REQUIRED', 'description' => 'Additional action needed.' ) );
		$exception         = new PayPalApiException( $response, 422 );

		$this->orderProcessor->expects( 'process' )->andThrow( $exception );

		$wcOrder->shouldNotReceive( 'update_status' );
		$wcOrder->shouldReceive( 'add_order_note' )->once();

		$this->sessionHandler->shouldReceive( 'increment_insufficient_funding_tries' )->once();
		$this->sessionHandler->shouldReceive( 'insufficient_funding_tries' )->andReturn( 1 );
		$this->sessionHandler->shouldNotReceive( 'destroy_session_data' );
		$this->sessionOrder->shouldReceive( 'id' )->andReturn( 'PAYPAL-ORDER-ID' );

		$testee = $this->createGateway();

		expect( 'wc_get_order' )
			->with( $orderId )
			->andReturn( $wcOrder );

		$result = $testee->process_payment( $orderId );

		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( 'checkoutnow=PAYPAL-ORDER-ID', $result['redirect'] );
	}

	/**
	 * GIVEN a capture attempt throws a PayPalApiException carrying a PAYER_ACTION_REQUIRED detail
	 *   AND the retry attempt count has already reached the give-up threshold (3 attempts)
	 * WHEN process_payment() handles the exception
	 * THEN the order IS marked failed, since the retry is now considered exhausted
	 */
	public function test_process_payment_fails_order_when_payer_action_retries_exhausted(): void {
		$orderId = 1;
		$wcOrder = Mockery::mock( \WC_Order::class );

		$response          = new \stdClass();
		$response->details = array( (object) array( 'issue' => 'PAYER_ACTION_REQUIRED', 'description' => 'Additional action needed.' ) );
		$exception         = new PayPalApiException( $response, 422 );

		$this->orderProcessor->expects( 'process' )->andThrow( $exception );

		$wcOrder->shouldReceive( 'update_status' )->once()->with( 'failed', Mockery::type( 'string' ) );

		$this->sessionHandler->shouldReceive( 'increment_insufficient_funding_tries' )->once();
		$this->sessionHandler->shouldReceive( 'insufficient_funding_tries' )->andReturn( 3 );
		$this->sessionHandler->shouldReceive( 'destroy_session_data' )->once();

		$testee = $this->createGateway();

		expect( 'wc_get_order' )
			->with( $orderId )
			->andReturn( $wcOrder );
		when( 'wc_add_notice' )->justReturn( null );
		when( 'wc_get_checkout_url' )->justReturn( 'http://example.com/checkout' );

		$woocommerce = Mockery::mock( \WooCommerce::class );
		$session     = Mockery::mock( \WC_Session::class );
		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );
		$session->shouldReceive( 'set' );

		$result = $testee->process_payment( $orderId );

		$this->assertEquals( 'failure', $result['result'] );
	}

	/**
	 * GIVEN a capture attempt throws a PayPalApiException carrying a PAYER_ACTION_REQUIRED detail
	 *   AND no PayPal order remains in the session to retry against (session expired)
	 * WHEN process_payment() handles the exception
	 * THEN the order IS marked failed, since there is no way left to recover this attempt
	 */
	public function test_process_payment_fails_order_when_payer_action_session_order_missing(): void {
		$orderId = 1;
		$wcOrder = Mockery::mock( \WC_Order::class );

		$response          = new \stdClass();
		$response->details = array( (object) array( 'issue' => 'PAYER_ACTION_REQUIRED', 'description' => 'Additional action needed.' ) );
		$exception         = new PayPalApiException( $response, 422 );

		$this->orderProcessor->expects( 'process' )->andThrow( $exception );

		$wcOrder->shouldReceive( 'update_status' )->once()->with( 'failed', Mockery::type( 'string' ) );

		$this->sessionHandler->shouldReceive( 'increment_insufficient_funding_tries' )->once();
		$this->sessionHandler->shouldReceive( 'insufficient_funding_tries' )->andReturn( 1 );
		$this->sessionHandler->shouldReceive( 'order' )->andReturn( null );
		$this->sessionHandler->shouldReceive( 'destroy_session_data' )->once();

		$testee = $this->createGateway();

		expect( 'wc_get_order' )
			->with( $orderId )
			->andReturn( $wcOrder );
		when( 'wc_add_notice' )->justReturn( null );
		when( 'wc_get_checkout_url' )->justReturn( 'http://example.com/checkout' );

		$woocommerce = Mockery::mock( \WooCommerce::class );
		$session     = Mockery::mock( \WC_Session::class );
		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );
		$session->shouldReceive( 'set' );

		$result = $testee->process_payment( $orderId );

		$this->assertEquals( 'failure', $result['result'] );
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

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @scenario Current user changes their subscription payment to a saved PayPal
	 * token they own. The token must be attached to the order without attempting a
	 * $0 capture (WC Subscriptions zeroes the order total during change-payment
	 * requests, and PayPal rejects $0 create-order calls with
	 * CANNOT_BE_ZERO_OR_NEGATIVE).
	 */
	public function test_process_payment_attaches_token_when_ownership_check_passes_for_change_payment(): void {
		$orderId = 1;
		$_POST['woocommerce_change_payment'] = '1';
		$_POST['wc-ppcp-gateway-payment-token'] = '99';

		$this->subscriptionHelper->shouldReceive('has_subscription')->with($orderId)->andReturn(true);
		$this->subscriptionHelper->shouldReceive('is_subscription_change_payment')->andReturn(true);

		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_id')->andReturn($orderId);
		$wcOrder->shouldReceive('get_meta')->andReturn('');
		when('wc_get_order')->justReturn($wcOrder);

		$tokens = Mockery::mock('alias:WC_Payment_Tokens');
		$payment_token = Mockery::mock(\WC_Payment_Token::class);
		$payment_token->shouldReceive('get_user_id')->andReturn(1);
		$tokens->shouldReceive('get')->with(99)->andReturn($payment_token);

		when('get_current_user_id')->justReturn(1);

		$wcOrder->shouldReceive('add_payment_token')->once()->with($payment_token);
		$wcOrder->shouldReceive('save')->once();
		$this->sessionHandler->shouldReceive('destroy_session_data')->once();

		$this->capturePayPalPayment->shouldNotReceive('create_order');

		$result = $this->createReturnUrlStubGateway()->process_payment($orderId);

		$this->assertEquals('success', $result['result']);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @scenario Current user supplies a token id belonging to a different user while
	 * changing their subscription payment. The token must not be attached, the
	 * change must fail, and no $0 capture must be attempted either.
	 */
	public function test_process_payment_rejects_token_owned_by_different_user_during_change_payment(): void {
		$orderId = 1;
		$_POST['woocommerce_change_payment'] = '1';
		$_POST['wc-ppcp-gateway-payment-token'] = '99';

		$this->subscriptionHelper->shouldReceive('has_subscription')->with($orderId)->andReturn(true);
		$this->subscriptionHelper->shouldReceive('is_subscription_change_payment')->andReturn(true);

		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_id')->andReturn($orderId);
		$wcOrder->shouldReceive('get_meta')->andReturn('');
		when('wc_get_order')->justReturn($wcOrder);

		$tokens = Mockery::mock('alias:WC_Payment_Tokens');
		$payment_token = Mockery::mock(\WC_Payment_Token::class);
		$payment_token->shouldReceive('get_user_id')->andReturn(2);
		$tokens->shouldReceive('get')->with(99)->andReturn($payment_token);

		when('get_current_user_id')->justReturn(1);
		when('wc_add_notice')->justReturn(null);
		when('wc_get_checkout_url')->justReturn('http://example.test/checkout');

		$wcOrder->shouldNotReceive('add_payment_token');
		$wcOrder->shouldNotReceive('save');
		$this->sessionHandler->shouldNotReceive('destroy_session_data');
		$this->capturePayPalPayment->shouldNotReceive('create_order');

		$result = $this->createReturnUrlStubGateway()->process_payment($orderId);

		$this->assertEquals('failure', $result['result']);
	}
}

/**
 * Test double that returns a string return URL. The shared WC_Payment_Gateway
 * stub returns the order object, which would violate the string type hint of
 * PayPalGateway::add_payment_token_to_order().
 */
class PayPalGatewayReturnUrlStub extends PayPalGateway
{
	protected function get_return_url( $order = null ) {
		return 'http://example.test/return';
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
