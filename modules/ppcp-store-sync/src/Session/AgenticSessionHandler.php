<?php

/**
 * Agentic Commerce Session Handler
 *
 * Uses a custom WC_Session_Handler subclass to manage cart sessions.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Session
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Session;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * Class AgenticSessionHandler
 */
class AgenticSessionHandler
{
    /**
     * Session key for storing agentic commerce data.
     */
    private const SESSION_KEY = 'ppcp_agentic';
    private \WooCommerce\PayPalCommerce\StoreSync\Session\AgenticWcSession $session;
    public function __construct()
    {
        // Include required WC files for REST context.
        if (defined('WC_ABSPATH')) {
            /** @psalm-suppress UnresolvableInclude */
            include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            /** @psalm-suppress UnresolvableInclude */
            include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
        }
        $this->session = new \WooCommerce\PayPalCommerce\StoreSync\Session\AgenticWcSession();
        $this->session->init();
    }
    /**
     * Generate a unique session ID.
     *
     * @return string
     */
    private function generate_session_id(): string
    {
        return 't_' . wp_generate_password(30, \false);
    }
    /**
     * Create and save a new cart session.
     *
     * @param PayPalCart $cart     The cart to save.
     * @param string     $ec_token The PayPal EC token.
     * @return string The session ID or null on failure.
     */
    public function create_cart_session(PayPalCart $cart, string $ec_token): string
    {
        $session_key = $this->generate_session_id();
        // Create a new empty session with our custom ID.
        $this->session->create_session_with_id($session_key);
        $data = array('cart' => $cart->to_array(), 'ec_token' => $ec_token, 'created' => time());
        $this->session->set(self::SESSION_KEY, $data);
        $this->session->save_data();
        return $session_key;
    }
    /**
     * Load a cart session by ID.
     *
     * @param string $session_id The session ID.
     * @return array|null Array with 'cart' (PayPalCart), 'ec_token', 'created', or null if not
     *                    found.
     */
    public function load_cart_session(string $session_id): ?array
    {
        if (!$this->session->load_session_by_id($session_id)) {
            return null;
        }
        $session_data = $this->session->get(self::SESSION_KEY);
        if (!is_array($session_data) || !isset($session_data['cart'])) {
            return null;
        }
        try {
            // TODO: Validation issues from re-parsing are discarded; will be cleaned up in a future refactor.
            $cart = PayPalCart::from_array($session_data['cart'], new StoreValidation());
            return array('cart' => $cart, 'ec_token' => $session_data['ec_token'] ?? '', 'created' => $session_data['created'] ?? time());
        } catch (\Exception $e) {
            return null;
        }
    }
    /**
     * Update an existing cart session.
     *
     * @param string      $session_id The session ID.
     * @param PayPalCart  $cart       The updated cart.
     * @param string|null $ec_token   Optional new token; when omitted the existing token is kept.
     * @return bool True on success.
     */
    public function update_cart_session(string $session_id, PayPalCart $cart, ?string $ec_token = null): bool
    {
        // Load the session first.
        $existing = $this->load_cart_session($session_id);
        if (!$existing) {
            return \false;
        }
        $data = array('cart' => $cart->to_array(), 'ec_token' => $ec_token ?? $existing['ec_token'], 'created' => $existing['created'], 'modified' => time());
        $this->session->set(self::SESSION_KEY, $data);
        $this->session->save_data();
        return \true;
    }
    /**
     * Destroy/cleanup a cart session by ID.
     *
     * @param string $session_id The session ID to destroy.
     * @return bool True on success, false if session not found or cleanup failed.
     */
    public function destroy_cart_session(string $session_id): bool
    {
        // First verify the session exists by trying to load it.
        if (!$this->session->load_session_by_id($session_id)) {
            return \false;
        }
        // Clear the agentic commerce data from the session.
        $this->session->set(self::SESSION_KEY, null);
        $this->session->save_data();
        // Destroy the entire session to clean up completely.
        $this->session->delete_session($session_id);
        return \true;
    }
}
