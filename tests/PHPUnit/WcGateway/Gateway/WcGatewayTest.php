<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;


use Inpsyde\PayPalCommerce\TestCase;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;
use Mockery;
use function Brain\Monkey\Functions\expect;

class WcGatewayTest extends TestCase
{


    public function testFormFieldsAreSet()
    {

        $expectedFields = ['key' => 'value'];
        $settingsFields = Mockery::mock(SettingsFields::class);
        $settingsFields
            ->expects('fields')
            ->andReturn($expectedFields);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $testee = new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice
        );
        $this->assertEquals($testee->form_fields, $expectedFields);
    }


    public function testProcessPaymentSuccess() {

        $orderId = 1;
        $wcOrder = Mockery::mock(\WC_Order::class);
        $settingsFields = Mockery::mock(SettingsFields::class);
        $settingsFields
            ->expects('fields')
            ->andReturn([]);
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
        $testee = new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);

        $result = $testee->process_payment($orderId);
        $this->assertIsArray($result);
        $this->assertEquals('success', $result['result']);
        $this->assertEquals($result['redirect'], $wcOrder);
    }

    public function testProcessPaymentOrderNotFound() {

        $orderId = 1;
        $settingsFields = Mockery::mock(SettingsFields::class);
        $settingsFields
            ->expects('fields')
            ->andReturn([]);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $testee = new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn(false);

        $this->assertNull($testee->process_payment($orderId));
    }


    public function testProcessPaymentFails() {

        $orderId = 1;
        $wcOrder = Mockery::mock(\WC_Order::class);
        $lastError = 'some-error';
        $settingsFields = Mockery::mock(SettingsFields::class);
        $settingsFields
            ->expects('fields')
            ->andReturn([]);
        $orderProcessor = Mockery::mock(OrderProcessor::class);
        $orderProcessor
            ->expects('process')
            ->andReturnFalse();
        $orderProcessor
            ->expects('lastError')
            ->andReturn($lastError);
        $authorizedPaymentsProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $authorizedOrderActionNotice = Mockery::mock(AuthorizeOrderActionNotice::class);
        $testee = new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice
        );

        expect('wc_get_order')
            ->with($orderId)
            ->andReturn($wcOrder);
        expect('wc_add_notice')
            ->with($lastError);

        $result = $testee->process_payment($orderId);
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
            ->with(WcGateway::CAPTURED_META_KEY, 'true');
        $wcOrder
            ->expects('save');
        $settingsFields = Mockery::mock(SettingsFields::class);
        $settingsFields
            ->expects('fields')
            ->andReturn([]);
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
        $testee = new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice
        );

        $this->assertTrue($testee->captureAuthorizedPayment($wcOrder));
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
            ->with(WcGateway::CAPTURED_META_KEY, 'true');
        $wcOrder
            ->expects('save');
        $settingsFields = Mockery::mock(SettingsFields::class);
        $settingsFields
            ->expects('fields')
            ->andReturn([]);
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
        $testee = new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice
        );

        $this->assertTrue($testee->captureAuthorizedPayment($wcOrder));
    }

    /**
     * @dataProvider dataForTestCaptureAuthorizedPaymentNoActionableFailures
     *
     * @param string $lastStatus
     * @param int $expectedMessage
     */
    public function testCaptureAuthorizedPaymentNoActionableFailures($lastStatus, $expectedMessage) {

        $wcOrder = Mockery::mock(\WC_Order::class);
        $settingsFields = Mockery::mock(SettingsFields::class);
        $settingsFields
            ->expects('fields')
            ->andReturn([]);
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
        $testee = new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPaymentsProcessor,
            $authorizedOrderActionNotice
        );

        $this->assertFalse($testee->captureAuthorizedPayment($wcOrder));
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