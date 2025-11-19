<?php
/**
 * Stubs to help psalm correctly annotate problems in the plugin.
 *
 * @package WooCommerce
 */
namespace {

	if ( ! defined( 'PAYPAL_INTEGRATION_DATE' ) ) {
		define( 'PAYPAL_INTEGRATION_DATE', '2023-06-02' );
	}
	if ( ! defined( 'PAYPAL_URL' ) ) {
		define( 'PAYPAL_URL', 'https://www.paypal.com' );
	}
	if ( ! defined( 'PAYPAL_SANDBOX_URL' ) ) {
		define( 'PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com' );
	}
	if ( ! defined( 'EP_PAGES' ) ) {
		define( 'EP_PAGES', 4096 );
	}
	if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
		define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );
	}
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
	}
	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', '' );
	}
	if ( ! defined( 'PAYPAL_API_URL' ) ) {
		define( 'PAYPAL_API_URL', 'https://api-m.paypal.com' );
	}
	if ( ! defined( 'PAYPAL_SANDBOX_API_URL' ) ) {
		define( 'PAYPAL_SANDBOX_API_URL', 'https://api-m.sandbox.paypal.com' );
	}
	if ( ! defined( 'PPCP_PAYPAL_BN_CODE' ) ) {
		define( 'PPCP_PAYPAL_BN_CODE', 'Woo_PPCP' );
	}
	if ( ! defined( 'CONNECT_WOO_CLIENT_ID' ) ) {
		define( 'CONNECT_WOO_CLIENT_ID', 'AcCAsWta_JTL__OfpjspNyH7c1GGHH332fLwonA5CwX4Y10mhybRZmHLA0GdRbwKwjQIhpDQy0pluX_P' );
	}
	if ( ! defined( 'CONNECT_WOO_SANDBOX_CLIENT_ID' ) ) {
		define( 'CONNECT_WOO_SANDBOX_CLIENT_ID', 'AYmOHbt1VHg-OZ_oihPdzKEVbU3qg0qXonBcAztuzniQRaKE0w1Hr762cSFwd4n8wxOl-TCWohEa0XM_' );
	}
	if ( ! defined( 'CONNECT_WOO_MERCHANT_ID' ) ) {
		define( 'CONNECT_WOO_MERCHANT_ID', 'K8SKZ36LQBWXJ' );
	}
	if ( ! defined( 'CONNECT_WOO_SANDBOX_MERCHANT_ID' ) ) {
		define( 'CONNECT_WOO_SANDBOX_MERCHANT_ID', 'MPMFHQTVMBZ6G' );
	}
	if ( ! defined( 'CONNECT_WOO_URL' ) ) {
		define( 'CONNECT_WOO_URL', 'https://api.woocommerce.com/integrations/ppc' );
	}
	if ( ! defined( 'CONNECT_WOO_SANDBOX_URL' ) ) {
		define( 'CONNECT_WOO_SANDBOX_URL', 'https://api.woocommerce.com/integrations/ppcsandbox' );
	}


	/**
	 * Cancel the next occurrence of a scheduled action.
	 *
	 * While only the next instance of a recurring or cron action is unscheduled by this method, that
	 * will also prevent all future instances of that recurring or cron action from being run.
	 * Recurring and cron actions are scheduled in a sequence instead of all being scheduled at once.
	 * Each successive occurrence of a recurring action is scheduled only after the former action is
	 * run. If the next instance is never run, because it's unscheduled by this function, then the
	 * following instance will never be scheduled (or exist), which is effectively the same as being
	 * unscheduled by this method also.
	 *
	 * @param string $hook  The hook that the job will trigger.
	 * @param array  $args  Args that would have been passed to the job.
	 * @param string $group The group the job is assigned to.
	 *
	 * @return string|null The scheduled action ID if a scheduled action was found, or null if no
	 *                     matching action found.
	 */
	function as_unschedule_action( $hook, $args = array(), $group = '' ) {
		return null;
	}

	/**
	 * Schedule an action to run one time
	 *
	 * @param int    $timestamp When the job will run.
	 * @param string $hook      The hook to trigger.
	 * @param array  $args      Arguments to pass when the hook triggers.
	 * @param string $group     The group to assign this job to.
	 * @param bool   $unique    Whether the action should be unique.
	 *
	 * @return int The action ID.
	 */
	function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '', $unique = false ) {
		return 0;
	}

	/**
	 * Check if there is an existing action in the queue with a given hook, args and group combination.
	 *
	 * An action in the queue could be pending, in-progress or async. If the is pending for a time in
	 * future, its scheduled date will be returned as a timestamp. If it is currently being run, or an
	 * async action sitting in the queue waiting to be processed, in which case boolean true will be
	 * returned. Or there may be no async, in-progress or pending action for this hook, in which case,
	 * boolean false will be the return value.
	 *
	 * @param string $hook Name of the hook to search for.
	 * @param array  $args Arguments of the action to be searched.
	 * @param string $group Group of the action to be searched.
	 *
	 * @return int|bool The timestamp for the next occurrence of a pending scheduled action, true for an async or in-progress action or false if there is no matching action.
	 */
	function as_next_scheduled_action( $hook, $args = null, $group = '' ) {
		return 0;
	}

	/**
	 * Schedule a recurring action
	 *
	 * @param int    $timestamp When the first instance of the job will run.
	 * @param int    $interval_in_seconds How long to wait between runs.
	 * @param string $hook The hook to trigger.
	 * @param array  $args Arguments to pass when the hook triggers.
	 * @param string $group The group to assign this job to.
	 * @param bool   $unique Whether the action should be unique. It will not be scheduled if another pending or running action has the same hook and group parameters.
	 * @param int    $priority Lower values take precedence over higher values. Defaults to 10, with acceptable values falling in the range 0-255.
	 *
	 * @return int The action ID. Zero if there was an error scheduling the action.
	 */
	function as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
		return 0;
	}

	/**
	 * Retrieves the number of times a filter has been applied during the current request.
	 *
	 * @since 6.1.0
	 *
	 * @global int[] $wp_filters Stores the number of times each filter was triggered.
	 *
	 * @param string $hook_name The name of the filter hook.
	 * @return int The number of times the filter hook has been applied.
	 */
	function did_filter( $hook_name ) {
		return 0;
	}

	/**
	 * Returns whether or not a filter hook is currently being processed.
	 *
	 * The function current_filter() only returns the most recent filter being executed.
	 * did_filter() returns the number of times a filter has been applied during
	 * the current request.
	 *
	 * This function allows detection for any filter currently being executed
	 * (regardless of whether it's the most recent filter to fire, in the case of
	 * hooks called from hook callbacks) to be verified.
	 *
	 * @since 3.9.0
	 *
	 * @see current_filter()
	 * @see did_filter()
	 * @global string[] $wp_current_filter Current filter.
	 *
	 * @param string|null $hook_name Optional. Filter hook to check. Defaults to null,
	 *                               which checks if any filter is currently being run.
	 * @return bool Whether the filter is currently in the stack.
	 */
	function doing_filter( $hook_name = null ) {
		return false;
	}

	/**
	 * HTML API: WP_HTML_Tag_Processor class
	 */
