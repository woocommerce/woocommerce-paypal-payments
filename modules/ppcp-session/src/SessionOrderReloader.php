<?php

/**
 * Refreshes the PayPal order stored in the session.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Session;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
/**
 * Re-fetches a pending session order so that an approval made outside the
 * buttons (e.g. an APM that never redirects back) is picked up.
 *
 * The session order is read on almost every request, so the fetch is limited
 * to once per interval per order, and an order PayPal no longer knows about
 * is dropped from the session instead of being fetched again.
 */
class SessionOrderReloader
{
    public const LAST_RELOAD_SESSION_KEY = 'ppcp_session_order_last_reload';
    /**
     * Seconds between two fetches of the same order: the window in which an
     * approval made outside the buttons can go unnoticed.
     */
    private const RELOAD_INTERVAL = 15;
    private const TERMINAL_STATUSES = array(OrderStatus::APPROVED, OrderStatus::COMPLETED, OrderStatus::VOIDED);
    private OrderEndpoint $order_endpoint;
    private LoggerInterface $logger;
    private bool $reloaded = \false;
    public function __construct(OrderEndpoint $order_endpoint, LoggerInterface $logger)
    {
        $this->order_endpoint = $order_endpoint;
        $this->logger = $logger;
    }
    public function maybe_reload(?Order $order, \WooCommerce\PayPalCommerce\Session\SessionHandler $session_handler): void
    {
        if ($this->reloaded || !$order || !isset(WC()->session)) {
            return;
        }
        foreach (self::TERMINAL_STATUSES as $status) {
            if ($order->status()->is($status)) {
                return;
            }
        }
        $order_id = $order->id();
        if ($this->reloaded_recently($order_id)) {
            return;
        }
        $this->mark_as_reloaded($order_id);
        try {
            $session_handler->replace_order($this->order_endpoint->order($order_id));
        } catch (Throwable $exception) {
            if ($this->is_not_found($exception)) {
                $this->logger->info(sprintf('PayPal order %s no longer exists, removing it from the session.', $order_id));
                $session_handler->forget_order();
                return;
            }
            $this->logger->warning('Failed to reload PayPal order in the session: ' . $exception->getMessage());
        }
    }
    private function reloaded_recently(string $order_id): bool
    {
        $last_reload = WC()->session->get(self::LAST_RELOAD_SESSION_KEY);
        if (!is_array($last_reload) || ($last_reload['order_id'] ?? '') !== $order_id) {
            return \false;
        }
        $last_reload_age = time() - (int) ($last_reload['time'] ?? 0);
        return $last_reload_age < self::RELOAD_INTERVAL;
    }
    private function mark_as_reloaded(string $order_id): void
    {
        $this->reloaded = \true;
        WC()->session->set(self::LAST_RELOAD_SESSION_KEY, array('order_id' => $order_id, 'time' => time()));
    }
    /**
     * The order endpoint also raises code 404 for an empty response body, which
     * counts as gone here: the order cannot be recovered either way, and the
     * buyer creates a new one with the next button click.
     */
    private function is_not_found(Throwable $exception): bool
    {
        if ($exception instanceof PayPalApiException) {
            return $exception->status_code() === 404 || $exception->name() === 'RESOURCE_NOT_FOUND';
        }
        return $exception->getCode() === 404;
    }
}
