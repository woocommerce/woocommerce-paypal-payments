<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;


use Psr\Container\ContainerInterface;
use Woocommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\TestCase;
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
	private $environment;

	public function setUp(): void {
		parent::setUp();

		$this->environment = Mockery::mock(Environment::class);
	}

	public function testProcessPaymentSuccess() {
	    expect('is_admin')->andReturn(false);

        $orderId = 1;
        $wcOrder = Mockery::mock(\WC_Order::class);
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $orderProcessor
            ->expects('process')
            ->andReturnUsing(
                function(\WC_Order $order) use ($wcOrder) : bool {
                    return $order === $wcOrder;
                }
            );
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $settings = Mockery::mock(Settings::class);
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $sessionHandler
	        ->shouldReceive('destroy_session_data');
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $refundProcessor = Mockery::mock(RefundProcessor::class);
        $transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
        $state = Mockery::mock(State::class);
        $state
	        ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $subscriptionHelper = Mockery::mock(SubscriptionHelper::class);
        $subscriptionHelper
            ->shouldReceive('has_subscription')
            ->with($orderId)
            ->andReturn(true);
        $subscriptionHelper
            ->shouldReceive('is_subscription_change_payment')
            ->andReturn(true);

        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state,
            $transactionUrlProvider,
            $subscriptionHelper,
			PayPalGateway::ID,
			$this->environment
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);


        when('wc_get_checkout_url')
		->justReturn('test');

        $result = $testee->process_payment($orderId);

        $this->assertIsArray($result);

        $this->assertEquals('success', $result['result']);
        $this->assertEquals($result['redirect'], $wcOrder);
    }

    public function testProcessPaymentOrderNotFound() {
	    expect('is_admin')->andReturn(false);

        $orderId = 1;
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
	    $refundProcessor = Mockery::mock(RefundProcessor::class);
	    $state = Mockery::mock(State::class);
        $transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $subscriptionHelper = Mockery::mock(SubscriptionHelper::class);

	    $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state,
            $transactionUrlProvider,
            $subscriptionHelper,
			PayPalGateway::ID,
			$this->environment
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn(false);

        $redirectUrl = 'http://example.com/checkout';

        when('wc_get_checkout_url')
			->justReturn($redirectUrl);

        expect('wc_add_notice')
			->with('Couldn\'t find order to process','error');

        $this->assertEquals(
        	[
        		'result' => 'failure',
				'redirect' => $redirectUrl
			],
			$testee->process_payment($orderId)
		);
    }


    public function testProcessPaymentFails() {
    	expect('is_admin')->andReturn(false);

        $orderId = 1;
        $wcOrder = Mockery::mock(\WC_Order::class);
        $lastError = 'some-error';
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $orderProcessor
            ->expects('process')
            ->andReturnFalse();
        $orderProcessor
            ->expects('last_error')
            ->andReturn($lastError);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
	    $refundProcessor = Mockery::mock(RefundProcessor::class);
	    $state = Mockery::mock(State::class);
        $transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $subscriptionHelper = Mockery::mock(SubscriptionHelper::class);
        $subscriptionHelper->shouldReceive('has_subscription')->with($orderId)->andReturn(true);
        $subscriptionHelper->shouldReceive('is_subscription_change_payment')->andReturn(true);
        $wcOrder->shouldReceive('update_status')->andReturn(true);

        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state,
            $transactionUrlProvider,
            $subscriptionHelper,
			PayPalGateway::ID,
			$this->environment
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);
        expect('wc_add_notice')
            ->with($lastError, 'error');

		$redirectUrl = 'http://example.com/checkout';

		when('wc_get_checkout_url')
			->justReturn($redirectUrl);

        $result = $testee->process_payment($orderId);
        $this->assertEquals(
        	[
        		'result' => 'failure',
				'redirect' => $redirectUrl
			],
			$result
		);
    }

    public function testCaptureAuthorizedPayment() {
	    expect('is_admin')->andReturn(false);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('add_order_note');
        $wcOrder
            ->expects('update_meta_data')
            ->with(PayPalGateway::CAPTURED_META_KEY, 'true');
        $wcOrder
	        ->expects('payment_complete');
        $wcOrder
            ->expects('save');
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
		$capture = Mockery::mock(Capture::class);
		$capture
			->shouldReceive('status')
			->andReturn(new CaptureStatus(CaptureStatus::COMPLETED));
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedPaymentsProcessor
            ->expects('process')
            ->with($wcOrder)
			->andReturn(AuthorizedPaymentsProcessor::SUCCESSFUL);
        $authorizedPaymentsProcessor
            ->expects('captures')
			->andReturn([$capture]);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $authorizedOrderActionNotice
            ->expects('display_message')
            ->with(AuthorizeOrderActionNotice::SUCCESS);

        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
	    $refundProcessor = Mockery::mock(RefundProcessor::class);
	    $state = Mockery::mock(State::class);
        $transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $subscriptionHelper = Mockery::mock(SubscriptionHelper::class);

        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state,
            $transactionUrlProvider,
            $subscriptionHelper,
			PayPalGateway::ID,
			$this->environment
        );

        $this->assertTrue($testee->capture_authorized_payment($wcOrder));
    }

    public function testCaptureAuthorizedPaymentHasAlreadyBeenCaptured() {

	    expect('is_admin')->andReturn(false);
        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('get_status')
            ->andReturn('on-hold');
        $wcOrder
            ->expects('add_order_note');
        $wcOrder
            ->expects('update_meta_data')
            ->with(PayPalGateway::CAPTURED_META_KEY, 'true');
        $wcOrder
	        ->expects('payment_complete');
        $wcOrder
            ->expects('save');
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedPaymentsProcessor
            ->expects('process')
            ->with($wcOrder)
			->andReturn(AuthorizedPaymentsProcessor::ALREADY_CAPTURED);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $authorizedOrderActionNotice
            ->expects('display_message')
            ->with(AuthorizeOrderActionNotice::ALREADY_CAPTURED);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
	    $refundProcessor = Mockery::mock(RefundProcessor::class);
	    $state = Mockery::mock(State::class);
        $transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $subscriptionHelper = Mockery::mock(SubscriptionHelper::class);

        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state,
            $transactionUrlProvider,
            $subscriptionHelper,
			PayPalGateway::ID,
			$this->environment
        );

        $this->assertTrue($testee->capture_authorized_payment($wcOrder));
    }

    /**
     * @dataProvider dataForTestCaptureAuthorizedPaymentNoActionableFailures
     *
     * @param string $lastStatus
     * @param int $expectedMessage
     */
    public function testCaptureAuthorizedPaymentNoActionableFailures($lastStatus, $expectedMessage) {

    	expect('is_admin')->andReturn(false);
        $wcOrder = Mockery::mock(\WC_Order::class);
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedPaymentsProcessor
            ->expects('process')
            ->with($wcOrder)
			->andReturn($lastStatus);
		$authorizedPaymentsProcessor
			->expects('captures')
			->andReturn([]);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $authorizedOrderActionNotice
            ->expects('display_message')
            ->with($expectedMessage);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
	    $refundProcessor = Mockery::mock(RefundProcessor::class);
	    $state = Mockery::mock(State::class);
        $transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $subscriptionHelper = Mockery::mock(SubscriptionHelper::class);

        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state,
            $transactionUrlProvider,
            $subscriptionHelper,
			PayPalGateway::ID,
			$this->environment
        );

        $this->assertFalse($testee->capture_authorized_payment($wcOrder));
    }

    /**
     * @dataProvider dataForTestNeedsSetup
     */
    public function testNeedsSetup($currentState, $needSetup)
    {
    	expect('is_admin')->andReturn(true);
    	$settingsRenderer = Mockery::mock(SettingsRenderer::class);
    	$orderProcessor = Mockery::mock(OrderProcessor::class);
    	$authorizedOrdersProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
    	$authorizeOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
    	$config = Mockery::mock(ContainerInterface::class);
    	$config
		    ->shouldReceive('has')
		    ->andReturn(false);
    	$sessionHandler = Mockery::mock(SessionHandler::class);
    	$refundProcessor = Mockery::mock(RefundProcessor::class);
    	$onboardingState = Mockery::mock(State::class);
    	$onboardingState
		    ->expects('current_state')
		    ->andReturn($currentState);
    	$transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
    	$subscriptionHelper = Mockery::mock(SubscriptionHelper::class);

    	$testee = new PayPalGateway(
    		$settingsRenderer,
		    $orderProcessor,
		    $authorizedOrdersProcessor,
		    $authorizeOrderActionNotice,
		    $config,
		    $sessionHandler,
		    $refundProcessor,
		    $onboardingState,
		    $transactionUrlProvider,
		    $subscriptionHelper,
			PayPalGateway::ID,
			$this->environment
	    );

    	$this->assertSame($needSetup, $testee->needs_setup());
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
		    [State::STATE_PROGRESSIVE, true],
		    [State::STATE_ONBOARDED, false]
	    ];
    }
}
