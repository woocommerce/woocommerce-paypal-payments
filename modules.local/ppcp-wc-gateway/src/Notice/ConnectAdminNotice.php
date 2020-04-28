<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Notice;

use Inpsyde\PayPalCommerce\AdminNotices\Entity\Message;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;

class ConnectAdminNotice
{
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function connectMessage() : ?Message
    {
        if (!$this->shouldDisplay()) {
            return null;
        }

        $message = sprintf(
            /* translators: %1$s the gateway name */
            __(
                '%1$s is almost ready. To get started, <a href="%2$s">connect your account</a>.',
                'woocommerce-paypal-commerce-gateway'
            ),
            $this->settings->get('title'),
            // TODO: find a better way to get the url
            admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway')
        );
        return new Message( $message, 'warning');
    }

    protected function shouldDisplay(): bool
    {
        // TODO: decide on what condition to display
        return !wc_string_to_bool($this->settings->get('enabled'));
    }
}
