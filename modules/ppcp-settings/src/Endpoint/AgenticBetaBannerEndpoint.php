<?php

/**
 * REST endpoint to handle agentic beta banner interactions.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
/**
 * Handles banner interactions for the agentic beta program:
 * permanent dismissal, 7-day snooze (remind-me-later), and survey application.
 */
class AgenticBetaBannerEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    public const OPTION_DISMISSED = 'woocommerce-ppcp-agentic-banner-dismissed';
    public const OPTION_STATUS = 'woocommerce-ppcp-agentic-beta-status';
    public const OPTION_SNOOZED_UNTIL = 'woocommerce-ppcp-agentic-banner-snoozed-until';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const SNOOZE_DAYS = 7;
    protected $rest_base = 'agentic-beta-banner';
    public function register_routes(): void
    {
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/dismiss', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'handle_dismiss'), 'permission_callback' => array($this, 'check_permission')));
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/remind', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'handle_remind'), 'permission_callback' => array($this, 'check_permission')));
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/apply', array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'handle_apply'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Permanently dismisses the banner. Once dismissed it will never be shown again.
     */
    public function handle_dismiss(WP_REST_Request $request): WP_REST_Response
    {
        update_option(self::OPTION_DISMISSED, \true);
        return $this->return_success(array('dismissed' => \true));
    }
    /**
     * Snoozes the banner for {@see self::SNOOZE_DAYS} days and sets status to pending.
     * After the snooze period expires the banner becomes eligible to show again.
     */
    public function handle_remind(WP_REST_Request $request): WP_REST_Response
    {
        $snoozed_until = time() + self::SNOOZE_DAYS * DAY_IN_SECONDS;
        update_option(self::OPTION_SNOOZED_UNTIL, $snoozed_until);
        update_option(self::OPTION_STATUS, self::STATUS_PENDING);
        return $this->return_success(array('snoozed_until' => $snoozed_until));
    }
    /**
     * Records that the merchant has applied for the beta program.
     */
    public function handle_apply(WP_REST_Request $request): WP_REST_Response
    {
        update_option(self::OPTION_STATUS, self::STATUS_APPLIED);
        return $this->return_success(array('status' => self::STATUS_APPLIED));
    }
}
