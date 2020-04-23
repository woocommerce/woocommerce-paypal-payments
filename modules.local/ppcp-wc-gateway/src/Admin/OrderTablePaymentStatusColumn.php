<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Admin;

use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;

class OrderTablePaymentStatusColumn
{
    const COLUMN_KEY = 'ppcp_payment_status';
    const INTENT = 'authorize';
    const AFTER_COLUMN_KEY = 'order_status';
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(array $columns): array
    {
        if ($this->settings->get('intent') !== self::INTENT) {
            return $columns;
        }

        $statusColumnPosition = array_search(self::AFTER_COLUMN_KEY, array_keys($columns), true);
        $toInsertPosition = false === $statusColumnPosition ? count($columns) : $statusColumnPosition + 1;

        $columns = array_merge(
            array_slice($columns, 0, $toInsertPosition),
            [
                self::COLUMN_KEY => __('Payment Captured', 'woocommerce-paypal-gateway'),
            ],
            array_slice($columns, $toInsertPosition)
        );

        return $columns;
    }

    public function render(string $column, int $wcOrderId)
    {
        if ($this->settings->get('intent') !== self::INTENT) {
            return;
        }

        if (self::COLUMN_KEY !== $column) {
            return;
        }

        if ($this->isCaptured($wcOrderId)) {
            $this->renderCompletedStatus();
        } else {
            $this->renderIncompletedStatus();
        }
    }

    protected function isCaptured(int $wcOrderId): bool
    {
        $wcOrder = wc_get_order($wcOrderId);
        $captured = $wcOrder->get_meta('_ppcp_paypal_captured');

        if (!empty($captured) && wc_string_to_bool($captured)) {
            return true;
        }

        return false;
    }

    protected function renderCompletedStatus()
    {
        echo '<span class="dashicons dashicons-yes"></span>';
    }

    protected function renderIncompletedStatus()
    {
        printf(
            '<mark class="onbackorder">%s</mark>',
            esc_html__('Not captured', 'woocommerce-paypal-gateway')
        );
    }
}
