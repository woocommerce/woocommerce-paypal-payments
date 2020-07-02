<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Psr\Container\ContainerInterface;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
class WcGateway extends WcGatewayBase
{

    public const CAPTURED_META_KEY = '_ppcp_paypal_captured';
    public const INTENT_META_KEY = '_ppcp_paypal_intent';
    public const ORDER_ID_META_KEY = '_ppcp_paypal_order_id';

    private $settingsRenderer;
    private $authorizedPayments;
    private $notice;
    private $orderProcessor;
    private $config;

    public function __construct(
        SettingsRenderer $settingsRenderer,
        OrderProcessor $orderProcessor,
        AuthorizedPaymentsProcessor $authorizedPayments,
        AuthorizeOrderActionNotice $notice,
        ContainerInterface $config
    ) {

        $this->orderProcessor = $orderProcessor;
        $this->authorizedPayments = $authorizedPayments;
        $this->notice = $notice;
        $this->settingsRenderer = $settingsRenderer;
        $this->config = $config;

        $this->method_title = __('PayPal Payments', 'woocommerce-paypal-gateway');
        $this->method_description = __(
            'Provide your customers with the PayPal payment system',
            'woocommerce-paypal-gateway'
        );

        parent::__construct();

        $this->title = $this->config->has('title') ? $this->config->get('title') : $this->method_title;
        $this->description = $this->config->has('description') ? $this->config->get('description') : $this->method_description;

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
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Payments', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'ppcp' => [
                'type' => 'ppcp',
            ],
        ];
    }

    public function process_payment($orderId): ?array
    {
        global $woocommerce;
        $wcOrder = wc_get_order($orderId);
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

    public function captureAuthorizedPayment(\WC_Order $wcOrder): bool
    {
        $isProcessed = $this->authorizedPayments->process($wcOrder);
        $this->renderAuthorizationMessageForStatus($this->authorizedPayments->lastStatus());

        if ($isProcessed) {
            $wcOrder->add_order_note(
                __('Payment successfully captured.', 'woocommerce-paypal-gateway')
            );

            $wcOrder->set_status('processing');
            $wcOrder->update_meta_data(self::CAPTURED_META_KEY, 'true');
            $wcOrder->save();
            return true;
        }

        if ($this->authorizedPayments->lastStatus() === AuthorizedPaymentsProcessor::ALREADY_CAPTURED) {
            if ($wcOrder->get_status() === 'on-hold') {
                $wcOrder->add_order_note(
                    __('Payment successfully captured.', 'woocommerce-paypal-gateway')
                );
                $wcOrder->set_status('processing');
            }

            $wcOrder->update_meta_data(self::CAPTURED_META_KEY, 'true');
            $wcOrder->save();
            return true;
        }
        return false;
    }

    private function renderAuthorizationMessageForStatus(string $status)
    {

        $messageMapping = [
            AuthorizedPaymentsProcessor::SUCCESSFUL => AuthorizeOrderActionNotice::SUCCESS,
            AuthorizedPaymentsProcessor::ALREADY_CAPTURED => AuthorizeOrderActionNotice::ALREADY_CAPTURED,
            AuthorizedPaymentsProcessor::INACCESSIBLE => AuthorizeOrderActionNotice::NO_INFO,
            AuthorizedPaymentsProcessor::NOT_FOUND => AuthorizeOrderActionNotice::NOT_FOUND,
        ];
        $displayMessage = (isset($messageMapping[$status])) ?
            $messageMapping[$status]
            : AuthorizeOrderActionNotice::FAILED;
        $this->notice->displayMessage($displayMessage);
    }

    public function generate_ppcp_html(): string
    {

        ob_start();
        $this->settingsRenderer->render();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
