<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
class WcGateway extends WcGatewayBase implements WcGatewayInterface
{

    public const CAPTURED_META_KEY = '_ppcp_paypal_captured';
    public const INTENT_META_KEY = '_ppcp_paypal_intent';
    public const ORDER_ID_META_KEY = '_ppcp_paypal_order_id';

    private $isSandbox = true;
    private $settingsFields;
    private $authorizedPayments;
    private $notice;
    private $orderProcessor;

    public function __construct(
        SettingsFields $settingsFields,
        OrderProcessor $orderProcessor,
        AuthorizedPaymentsProcessor $authorizedPayments,
        AuthorizeOrderActionNotice $notice
    ) {

        $this->orderProcessor = $orderProcessor;
        $this->authorizedPayments = $authorizedPayments;
        $this->notice = $notice;
        $this->settingsFields = $settingsFields;

        $this->method_title = __('PayPal Payments', 'woocommerce-paypal-gateway');
        $this->method_description = __(
            'Provide your customers with the PayPal payment system',
            'woocommerce-paypal-gateway'
        );

        parent::__construct();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->init_form_fields();
        $this->init_settings();

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [
                $this,
                'process_admin_options',
            ]
        );
    }

    public function init_form_fields()
    {
        $this->form_fields = $this->settingsFields->fields();
    }

    public function process_payment($orderId): ?array
    {
        global $woocommerce;
        $wcOrder = new \WC_Order($orderId);
        if (! is_a($wcOrder, \WC_Order::class)) {
            return null;
        }

        if ($this->orderProcessor->process($wcOrder, $woocommerce)) {
            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($wcOrder),
            ];
        }

        wc_add_notice($this->orderProcessor->lastError());
        return null;
    }

    public function captureAuthorizedPayment(\WC_Order $wcOrder): void
    {
        $result = $this->authorizedPayments->process($wcOrder);

        if ($result === AuthorizedPaymentsProcessor::INACCESSIBLE) {
            $this->notice->displayMessage(AuthorizeOrderActionNotice::NO_INFO);
        }
        if ($result === AuthorizedPaymentsProcessor::NOT_FOUND) {
            $this->notice->displayMessage(AuthorizeOrderActionNotice::NOT_FOUND);
        }

        if ($result === AuthorizedPaymentsProcessor::ALREADY_CAPTURED) {
            if ($wcOrder->get_status() === 'on-hold') {
                $wcOrder->add_order_note(
                    __(
                        'Payment successfully captured.',
                        'woocommerce-paypal-gateway'
                    )
                );
                $wcOrder->update_status('processing');
                $wcOrder->update_meta_data(self::CAPTURED_META_KEY, 'true');
                // TODO investigate why save has to be called
                $wcOrder->save();
            }

            $this->notice->displayMessage(AuthorizeOrderActionNotice::ALREADY_CAPTURED);
        }

        if ($result === AuthorizedPaymentsProcessor::FAILED) {
            $this->notice->displayMessage(AuthorizeOrderActionNotice::FAILED);
        }

        if ($result === AuthorizedPaymentsProcessor::SUCCESSFUL) {
            $wcOrder->add_order_note(
                __(
                    'Payment successfully captured.',
                    'woocommerce-paypal-gateway'
                )
            );

            $wcOrder->update_status('processing');
            $wcOrder->update_meta_data(self::CAPTURED_META_KEY, 'true');
            // TODO investigate why save has to be called
            $wcOrder->save();

            $this->notice->displayMessage(AuthorizeOrderActionNotice::SUCCESS);
        }
    }

}
