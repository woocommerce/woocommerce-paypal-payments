<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;


use Inpsyde\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\TestCase;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Mockery;
use function Brain\Monkey\Functions\expect;

class WcGatewayTest extends TestCase
{


    public function testProcessPaymentSuccess() {

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
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler
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

        $orderId = 1;
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler
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

        $orderId = 1;
        $wcOrder = Mockery::mock(\WC_Order::class);
        $lastError = 'some-error';
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $orderProcessor
            ->expects('process')
            ->andReturnFalse();
        $orderProcessor
            ->expects('lastError')
            ->andReturn($lastError);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);
        expect('wc_add_notice')
            ->with($lastError);

        global $woocommerce;
        $woocommerce = Mockery::mock(\WooCommerce::class);
        $result = $testee->process_payment($orderId);
        unset($woocommerce);
        $this->assertNull($result);
    }

    public function testCaptureAuthorizedPayment() {

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
            ->expects('lastStatus')
            ->andReturn(AuthorizedPaymentsProcessor::SUCCESSFUL);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $authorizedOrderActionNotice
            ->expects('displayMessage')
            ->with(AuthorizeOrderActionNotice::SUCCESS);

        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler
        );

        $this->assertTrue($testee->capture_authorized_payment($wcOrder));
    }

    public function testCaptureAuthorizedPaymentHasAlreadyBeenCaptured() {

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
            ->shouldReceive('lastStatus')
            ->andReturn(AuthorizedPaymentsProcessor::ALREADY_CAPTURED);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $authorizedOrderActionNotice
            ->expects('displayMessage')
            ->with(AuthorizeOrderActionNotice::ALREADY_CAPTURED);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler
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

        $wcOrder = Mockery::mock(\WC_Order::class);
        $settingsRenderer = Mockery::mock(SettingsRenderer::class);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedPaymentsProcessor
            ->expects('process')
            ->with($wcOrder)
            ->andReturnFalse();
        $authorizedPaymentsProcessor
            ->shouldReceive('lastStatus')
            ->andReturn($lastStatus);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $authorizedOrderActionNotice
            ->expects('displayMessage')
            ->with($expectedMessage);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')->andReturnFalse();
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $testee = new PayPalGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice,
            $settings,
            $sessionHandler
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