<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Mockery;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class WcGatewayTest extends TestCase
{
	private $isAdmin = false;
	private $sessionHandler;
	private $fundingSource = null;

	private $settingsRenderer;
	private $funding_source_renderer;
	private $orderProcessor;
	private $settings;
	private $refundProcessor;
	private $onboardingState;
	private $transactionUrlProvider;
	private $subscriptionHelper;
	private $environment;
	private $paymentTokenRepository;
	private $logger;
	private $apiShopCountry;
	private $orderEndpoint;
	private $paymentTokensEndpoint;
	private $vaultV3Enabled;
	private $wcPaymentTokens;

	public function setUp(): void {
		parent::setUp();

		expect('is_admin')->andReturnUsing(function () {
			return $this->isAdmin;
		});
		when('wc_clean')->returnArg();

		$this->settingsRenderer = Mockery::mock(SettingsRenderer::class);
		$this->orderProcessor = Mockery::mock(OrderProcessor::class);
		$this->settings = Mockery::mock(Settings::class);
		$this->sessionHandler = Mockery::mock(SessionHandler::class);
		$this->refundProcessor = Mockery::mock(RefundProcessor::class);
		$this->onboardingState = Mockery::mock(State::class);
		$this->transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
		$this->subscriptionHelper = Mockery::mock(SubscriptionHelper::class);
		$this->environment = Mockery::mock(Environment::class);
		$this->paymentTokenRepository = Mockery::mock(PaymentTokenRepository::class);
		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->funding_source_renderer = new FundingSourceRenderer(
			$this->settings,
			['venmo' => 'Venmo', 'paylater' => 'Pay Later', 'blik' => 'BLIK']
		);
		$this->apiShopCountry = 'DE';
		$this->orderEndpoint = Mockery::mock(OrderEndpoint::class);

		$this->onboardingState->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);

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
		$this->vaultV3Enabled = true;
		$this->wcPaymentTokens = Mockery::mock(WooCommercePaymentTokens::class);
	}

	private function createGateway()
	{
		return new PayPalGateway(
			$this->settingsRenderer,
			$this->funding_source_renderer,
			$this->orderProcessor,
			$this->settings,
			$this->sessionHandler,
			$this->refundProcessor,
			$this->onboardingState,
			$this->transactionUrlProvider,
			$this->subscriptionHelper,
			PayPalGateway::ID,
			$this->environment,
			$this->paymentTokenRepository,
			$this->logger,
			$this->apiShopCountry,
			$this->orderEndpoint,
			function ($id) {
				return 'checkoutnow=' . $id;
			},
			'Pay via PayPal',
			$this->paymentTokensEndpoint,
			$this->vaultV3Enabled,
			$this->wcPaymentTokens,
			false
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
     * @dataProvider dataForTestNeedsSetup
     */
    public function testNeedsSetup($currentState, $needSetup)
    {
		$this->isAdmin = true;

		$this->onboardingState = Mockery::mock(State::class);
		$this->onboardingState
		    ->expects('current_state')
		    ->andReturn($currentState);

    	$testee = $this->createGateway();

    	$this->assertSame($needSetup, $testee->needs_setup());
    }

    /**
     * @dataProvider dataForFundingSource
     */
    public function testFundingSource($fundingSource, $title, $description)
    {
		$this->fundingSource = $fundingSource;

    	$testee = $this->createGateway();

		self::assertEquals($title, $testee->title);
		self::assertEquals($description, $testee->description);
    }

    public function dataForTestCaptureAuthorizedPaymentNoActionableFailures() : array
    {
        return [
            'inaccessible' => [
                AuthorizedPaymentsProcessor::INACCESSIBLE,
                AuthorizeOrderActionNotice::NO_INFO,
            ],
            'not_found' => [
                AuthorizedPaymentsProcessor::NOT_FOUND,
                AuthorizeOrderActionNotice::NOT_FOUND,
            ],
            'not_mapped' => [
                'some-other-failure',
                AuthorizeOrderActionNotice::FAILED,
            ],
        ];
    }

    public function dataForTestNeedsSetup(): array
    {
    	return [
    		[State::STATE_START, true],
		    [State::STATE_ONBOARDED, false]
	    ];
    }

    public function dataForFundingSource(): array
    {
    	return [
    		[null, 'PayPal', 'Pay via PayPal.'],
    		['venmo', 'Venmo', 'Pay via Venmo.'],
    		['paylater', 'Pay Later', 'Pay via Pay Later.'],
    		['blik', 'BLIK (via PayPal)', 'Pay via BLIK.'],
    		['qwerty', 'PayPal', 'Pay via PayPal.'],
	    ];
    }
}
