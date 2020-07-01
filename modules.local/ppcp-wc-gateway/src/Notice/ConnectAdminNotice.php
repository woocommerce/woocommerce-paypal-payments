<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Notice;

use Inpsyde\PayPalCommerce\AdminNotices\Entity\Message;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Psr\Container\ContainerInterface;

class ConnectAdminNotice
{
    private $state;
    private $settings;

    public function __construct(State $state, ContainerInterface $settings)
    {
        $this->state = $state;
        $this->settings = $settings;
    }

    public function connectMessage(): ?Message
    {
        if (!$this->shouldDisplay()) {
            return null;
        }

        $message = sprintf(
            /* translators: %1$s the gateway name */
            __(
                'PayPal Payments is almost ready. To get started, <a href="%1$s">connect your account</a>.',
                'woocommerce-paypal-commerce-gateway'
            ),
            // TODO: find a better way to get the url
            admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway')
        );
        return new Message($message, 'warning');
    }

    protected function shouldDisplay(): bool
    {
        // TODO: decide on what condition to display
        return $this->state->currentState() < State::STATE_PROGRESSIVE;
    }
}
