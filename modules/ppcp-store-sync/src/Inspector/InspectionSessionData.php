<?php

/**
 * Agentic Commerce Session Inspector
 *
 * Inspector for monitoring and debugging agentic commerce sessions during development.
 * This class provides read-only access to session data for debugging purposes.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Inspector
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Inspector;

use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticWcSession;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
/**
 * Inspector for monitoring agentic commerce sessions.
 *
 * Provides debugging and inspection capabilities for cart sessions.
 */
class InspectionSessionData
{
    /**
     * Session key for storing agentic commerce data.
     */
    private const SESSION_KEY = 'ppcp_agentic';
    /**
     * The custom session handler.
     *
     * @var AgenticWcSession
     */
    private AgenticWcSession $session;
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->session = new AgenticWcSession();
        $this->session->init();
    }
    /**
     * List all agentic cart sessions with summary information.
     *
     * Returns lightweight session metadata without full cart details.
     * Useful for displaying a list of sessions in an admin interface.
     *
     * @return array Array of session summaries, each containing:
     *               - session_id: string
     *               - created: int|null Unix timestamp
     *               - modified: int|null Unix timestamp
     *               - item_count: int Number of items in cart
     *               - ec_token: string PayPal EC token
     */
    public function list_all_sessions(): array
    {
        $all_sessions = $this->get_all_sessions();
        $session_summaries = array();
        foreach ($all_sessions as $session) {
            // Check if this session contains agentic commerce data.
            if (!isset($session['session_value'][self::SESSION_KEY])) {
                continue;
            }
            $session_data = maybe_unserialize($session['session_value'][self::SESSION_KEY]);
            // Validate required data structure.
            if (!is_array($session_data) || !isset($session_data['cart'])) {
                continue;
            }
            // Count items without fully parsing the cart.
            $item_count = 0;
            if (isset($session_data['cart']['items']) && is_array($session_data['cart']['items'])) {
                $item_count = count($session_data['cart']['items']);
            }
            $session_summaries[] = array('session_id' => $session['session_key'], 'created' => $session_data['created'] ?? null, 'modified' => $session_data['modified'] ?? null, 'item_count' => $item_count, 'ec_token' => $session_data['ec_token'] ?? '');
        }
        // Sort by created date, newest first.
        usort($session_summaries, function ($a, $b) {
            $a_time = $a['created'] ?? 0;
            $b_time = $b['created'] ?? 0;
            return $b_time <=> $a_time;
        });
        return $session_summaries;
    }
    /**
     * Inspect a specific cart session by ID.
     *
     * Returns full cart details including all items, totals, and metadata.
     * Use this when you need complete information about a specific session.
     *
     * @param string $session_id The session ID to inspect.
     * @return array|null Array with full session details or null if not found:
     *                           - session_id: string
     *                           - cart: PayPalCart Full cart object
     *                           - ec_token: string PayPal EC token
     *                           - created: int|null Unix timestamp
     *                           - modified: int|null Unix timestamp
     *                           - expires: int|null Unix timestamp when session expires
     */
    public function inspect_cart_session(string $session_id): ?array
    {
        if (!$this->session->load_session_by_id($session_id)) {
            return null;
        }
        $session_data = maybe_unserialize($this->session->get(self::SESSION_KEY));
        if (!is_array($session_data) || !isset($session_data['cart'])) {
            return null;
        }
        try {
            $cart = PayPalCart::from_array($session_data['cart']);
            // Get expiry time from database.
            $expires = $this->get_session_expiry($session_id);
            return array('session_id' => $session_id, 'cart' => $cart, 'ec_token' => $session_data['ec_token'] ?? '', 'created' => $session_data['created'] ?? null, 'modified' => $session_data['modified'] ?? null, 'expires' => $expires);
        } catch (\Exception $e) {
            return null;
        }
    }
    /**
     * Get session statistics for dashboard display.
     *
     * Provides aggregate statistics about agentic commerce sessions.
     *
     * @return array Statistics including:
     *               - total_sessions: int
     *               - total_items: int
     *               - oldest_session: int|null Unix timestamp
     *               - newest_session: int|null Unix timestamp
     *               - average_age_hours: float|null
     */
    public function get_session_statistics(): array
    {
        $sessions = $this->list_all_sessions();
        if (empty($sessions)) {
            return array('total_sessions' => 0, 'total_items' => 0, 'oldest_session' => null, 'newest_session' => null, 'average_age_hours' => null);
        }
        $total_items = 0;
        $oldest = null;
        $newest = null;
        $total_age = 0;
        $sessions_with_age = 0;
        foreach ($sessions as $session) {
            $total_items += $session['item_count'];
            if ($session['created']) {
                if ($oldest === null || $session['created'] < $oldest) {
                    $oldest = $session['created'];
                }
                if ($newest === null || $session['created'] > $newest) {
                    $newest = $session['created'];
                }
                $age = time() - $session['created'];
                $total_age += $age;
                ++$sessions_with_age;
            }
        }
        $average_age_hours = $sessions_with_age > 0 ? $total_age / $sessions_with_age / 3600 : null;
        return array('total_sessions' => count($sessions), 'total_items' => $total_items, 'oldest_session' => $oldest, 'newest_session' => $newest, 'average_age_hours' => $average_age_hours);
    }
    /**
     * Get the expiry timestamp for a session.
     *
     * @param string $session_id The session ID.
     * @return int|null Unix timestamp or null if not found.
     */
    private function get_session_expiry(string $session_id): ?int
    {
        global $wpdb;
        $expiry = $wpdb->get_var($wpdb->prepare("SELECT session_expiry FROM {$wpdb->prefix}woocommerce_sessions\n                WHERE session_key = %s", $session_id));
        return $expiry ? (int) $expiry : null;
    }
    /**
     * Get all active sessions from the database.
     *
     * This method directly queries the WooCommerce sessions table.
     * Only used for inspection purposes.
     *
     * @return array Array of sessions with 'session_key' and 'session_value' (unserialized).
     */
    private function get_all_sessions(): array
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare("SELECT session_key, session_value FROM {$wpdb->prefix}woocommerce_sessions\n             WHERE session_expiry > %d", time()));
        if (!$results) {
            return array();
        }
        $sessions = array();
        foreach ($results as $row) {
            $sessions[] = array('session_key' => $row->session_key, 'session_value' => maybe_unserialize($row->session_value));
        }
        return $sessions;
    }
}
