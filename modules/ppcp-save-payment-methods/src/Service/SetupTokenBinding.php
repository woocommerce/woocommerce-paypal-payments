<?php

/**
 * Binds vault setup tokens to the customer that created them.
 *
 * Each setup token is recorded when it is created, so the exchange only accepts
 * tokens created by the same user and session, once, within a short window.
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Service;

use WC_Session;
class SetupTokenBinding
{
    private const SESSION_KEY = 'ppcp_setup_token_bindings';
    private const TTL = 3 * 3600;
    // Three hours.
    /**
     * Records a setup token as created by the current user and session.
     *
     * @param string $setup_token_id The PayPal setup token ID.
     */
    public function bind(string $setup_token_id): void
    {
        $session = $this->session();
        if (!$session || $setup_token_id === '') {
            return;
        }
        // A guest without a cart may have no session cookie yet; without one, the
        // binding would not survive until the exchange request.
        if (method_exists($session, 'has_session') && !$session->has_session() && method_exists($session, 'set_customer_session_cookie')) {
            $session->set_customer_session_cookie(\true);
        }
        $bindings = $this->unexpired_bindings($session);
        $bindings[$this->key($setup_token_id)] = array('user_id' => get_current_user_id(), 'expires' => time() + self::TTL);
        $session->set(self::SESSION_KEY, $bindings);
    }
    /**
     * Removes a setup token and reports whether it was created by the current user and session.
     *
     * The token is removed before the caller exchanges it, so each token can be
     * exchanged only once.
     *
     * @param string $setup_token_id The PayPal setup token ID.
     */
    public function consume(string $setup_token_id): bool
    {
        $session = $this->session();
        if (!$session || $setup_token_id === '') {
            return \false;
        }
        $bindings = $this->unexpired_bindings($session);
        $key = $this->key($setup_token_id);
        $binding = $bindings[$key] ?? null;
        unset($bindings[$key]);
        $session->set(self::SESSION_KEY, $bindings);
        return is_array($binding) && (int) $binding['user_id'] === get_current_user_id();
    }
    private function session(): ?WC_Session
    {
        if (!function_exists('WC')) {
            return null;
        }
        $session = WC()->session;
        return $session instanceof WC_Session ? $session : null;
    }
    /**
     * @return array<string, array{user_id: int, expires: int}>
     */
    private function unexpired_bindings(WC_Session $session): array
    {
        $bindings = $session->get(self::SESSION_KEY);
        if (!is_array($bindings)) {
            return array();
        }
        $now = time();
        return array_filter($bindings, static function ($binding) use ($now): bool {
            return is_array($binding) && isset($binding['user_id'], $binding['expires']) && (int) $binding['expires'] > $now;
        });
    }
    private function key(string $setup_token_id): string
    {
        return hash('sha256', $setup_token_id);
    }
}
