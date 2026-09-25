<?php

/**
 * WC Session Handler for Agentic Commerce
 *
 * A WC_Session_Handler subclass for loading sessions by arbitrary cart IDs.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Session
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Session;

use WC_Session_Handler;
/**
 * Custom session handler for Agentic Commerce.
 *
 * Sessions are addressed by cart ID instead of by cookie, so every cookie-driven
 * part of the parent handler stays inactive.
 */
class AgenticWcSession extends WC_Session_Handler
{
    /**
     * Load session data by ID.
     *
     * @param string $session_id The session ID.
     * @return bool True if session loaded successfully.
     */
    public function load_session_by_id(string $session_id): bool
    {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare("SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions\n             WHERE session_key = %s AND session_expiry > %d", $session_id, time()));
        if (!$value) {
            return \false;
        }
        $this->_customer_id = $session_id;
        $this->_data = maybe_unserialize($value);
        $this->_has_cookie = \true;
        $this->set_session_expiration();
        return \true;
    }
    /**
     * Create a new session with the given ID.
     *
     * @param string $session_id The session ID.
     */
    public function create_session_with_id(string $session_id): void
    {
        $this->_customer_id = $session_id;
        $this->_data = array();
        $this->_has_cookie = \true;
        $this->set_session_expiration();
    }
    /**
     * Never reads a session cookie: sessions are loaded by ID, on demand.
     */
    public function init_session_cookie(): void
    {
        // No-op - sessions are loaded on-demand.
    }
    /**
     * Never registers the global WC session hooks, which would save this cart
     * session on shutdown and write its ID into the shopper's session cookie.
     */
    public function init(): void
    {
    }
}