// phpcs:disable
	class WP_HTML_Tag_Processor {
		public function __construct( $html ) {
		}

		/**
		 * @param array|string|null $query
		 */
		public function next_tag( $query = null ): bool {
			return false;
		}

		public function set_attribute( $name, $value ): bool {
			return false;
		}

		public function get_updated_html(): string {
			return '';
		}
	}
}

namespace Automattic\WooCommerce\Enums{
	final class ProductStatus {
		/**
		 * The product is in auto-draft status.
		 *
		 * @var string
		 */
		public const AUTO_DRAFT = 'auto-draft';

		/**
		 * The product is in draft status.
		 *
		 * @var string
		 */
		public const DRAFT = 'draft';

		/**
		 * The product is in pending status.
		 *
		 * @var string
		 */
		public const PENDING = 'pending';

		/**
		 * The product is in private status.
		 *
		 * @var string
		 */
		public const PRIVATE = 'private';

		/**
		 * The product is in publish status.
		 *
		 * @var string
		 */
		public const PUBLISH = 'publish';

		/**
		 * The product is in trash status.
		 *
		 * @var string
		 */
		public const TRASH = 'trash';

		/**
		 * The product is in future status.
		 *
		 * @var string
		 */
		public const FUTURE = 'future';
	}

	final class ProductType {
		/**
		 * Simple product type.
		 *
		 * @var string
		 */
		public const SIMPLE = 'simple';

		/**
		 * Variable product type.
		 *
		 * @var string
		 */
		public const VARIABLE = 'variable';

		/**
		 * Grouped product type.
		 *
		 * @var string
		 */
		public const GROUPED = 'grouped';

		/**
		 * External/Affiliate product type.
		 *
		 * @var string
		 */
		public const EXTERNAL = 'external';

		/**
		 * Variation product type.
		 *
		 * @var string
		 */
		public const VARIATION = 'variation';
	}
}
