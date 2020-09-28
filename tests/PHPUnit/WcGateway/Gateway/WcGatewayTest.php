<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;


use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsFields;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Mockery;
use function Brain\Monkey\Functions\expect;

class WcGatewayTest extends TestCase
{


    public function testProcessPaymentSuccess() {
	    expect('is_admin')->andReturn(false);

        $orderId = 1;
        $wcOrder = Mockery::mock(\WC_Order::class);
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $orderProcessor
            ->expects('process')
            ->andReturnUsing(
                function(\WC_Order $order, $woocommerce) use ($wcOrder) : bool {
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
        $state = Mockery::mock(State::class);
        $state
	        ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);

        global $woocommerce;
        $woocommerce = Mockery::mock(\WooCommerce::class);
        $result = $testee->process_payment($orderId);
        unset($woocommerce);
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
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn(false);

        global $woocommerce;
        $woocommerce = Mockery::mock(\WooCommerce::class);
        $this->assertNull($testee->process_payment($orderId));
        unset($woocommerce);
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
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);
        expect('wc_add_notice')
            ->with($lastError, 'error');

        global $woocommerce;
        $woocommerce = Mockery::mock(\WooCommerce::class);
        $result = $testee->process_payment($orderId);
        unset($woocommerce);
        $this->assertNull($result);
    }

    public function testCaptureAuthorizedPayment() {
	    expect('is_admin')->andReturn(false);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('add_order_note');
        $wcOrder
            ->expects('set_status')
            ->with('processing');
        $wcOrder
            ->expects('update_meta_data')
            ->with(PayPalGateway::CAPTURED_META_KEY, 'true');
        $wcOrder
            ->expects('save');
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedPaymentsProcessor
            ->expects('process')
            ->with($wcOrder)
            ->andReturnTrue();
        $authorizedPaymentsProcessor
            ->expects('last_status')
            ->andReturn(AuthorizedPaymentsProcessor::SUCCESSFUL);
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
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state
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
            ->expects('set_status')
            ->with('processing');
        $wcOrder
            ->expects('update_meta_data')
            ->with(PayPalGateway::CAPTURED_META_KEY, 'true');
        $wcOrder
            ->expects('save');
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedPaymentsProcessor
            ->expects('process')
            ->with($wcOrder)
            ->andReturnFalse();
        $authorizedPaymentsProcessor
            ->shouldReceive('last_status')
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
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state
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
            ->andReturnFalse();
        $authorizedPaymentsProcessor
            ->shouldReceive('last_status')
            ->andReturn($lastStatus);
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
	    $state
		    ->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler,
	        $refundProcessor,
	        $state
        );

        $this->assertFalse($testee->capture_authorized_payment($wcOrder));
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
}