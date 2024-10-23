<?php

class WC_Subscription extends WC_Order {

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
	}
	/**
	 * Check if the subscription's payment method supports a certain feature, like date changes.
	 */
	public function payment_method_supports( $payment_gateway_feature ) {
	}

	/**
	 * Check if a the subscription can be changed to a new status or date
	 */
	public function can_be_updated_to( $new_status ) {
	}

	/**
	 * Checks if the subscription requires manual renewal payments.
	 *
	 * This differs to the @see self::get_requires_manual_renewal() method in that it also conditions outside
	 * of the 'requires_manual_renewal' property which would force a subscription to require manual renewal
	 * payments, like an inactive payment gateway or a site in staging mode.
	 *
	 * @return bool
	 */
	public function is_manual() {
	}

	/**
	 * Get the number of payments for a subscription.
	 *
	 * Default payment count includes all renewal orders and potentially an initial order
	 * (if the subscription was created as a result of a purchase from the front end
	 * rather than manually by the store manager).
	 *
	 * @param  string       $payment_type Type of count (completed|refunded|net). Optional. Default completed.
	 * @param  string|array $order_types Type of order relation(s) to count. Optional. Default array(parent,renewal).
	 * @return integer Count.
	 * @since 2.6.0
	 */
	public function get_payment_count( $payment_type = 'completed', $order_types = '' ) {
	}

	/**
	 * Get the number of payments failed
	 *
	 * Failed orders are the number of orders that have wc-failed as the status
	 */
	public function get_failed_payment_count() {
	}

	/**
	 * Returns the total amount charged at the outset of the Subscription.
	 *
	 * This may return 0 if there is a free trial period or the subscription was synchronised, and no sign up fee,
	 * otherwise it will be the sum of the sign up fee and price per period.
	 *
	 * @return float The total initial amount charged when the subscription product in the order was first purchased, if any.
	 */
	public function get_total_initial_payment() {
	}

	/**
	 * Get billing period.
	 *
	 * @return string
	 */
	public function get_billing_period( $context = 'view' ) {
	}

	/**
	 * Get billing interval.
	 *
	 * @return string
	 */
	public function get_billing_interval( $context = 'view' ) {
	}

	/**
	 * Get trial period.
	 *
	 * @return string
	 */
	public function get_trial_period( $context = 'view' ) {
	}

	/**
	 * Get suspension count.
	 *
	 * @return int
	 */
	public function get_suspension_count( $context = 'view' ) {
	}

	/**
	 * Checks if the subscription requires manual renewal payments.
	 *
	 * @return bool
	 */
	public function get_requires_manual_renewal( $context = 'view' ) {
	}

	/**
	 * Get the switch data.
	 *
	 * @return string
	 */
	public function get_switch_data( $context = 'view' ) {
	}

	/**
	 * Get the flag about whether the cancelled email has been sent or not.
	 *
	 * @return string
	 */
	public function get_cancelled_email_sent( $context = 'view' ) {
	}

	/*** Setters *****************************************************/

	/**
	 * Set billing period.
	 *
	 * @param string $value
	 */
	public function set_billing_period( $value ) {
	}

	/**
	 * Set billing interval.
	 *
	 * @param int $value
	 */
	public function set_billing_interval( $value ) {
	}

	/**
	 * Set trial period.
	 *
	 * @param string $value
	 */
	public function set_trial_period( $value ) {
	}

	/**
	 * Set suspension count.
	 *
	 * @param int $value
	 */
	public function set_suspension_count( $value ) {
	}

	/**
	 * Set the manual renewal flag on the subscription.
	 *
	 * The manual renewal flag is stored in database as string 'true' or 'false' when set, and empty string when not set
	 * (which means it doesn't require manual renewal), but we want to consistently use it via get/set as a boolean,
	 * for sanity's sake.
	 *
	 * @param bool $value
	 */
	public function set_requires_manual_renewal( $value ) {

	}

	/**
	 * Set the switch data on the subscription.
	 */
	public function set_switch_data( $value ) {
	}

	/**
	 * Set the flag about whether the cancelled email has been sent or not.
	 */
	public function set_cancelled_email_sent( $value ) {
	}

	/*** Date methods *****************************************************/

	/**
	 * Get the MySQL formatted date for a specific piece of the subscriptions schedule
	 *
	 * @param string $date_type 'date_created', 'trial_end', 'next_payment', 'last_order_date_created' or 'end'
	 * @param string $timezone The timezone of the $datetime param, either 'gmt' or 'site'. Default 'gmt'.
	 */
	public function get_date( $date_type, $timezone = 'gmt' ) {
	}

	/**
	 * Get a certain date type for the most recent order on the subscription with that date type,
	 * or the last order, if the order type is specified as 'last'.
	 *
	 * @param string $date_type Any valid WC 3.0 date property, including 'date_paid', 'date_completed', 'date_created', or 'date_modified'
	 * @param string $order_type The type of orders to return, can be 'last', 'parent', 'switch', 'renewal' or 'any'. Default 'any'. Use 'last' to only check the last order.
	 * @return WC_DateTime|NULL object if the date is set or null if there is no date.
	 */
	protected function get_related_orders_date( $date_type, $order_type = 'any' ) {
	}

	/**
	 * Set a certain date type for the last order on the subscription.
	 *
	 * @since 2.2.0
	 * @param string $date_type One of 'date_paid', 'date_completed', 'date_modified', or 'date_created'.
	 */
	protected function set_last_order_date( $date_type, $date = null ) {

		if ( $this->object_read ) {

			$setter     = 'set_' . $date_type;
			$last_order = $this->get_last_order( 'all' );

			if ( $last_order && is_callable( array( $last_order, $setter ) ) ) {
				$last_order->{$setter}( $date );
				$last_order->save();
			}
		}
	}

	/**
	 * Returns a string representation of a subscription date in the site's time (i.e. not GMT/UTC timezone).
	 *
	 * @param string $date_type 'date_created', 'trial_end', 'next_payment', 'last_order_date_created', 'end' or 'end_of_prepaid_term'
	 */
	public function get_date_to_display( $date_type = 'next_payment' ) {
	}

	/**
	 * Get the timestamp for a specific piece of the subscriptions schedule
	 *
	 * @param string $date_type 'date_created', 'trial_end', 'next_payment', 'last_order_date_created', 'end' or 'end_of_prepaid_term'
	 * @param string $timezone The timezone of the $datetime param. Default 'gmt'.
	 */
	public function get_time( $date_type, $timezone = 'gmt' ) {
	}

	/**
	 * Set the dates on the subscription.
	 *
	 * Because dates are interdependent on each other, this function will take an array of dates, make sure that all
	 * dates are in the right order in the right format, that there is at least something to update.
	 *
	 * @param array $dates array containing dates with keys: 'date_created', 'trial_end', 'next_payment', 'last_order_date_created' or 'end'. Values are MySQL formatted date/time strings in UTC timezone.
	 * @param string $timezone The timezone of the $datetime param. Default 'gmt'.
	 */
	public function update_dates( $dates, $timezone = 'gmt' ) {
	}

	/**
	 * Remove a date from a subscription.
	 *
	 * @param string $date_type 'trial_end', 'next_payment' or 'end'. The 'date_created' and 'last_order_date_created' date types will throw an exception.
	 */
	public function delete_date( $date_type ) {
	}

	/**
	 * Check if a given date type can be updated for this subscription.
	 *
	 * @param string $date_type 'date_created', 'trial_end', 'next_payment', 'last_order_date_created' or 'end'
	 */
	public function can_date_be_updated( $date_type ) {
	}

	/**
	 * Calculate a given date for the subscription in GMT/UTC.
	 *
	 * @param string $date_type 'trial_end', 'next_payment', 'end_of_prepaid_term' or 'end'
	 */
	public function calculate_date( $date_type ) {
	}

	/**
	 * Calculates the next payment date for a subscription.
	 *
	 * Although an inactive subscription does not have a next payment date, this function will still calculate the date
	 * so that it can be used to determine the date the next payment should be charged for inactive subscriptions.
	 *
	 * @return int | string Zero if the subscription has no next payment date, or a MySQL formatted date time if there is a next payment date
	 */
	protected function calculate_next_payment_date() {
	}

	/**
	 * Complete a partial save, saving subscription date changes to the database.
	 *
	 * Sometimes it's necessary to only save changes to date properties, for example, when you
	 * don't want status transitions to be triggered by a full object @see $this->save().
	 *
	 * @since 2.2.6
	 */
	public function save_dates() {
	}

	/**
	 * When payment is completed for a related order, reset any renewal related counters and reactive the subscription.
	 *
	 * @param WC_Order $order
	 */
	public function payment_complete_for_order( $last_order ) {
	}

	/**
	 * When a payment fails, either for the original purchase or a renewal payment, this function processes it.
	 *
	 * @since 2.0
	 */
	public function payment_failed( $new_status = 'on-hold' )
	{
	}
	/**
	 * Get parent order object.
	 *
	 * @return mixed WC_Order|bool
	 */
	public function get_parent() {
	}

	/**
	 * Extracting the query from get_related_orders and get_last_order so it can be moved in a cached
	 * value.
	 *
	 * @deprecated 2.3.0 Moved to WCS_Subscription_Data_Store_CPT::get_related_order_ids() to separate cache logic from subscription instances and to avoid confusion from the misnomer on this method's name - it gets renewal orders, not related orders - and its ambiguity - it runs a query and returns order IDs, it does not return a SQL query string or order objects.
	 * @return array
	 */
	public function get_related_orders_query( $subscription_id ) {
	}

	/**
	 * Get the related orders for a subscription, including renewal orders and the initial order (if any)
	 *
	 * @param string $return_fields The columns to return, either 'all' or 'ids'
	 * @param array|string $order_types Can include 'any', 'parent', 'renewal', 'resubscribe' and/or 'switch'. Custom types possible via the 'woocommerce_subscription_related_orders' filter. Defaults to array( 'parent', 'renewal', 'switch' ).
	 * @return array
	 */
	public function get_related_orders( $return_fields = 'ids', $order_types = array( 'parent', 'renewal', 'switch' ) ) {
	}

	/**
	 * Get the related order IDs for a subscription based on an order type.
	 *
	 * @param string $order_type Can include 'any', 'parent', 'renewal', 'resubscribe' and/or 'switch'. Defaults to 'any'.
	 * @return array List of related order IDs.
	 */
	protected function get_related_order_ids( $order_type = 'any' ) {
	}

	/**
	 * Gets the most recent order that relates to a subscription, including renewal orders and the initial order (if any).
	 *
	 * @param string $return_fields The columns to return, either 'all' or 'ids'
	 * @param array $order_types Can include any combination of 'parent', 'renewal', 'switch' or 'any' which will return the latest renewal order of any type. Defaults to 'parent' and 'renewal'.
	 */
	public function get_last_order( $return_fields = 'ids', $order_types = array( 'parent', 'renewal' ) ) {
	}

	/**
	 * Determine how the payment method should be displayed for a subscription.
	 *
	 * @param string $context The context the payment method is being displayed in. Can be 'admin' or 'customer'. Default 'admin'.
	 */
	public function get_payment_method_to_display( $context = 'admin' ) {
	}

	/**
	 * Check if the subscription has a line item for a specific product, by ID.
	 *
	 * @param int A product or variation ID to check for.
	 * @return bool
	 */
	public function has_product( $product_id ) {
	}

	/**
	 * Check if the subscription has a payment gateway.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function has_payment_gateway() {
	}

	/**
	 * The total sign-up fee for the subscription if any.
	 *
	 * @return int
	 */
	public function get_sign_up_fee() {
	}

	/**
	 * Check if a given line item on the subscription had a sign-up fee, and if so, return the value of the sign-up fee.
	 *
	 * The single quantity sign-up fee will be returned instead of the total sign-up fee paid. For example, if 3 x a product
	 * with a 10 BTC sign-up fee was purchased, a total 30 BTC was paid as the sign-up fee but this function will return 10 BTC.
	 *
	 * @param array|int Either an order item (in the array format returned by self::get_items()) or the ID of an order item.
	 * @param  string $tax_inclusive_or_exclusive Whether or not to adjust sign up fee if prices inc tax - ensures that the sign up fee paid amount includes the paid tax if inc
	 * @return bool
	 */
	public function get_items_sign_up_fee( $line_item, $tax_inclusive_or_exclusive = 'exclusive_of_tax' ) {
	}

	/**
	 *  Determine if the subscription is for one payment only.
	 *
	 * @return bool whether the subscription is for only one payment
	 */
	public function is_one_payment() {
	}

	/**
	 * Validates subscription date updates ensuring the proposed date changes are in the correct format and are compatible with
	 * the current subscription dates. Also returns the dates in the gmt timezone - ready for setting/deleting.
	 *
	 * @param array $dates array containing dates with keys: 'date_created', 'trial_end', 'next_payment', 'last_order_date_created' or 'end'. Values are MySQL formatted date/time strings in UTC timezone.
	 * @param string $timezone The timezone of the $datetime param. Default 'gmt'.
	 * @return array $dates array of dates in gmt timezone.
	 */
	public function validate_date_updates( $dates, $timezone = 'gmt' ) {
	}

	/**
	 * Generates a URL to add or change the subscription's payment method from the my account page.
	 *
	 * @return string
	 * @since 2.5.0
	 */
	public function get_change_payment_method_url() {
	}

	/* Get the subscription's payment method meta.
	 *
	 * @since 2.4.3
	 * @return array The subscription's payment meta in the format returned by the woocommerce_subscription_payment_meta filter.
	 */
	public function get_payment_method_meta() {
	}
}

class WC_Subscriptions_Product
{
	/**
	 * Returns the raw sign up fee value (ignoring tax) by filtering the products price.
	 *
	 * @return string
	 */
	public static function get_sign_up_fee_filter($price, $product)
	{
	}

	/**
	 * Checks a given product to determine if it is a subscription.
	 * When the received arg is a product object, make sure it is passed into the filter intact in order to retain any properties added on the fly.
	 *
	 * @param int|WC_Product $product Either a product object or product's post ID.
	 * @return bool
	 * @since 1.0
	 */
	public static function is_subscription($product)
	{
	}

	/**
	 * Output subscription string as the price html for grouped products and make sure that
	 * sign-up fees are taken into account for price.
	 *
	 * @since 1.3.4
	 */
	public static function get_grouped_price_html($price, $grouped_product)
	{
	}

	/**
	 * Output subscription string in Gravity Form fields.
	 *
	 * @since 1.1
	 */
	public static function get_gravity_form_prices($price, $product)
	{
	}

	/**
	 * Returns a string representing the details of the subscription.
	 *
	 * For example "$20 per Month for 3 Months with a $10 sign-up fee".
	 *
	 * @param WC_Product|int $product A WC_Product object or ID of a WC_Product.
	 * @param array $inclusions An associative array of flags to indicate how to calculate the price and what to include, values:
	 *    'tax_calculation'     => false to ignore tax, 'include_tax' or 'exclude_tax' To indicate that tax should be added or excluded respectively
	 *    'subscription_length' => true to include subscription's length (default) or false to exclude it
	 *    'sign_up_fee'         => true to include subscription's sign up fee (default) or false to exclude it
	 *    'price'               => string a price to short-circuit the price calculations and use in a string for the product
	 * @since 1.0
	 */
	public static function get_price_string($product, $include = array())
	{
	}

	/**
	 * Returns the active price per period for a product if it is a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return string The price charged per period for the subscription, or an empty string if the product is not a subscription.
	 * @since 1.0
	 */
	public static function get_price($product)
	{
	}

	/**
	 * Returns the sale price per period for a product if it is a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return string
	 * @since 2.2.0
	 */
	public static function get_regular_price($product, $context = 'view')
	{
	}

	/**
	 * Returns the regular price per period for a product if it is a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return string
	 * @since 2.2.0
	 */
	public static function get_sale_price($product, $context = 'view')
	{
	}

	/**
	 * Returns the subscription period for a product, if it's a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return string A string representation of the period, either Day, Week, Month or Year, or an empty string if product is not a subscription.
	 * @since 1.0
	 */
	public static function get_period($product)
	{
	}

	/**
	 * Returns the subscription interval for a product, if it's a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return int An integer representing the subscription interval, or 1 if the product is not a subscription or there is no interval
	 * @since 1.0
	 */
	public static function get_interval($product)
	{
	}

	/**
	 * Returns the length of a subscription product, if it is a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return int An integer representing the length of the subscription, or 0 if the product is not a subscription or the subscription continues for perpetuity
	 * @since 1.0
	 */
	public static function get_length($product)
	{
	}

	/**
	 * Returns the trial length of a subscription product, if it is a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return int An integer representing the length of the subscription trial, or 0 if the product is not a subscription or there is no trial
	 * @since 1.0
	 */
	public static function get_trial_length($product)
	{
	}

	/**
	 * Returns the trial period of a subscription product, if it is a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return string A string representation of the period, either Day, Week, Month or Year, or an empty string if product is not a subscription or there is no trial
	 * @since 1.2
	 */
	public static function get_trial_period($product)
	{
	}

	/**
	 * Returns the sign-up fee for a subscription, if it is a subscription.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return int|string The value of the sign-up fee, or 0 if the product is not a subscription or the subscription has no sign-up fee
	 * @since 1.0
	 */
	public static function get_sign_up_fee($product)
	{
	}

	/**
	 * Takes a subscription product's ID and returns the date on which the first renewal payment will be processed
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param int|WC_Product $product The product instance or product/post ID of a subscription product.
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 * @param string $type The return format for the date, either 'mysql', or 'timezone'. Default 'mysql'.
	 * @param string $timezone The timezone for the returned date, either 'site' for the site's timezone, or 'gmt'. Default, 'site'.
	 * @since 2.0
	 */
	public static function get_first_renewal_payment_date($product, $from_date = '', $timezone = 'gmt')
	{
	}

	/**
	 * Takes a subscription product's ID and returns the date on which the first renewal payment will be processed
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param int|WC_Product $product The product instance or product/post ID of a subscription product.
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 * @param string $type The return format for the date, either 'mysql', or 'timezone'. Default 'mysql'.
	 * @param string $timezone The timezone for the returned date, either 'site' for the site's timezone, or 'gmt'. Default, 'site'.
	 * @since 2.0
	 */
	public static function get_first_renewal_payment_time($product, $from_date = '', $timezone = 'gmt')
	{
	}

	/**
	 * Takes a subscription product's ID and returns the date on which the subscription product will expire,
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param int|WC_Product $product The product instance or product/post ID of a subscription product.
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 * @since 1.0
	 */
	public static function get_expiration_date($product, $from_date = '')
	{
	}

	/**
	 * Takes a subscription product's ID and returns the date on which the subscription trial will expire,
	 * based on the subscription's trial length and calculated from either the $from_date if specified,
	 * or the current date/time.
	 *
	 * @param int|WC_Product $product The product instance or product/post ID of a subscription product.
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date (in UTC timezone), or empty (default), which will use today's date/time (in UTC timezone).
	 * @since 1.0
	 */
	public static function get_trial_expiration_date($product, $from_date = '')
	{
	}

	/**
	 * Checks the classname being used for a product variation to see if it should be a subscription product
	 * variation, and if so, returns this as the class which should be instantiated (instead of the default
	 * WC_Product_Variation class).
	 *
	 * @return string $classname The name of the WC_Product_* class which should be instantiated to create an instance of this product.
	 * @since 1.3
	 */
	public static function set_subscription_variation_class($classname, $product_type, $post_type, $product_id)
	{
	}

	/**
	 * Ensures a price is displayed for subscription variation where WC would normally ignore it (i.e. when prices are equal).
	 *
	 * @return array $variation_details Set of name/value pairs representing the subscription.
	 * @since 1.3.6
	 */
	public static function maybe_set_variations_price_html($variation_details, $variable_product, $variation)
	{
	}

	/**
	 * Do not allow any user to delete a subscription product if it is associated with an order.
	 *
	 * Those with appropriate capabilities can still trash the product, but they will not be able to permanently
	 * delete the product if it is associated with an order (i.e. been purchased).
	 *
	 * @since 1.4.9
	 */
	public static function user_can_not_delete_subscription($allcaps, $caps, $args)
	{
	}

	/**
	 * Make sure the 'untrash' (i.e. "Restore") row action is displayed.
	 *
	 * In @return array $actions Array of actions that can be performed on the post.
	 * @return array $post Array of post values for the current product (or post object if it is not a product).
	 * @see self::user_can_not_delete_subscription() we prevent a store manager being able to delete a subscription product.
	 * However, WooCommerce also uses the `delete_post` capability to check whether to display the 'trash' and 'untrash' row actions.
	 * We want a store manager to be able to trash and untrash subscriptions, so this function adds them again.
	 *
	 * @since 1.4.9
	 */
	public static function subscription_row_actions($actions, $post)
	{
	}

	/**
	 * Remove the "Delete Permanently" action from the bulk actions select element on the Products admin screen.
	 *
	 * Because any subscription products associated with an order can not be permanently deleted (as a result of
	 * @return array $actions Array of actions that can be performed on the post.
	 * @see self::user_can_not_delete_subscription() ), leaving the bulk action in can lead to the store manager
	 * hitting the "You are not allowed to delete this item" brick wall and not being able to continue with the
	 * deletion (or get any more detailed information about which item can't be deleted and why).
	 *
	 * @since 1.4.9
	 */
	public static function subscription_bulk_actions($actions)
	{
	}

	/**
	 * Check whether a product has one-time shipping only.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @return bool True if the product requires only one time shipping, false otherwise.
	 * @since 2.2.0
	 */
	public static function needs_one_time_shipping($product)
	{
	}

	/**
	 * Hooked to the @see 'wp_scheduled_delete' WP-Cron scheduled task to rename the '_wp_trash_meta_time' meta value
	 * as '_wc_trash_meta_time'. This is the flag used by WordPress to determine which posts should be automatically
	 * purged from the trash. We want to make sure Subscriptions products are not automatically purged (but still want
	 * to keep a record of when the product was trashed).
	 *
	 * @since 1.4.9
	 */
	public static function prevent_scheduled_deletion()
	{
	}

	/**
	 * Trash subscription variations - don't delete them permanently.
	 *
	 * This is hooked to 'wp_ajax_woocommerce_remove_variation' & 'wp_ajax_woocommerce_remove_variations'
	 * before WooCommerce's WC_AJAX::remove_variation() or WC_AJAX::remove_variations() functions are run.
	 * The WooCommerce functions will still run after this, but if the variation is a subscription, the
	 * request will either terminate or in the case of bulk deleting, the variation's ID will be removed
	 * from the $_POST.
	 *
	 * @since 1.4.9
	 */
	public static function remove_variations()
	{
	}

	/**
	 * Save variation meta data when it is bulk edited from the Edit Product screen
	 *
	 * @param string $bulk_action The bulk edit action being performed
	 * @param array $data An array of data relating to the bulk edit action. $data['value'] represents the new value for the meta.
	 * @param int $variable_product_id The post ID of the parent variable product.
	 * @param array $variation_ids An array of post IDs for the variable prodcut's variations.
	 * @since 1.5.29
	 */
	public static function bulk_edit_variations($bulk_action, $data, $variable_product_id, $variation_ids)
	{
	}

	/**
	 *
	 * Hooked to `woocommerce_product_after_variable_attributes`.
	 * This function adds a hidden field to the backend's HTML output of product variations indicating whether the
	 * variation is being used in subscriptions or not.
	 * This is used by some admin JS code to prevent removal of certain variations and also display a tooltip message to the
	 * admin.
	 *
	 * @param int $loop Position of the variation inside the variations loop.
	 * @param array $variation_data Array of variation data.
	 * @param WP_Post $variation The variation's WP post.
	 * @since 2.2.17
	 */
	public static function add_variation_removal_flag($loop, $variation_data, $variation)
	{
	}

	/**
	 * Processes an AJAX request to check if a product has a variation which is either sync'd or has a trial.
	 * Once at least one variation with a trial or sync date is found, this will terminate and return true, otherwise false.
	 *
	 * @since 2.0.18
	 */
	public static function check_product_variations_for_syncd_or_trial()
	{
	}

	/**
	 * Processes an AJAX request to update a product's One Time Shipping setting after a bulk variation edit has been made.
	 * After bulk edits (variation level saving as well as variation bulk actions), variation data has been updated in the
	 * database and therefore doesn't require the product global settings to be updated by the user for the changes to take effect.
	 * This function, triggered after saving variations or triggering the trial length bulk action, ensures one time shipping settings
	 * are updated after determining if one time shipping is still available to the product.
	 *
	 * @since 2.0.18
	 */
	public static function maybe_update_one_time_shipping_on_variation_edits()
	{
	}

	/**
	 * Get a piece of subscription related meta data for a product in a version compatible way.
	 *
	 * @param mixed $product A WC_Product object or product ID
	 * @param string $meta_key The string key for the meta data
	 * @param mixed $default_value The value to return if the meta doesn't exist or isn't set
	 * @param string $empty_handling (optional) How empty values should be handled -- can be 'use_default_value' or 'allow_empty'. Defaults to 'allow_empty' returning the empty value.
	 * @return mixed
	 * @since 2.2.0
	 */
	public static function get_meta_data($product, $meta_key, $default_value, $empty_handling = 'allow_empty')
	{
	}

	/**
	 * sync variable product min/max prices with WC 3.0
	 *
	 * @param WC_Product_Variable $product
	 * @since 2.2.0
	 */
	public static function variable_subscription_product_sync($product)
	{
	}

	/**
	 * Get an array of parent IDs from a potential child product, used to determine if a product belongs to a group.
	 *
	 * @param WC_Product The product object to get parents from.
	 * @return array Parent IDs
	 * @since 2.2.4
	 */
	public static function get_parent_ids($product)
	{
	}

	/**
	 * Get a product's list of parent IDs which are a grouped type.
	 *
	 * Unlike @param WC_Product The product object to get parents from.
	 * @return array The product's grouped parent IDs.
	 * @see WC_Subscriptions_Product::get_parent_ids(), this function will return parent products which still exist, are visible and are a grouped product.
	 *
	 * @since 2.3.0
	 */
	public static function get_visible_grouped_parent_product_ids($product)
	{
	}

	/**
	 * Gets the add to cart text for subscription products.
	 *
	 * @return string The add to cart text.
	 * @since 3.0.7
	 */
	public static function get_add_to_cart_text()
	{
	}

	/**
	 * Validates an ajax request to delete a subscription variation.
	 *
	 * @since 3.x.x
	 */
	public static function validate_variation_deletion()
	{
	}
}

class WC_Product_Subscription extends WC_Product_Simple
{
}

class WC_Subscriptions
{
}

class WC_Subscriptions_Admin
{
	/**
	 * The WooCommerce settings tab name
	 *
	 * @since 1.0
	 */
	public static $tab_name = 'subscriptions';

	/**
	 * The prefix for subscription settings
	 *
	 * @since 1.0
	 */
	public static $option_prefix = 'woocommerce_subscriptions';
}

/**
 * Allow for payment dates to be synchronised to a specific day of the week, month or year.
 */
class WC_Subscriptions_Synchroniser {

	public static $setting_id;
	public static $setting_id_proration;
	public static $setting_id_days_no_fee;

	public static $post_meta_key       = '_subscription_payment_sync_date';
	public static $post_meta_key_day   = '_subscription_payment_sync_date_day';
	public static $post_meta_key_month = '_subscription_payment_sync_date_month';

	public static $sync_field_label;
	public static $sync_description;
	public static $sync_description_year;

	public static $billing_period_ranges;

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 */
	public static function init()
	{
	}

	/**
	 * Set default value of 'no' for our options.
	 *
	 * This only sets the default
	 *
	 * @param mixed  $default        The default value for the option.
	 * @param string $option         The option name.
	 * @param bool   $passed_default Whether get_option() was passed a default value.
	 *
	 * @return mixed The default option value.
	 */
	public static function option_default( $default, $option, $passed_default = null )
	{
	}

	/**
	 * Sanitize our options when they are saved in the admin area.
	 *
	 * @param mixed $value  The value being saved.
	 * @param array $option The option data array.
	 *
	 * @return mixed The sanitized option value.
	 */
	public static function sanitize_option( $value, $option )
	{
	}

	/**
	 * Check if payment syncing is enabled on the store.
	 *
	 * @since 1.5
	 */
	public static function is_syncing_enabled()
	{
	}

	/**
	 * Check if payments can be prorated on the store.
	 *
	 * @since 1.5
	 */
	public static function is_sync_proration_enabled()
	{
	}

	/**
	 * Add sync settings to the Subscription's settings page.
	 *
	 * @since 1.5
	 */
	public static function add_settings( $settings )
	{
	}

	/**
	 * Add the sync setting fields to the Edit Product screen
	 *
	 * @since 1.5
	 */
	public static function subscription_product_fields()
	{
	}

	/**
	 * Add the sync setting fields to the variation section of the Edit Product screen
	 *
	 * @since 1.5
	 */
	public static function variable_subscription_product_fields( $loop, $variation_data, $variation )
	{
	}

	/**
	 * Save sync options when a subscription product is saved
	 *
	 * @since 1.5
	 */
	public static function save_subscription_meta( $post_id )
	{
	}

	/**
	 * Save sync options when a variable subscription product is saved
	 *
	 * @since 1.5
	 */
	public static function process_product_meta_variable_subscription( $post_id )
	{
	}

	/**
	 * Save sync options when a variable subscription product is saved
	 *
	 * @since 1.5
	 */
	public static function save_product_variation( $variation_id, $index )
	{
	}

	/**
	 * Add translated syncing options for our client side script
	 *
	 * @since 1.5
	 */
	public static function admin_script_parameters( $script_parameters )
	{
	}

	/**
	 * Determine whether a product, specified with $product, needs to have its first payment processed on a
	 * specific day (instead of at the time of sign-up).
	 *
	 * @return (bool) True is the product's first payment will be synced to a certain day.
	 * @since 1.5
	 */
	public static function is_product_synced( $product )
	{
	}

	/**
	 * Determine whether a product, specified with $product, should have its first payment processed on a
	 * at the time of sign-up but prorated to the sync day.
	 *
	 * @since 1.5.10
	 *
	 * @param WC_Product $product
	 *
	 * @return bool
	 */
	public static function is_product_prorated( $product )
	{
	}

	/**
	 * Determine whether the payment for a subscription should be the full price upfront.
	 *
	 * This method is particularly concerned with synchronized subscriptions. It will only return
	 * true when the following conditions are met:
	 *
	 * - There is no free trial
	 * - The subscription is synchronized
	 * - The store owner has determined that new subscribers need to pay for their subscription upfront.
	 *
	 * Additionally, if the store owner sets a number of days prior to the synchronization day that do not
	 * require an upfront payment, this method will check to see whether the current date falls within that
	 * period for the given product.
	 *
	 * @param WC_Product $product The product to check.
	 * @param string     $from_date Optional. A MySQL formatted date/time string from which to calculate from. The default is an empty string which is today's date/time.
	 *
	 * @return bool Whether an upfront payment is required for the product.
	 */
	public static function is_payment_upfront( $product, $from_date = '' )
	{
	}

	/**
	 * Get the day of the week, month or year on which a subscription's payments should be
	 * synchronised to.
	 *
	 * @return int The day the products payments should be processed, or 0 if the payments should not be sync'd to a specific day.
	 * @since 1.5
	 */
	public static function get_products_payment_day( $product )
	{
	}

	/**
	 * Calculate the first payment date for a synced subscription.
	 *
	 * The date is calculated in UTC timezone.
	 *
	 * @param WC_Product $product A subscription product.
	 * @param string $type (optional) The format to return the first payment date in, either 'mysql' or 'timestamp'. Default 'mysql'.
	 * @param string $from_date (optional) The date to calculate the first payment from in GMT/UTC timzeone. If not set, it will use the current date. This should not include any trial period on the product.
	 * @since 1.5
	 */
	public static function calculate_first_payment_date( $product, $type = 'mysql', $from_date = '' )
	{
	}

	/**
	 * Return an i18n'ified associative array of sync options for 'year' as billing period
	 *
	 * @since 3.0.0
	 */
	public static function get_year_sync_options()
	{
	}

	/**
	 * Return an i18n'ified associative array of all possible subscription periods.
	 *
	 * @since 1.5
	 */
	public static function get_billing_period_ranges( $billing_period = '' )
	{
	}

	/**
	 * Add the first payment date to a products summary section
	 *
	 * @since 1.5
	 */
	public static function products_first_payment_date( $echo = false )
	{
	}

	/**
	 * Return a string explaining when the first payment will be completed for the subscription.
	 *
	 * @since 1.5
	 */
	public static function get_products_first_payment_date( $product )
	{
	}

	/**
	 * If a product is synchronised to a date in the future, make sure that is set as the product's first payment date
	 *
	 * @since 2.0
	 */
	public static function products_first_renewal_payment_time( $first_renewal_timestamp, $product_id, $from_date, $timezone )
	{
	}

	/**
	 * Make sure a synchronised subscription's price includes a free trial, unless it's first payment is today.
	 *
	 * @since 1.5
	 */
	public static function maybe_set_free_trial( $total = '' )
	{
	}

	/**
	 * Make sure a synchronised subscription's price includes a free trial, unless it's first payment is today.
	 *
	 * @since 1.5
	 */
	public static function maybe_unset_free_trial( $total = '' )
	{
	}

	/**
	 * Check if the cart includes a subscription that needs to be synced.
	 *
	 * @return bool Returns true if any item in the cart is a subscription sync request, otherwise, false.
	 * @since 1.5
	 */
	public static function cart_contains_synced_subscription( $cart = null )
	{
	}

	/**
	 * Maybe set the time of a product's trial expiration to be the same as the synced first payment date for products where the first
	 * renewal payment date falls on the same day as the trial expiration date, but the trial expiration time is later in the day.
	 *
	 * When making sure the first payment is after the trial expiration in @see self::calculate_first_payment_date() we only check
	 * whether the first payment day comes after the trial expiration day, because we don't want to pushing the first payment date
	 * a month or year in the future because of a few hours difference between it and the trial expiration. However, this means we
	 * could still end up with a trial end time after the first payment time, even though they are both on the same day because the
	 * trial end time is normally calculated from the start time, which can be any time of day, but the first renewal time is always
	 * set to be 3am in the site's timezone. For example, the first payment date might be calculate to be 3:00 on the 21st April 2017,
	 * while the trial end date is on the same day at 3:01 (or any time after that on the same day). So we need to check both the time and day. We also don't want to make the first payment date/time skip a year because of a few hours difference. That means we need to either modify the trial end time to be 3:00am or make the first payment time occur at the same time as the trial end time. The former is pretty hard to change, but the later will sync'd payments will be at a different times if there is a free trial ending on the same day, which could be confusing. o_0
	 *
	 * Fixes #1328
	 *
	 * @param mixed $trial_expiration_date MySQL formatted date on which the subscription's trial will end, or 0 if it has no trial
	 * @param mixed $product_id The product object or post ID of the subscription product
	 * @return mixed MySQL formatted date on which the subscription's trial is set to end, or 0 if it has no trial
	 * @since 2.0.13
	 */
	public static function recalculate_product_trial_expiration_date( $trial_expiration_date, $product_id )
	{
	}

	/**
	 * Make sure the expiration date is calculated from the synced start date for products where the start date
	 * will be synced.
	 *
	 * @param string $expiration_date MySQL formatted date on which the subscription is set to expire
	 * @param mixed $product_id The product/post ID of the subscription
	 * @param mixed $from_date A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 * @since 1.5
	 */
	public static function recalculate_product_expiration_date( $expiration_date, $product_id, $from_date )
	{
	}

	/**
	 * Check if a given timestamp (in the UTC timezone) is equivalent to today in the site's time.
	 *
	 * @param int $timestamp A time in UTC timezone to compare to today.
	 */
	public static function is_today( $timestamp )
	{
	}

	/**
	 * Filters WC_Subscriptions_Order::get_sign_up_fee() to make sure the sign-up fee for a subscription product
	 * that is synchronised is returned correctly.
	 *
	 * @param float The initial sign-up fee charged when the subscription product in the order was first purchased, if any.
	 * @param mixed $order A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param int $product_id The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 * @return float The initial sign-up fee charged when the subscription product in the order was first purchased, if any.
	 * @since 2.0
	 */
	public static function get_synced_sign_up_fee( $sign_up_fee, $subscription, $product_id )
	{
	}

	/**
	 * Removes the "set_subscription_prices_for_calculation" filter from the WC Product's woocommerce_get_price hook once
	 *
	 * @since 1.5.10
	 *
	 * @param int        $price   The current price.
	 * @param WC_Product $product The product object.
	 *
	 * @return int
	 */
	public static function set_prorated_price_for_calculation( $price, $product )
	{
	}

	/**
	 * Retrieve the full translated weekday word.
	 *
	 * Week starts on translated Monday and can be fetched
	 * by using 1 (one). So the week starts with 1 (one)
	 * and ends on Sunday with is fetched by using 7 (seven).
	 *
	 * @since 1.5.8
	 * @access public
	 *
	 * @param int $weekday_number 1 for Monday through 7 Sunday
	 * @return string Full translated weekday
	 */
	public static function get_weekday( $weekday_number )
	{
	}

	/**
	 * Override quantities used to lower stock levels by when using synced subscriptions. If it's a synced product
	 * that does not have proration enabled and the payment date is not today, do not lower stock levels.
	 *
	 * @param integer $qty the original quantity that would be taken out of the stock level
	 * @param array $order order data
	 * @param array $item item data for each item in the order
	 *
	 * @return int
	 */
	public static function maybe_do_not_reduce_stock( $qty, $order, $order_item )
	{
	}

	/**
	 * Add subscription meta for subscription that contains a synced product.
	 *
	 * @param WC_Order Parent order for the subscription
	 * @param WC_Subscription new subscription
	 * @since 2.0
	 */
	public static function maybe_add_subscription_meta( $post_id )
	{
	}

	/**
	 * When adding an item to an order/subscription via the Add/Edit Subscription administration interface, check if we should be setting
	 * the sync meta on the subscription.
	 *
	 * @param int The order item ID of an item that was just added to the order
	 * @param array The order item details
	 * @since 2.0
	 */
	public static function ajax_maybe_add_meta_for_item( $item_id, $item )
	{
	}

	/**
	 * When adding a product to an order/subscription via the WC_Subscription::add_product() method, check if we should be setting
	 * the sync meta on the subscription.
	 *
	 * @param int The post ID of a WC_Order or child object
	 * @param int The order item ID of an item that was just added to the order
	 * @param object The WC_Product for which an item was just added
	 * @since 2.0
	 */
	public static function maybe_add_meta_for_new_product( $subscription_id, $item_id, $product )
	{
	}

	/**
	 * Check if a given subscription is synced to a certain day.
	 *
	 * @param int|WC_Subscription Accepts either a subscription object of post id
	 * @return bool
	 * @since 2.0
	 */
	public static function subscription_contains_synced_product( $subscription_id )
	{
	}

	/**
	 * If the cart item is synced, add a '_synced' string to the recurring cart key.
	 *
	 * @since 2.0
	 */
	public static function add_to_recurring_cart_key( $cart_key, $cart_item )
	{
	}

	/**
	 * When adding a product line item to an order/subscription via the WC_Abstract_Order::add_product() method, check if we should be setting
	 * the sync meta on the subscription.
	 *
	 * Attached to WC 3.0+ hooks and uses WC 3.0 methods.
	 *
	 * @param int The new line item id
	 * @param WC_Order_Item
	 * @param int The post ID of a WC_Subscription
	 * @since 2.2.3
	 */
	public static function maybe_add_meta_for_new_line_item( $item_id, $item, $subscription_id )
	{
	}

	/**
	 * Store a synced product's signup fee on the line item on the subscription and order.
	 *
	 * When calculating prorated sign up fees during switches it's necessary to get the sign-up fee paid.
	 * For synced product purchases we cannot rely on the order line item price as that might include a prorated recurring price or no recurring price all.
	 *
	 * Attached to WC 3.0+ hooks and uses WC 3.0 methods.
	 *
	 * @param WC_Order_Item_Product $item The order item object.
	 * @param string $cart_item_key The hash used to identify the item in the cart
	 * @param array $cart_item The cart item's data.
	 * @since 2.3.0
	 */
	public static function maybe_add_line_item_meta( $item, $cart_item_key, $cart_item )
	{
	}

	/**
	 * Store a synced product's signup fee on the line item on the subscription and order.
	 *
	 * This function is a pre WooCommerce 3.0 version of @see WC_Subscriptions_Synchroniser::maybe_add_line_item_meta()
	 *
	 * @param int $item_id The order item ID.
	 * @param array $cart_item The cart item's data.
	 * @since 2.3.0
	 */
	public static function maybe_add_order_item_meta( $item_id, $cart_item )
	{
	}

	/**
	 * Hides synced subscription meta on the edit order and subscription screen on non-debug sites.
	 *
	 * @since 2.6.2
	 * @param array $hidden_meta_keys the list of meta keys hidden on the edit order and subscription screen.
	 * @return array $hidden_meta_keys
	 */
	public static function hide_order_itemmeta( $hidden_meta_keys )
	{
	}

	/**
	 * Gets the number of sign-up grace period days.
	 *
	 * @since 3.0.6
	 * @return int The number of days in the grace period. 0 will be returned if the stroe isn't charging the full recurring price on sign-up -- a prerequiste for setting a grace period.
	 */
	private static function get_number_of_grace_period_days()
	{
	}
}

/**
 * Check if a given object is a WC_Subscription (or child class of WC_Subscription), or if a given ID
 * belongs to a post with the subscription post type ('shop_subscription')
 *
 * @return boolean true if anything is found
 * @since  2.0
 */
function wcs_is_subscription($subscription)
{
}

/**
 * A very simple check. Basically if we have ANY subscriptions in the database, then the user has probably set at
 * least one up, so we can give them the standard message. Otherwise
 *
 * @return boolean true if anything is found
 * @since  2.0
 */
function wcs_do_subscriptions_exist()
{
}

/**
 * Main function for returning subscriptions. Wrapper for the wc_get_order() method.
 *
 * @param mixed $the_subscription Post object or post ID of the order.
 * @return WC_Subscription|false The subscription object, or false if it cannot be found.
 * @since  2.0
 */
function wcs_get_subscription($the_subscription)
{
}

/**
 * Create a new subscription
 *
 * Returns a new WC_Subscription object on success which can then be used to add additional data.
 *
 * @return WC_Subscription | WP_Error A WC_Subscription on success or WP_Error object on failure
 * @since  2.0
 */
function wcs_create_subscription($args = array())
{
}

/**
 * Return an array of subscription status types, similar to @return array
 * @since  2.0
 * @see wc_get_order_statuses()
 *
 */
function wcs_get_subscription_statuses()
{
}

/**
 * Get the nice name for a subscription's status
 *
 * @param string $status
 * @return string
 * @since  2.0
 */
function wcs_get_subscription_status_name($status)
{
}

/**
 * Helper function to return a localised display name for an address type
 *
 * @param string $address_type the type of address (shipping / billing)
 *
 * @return string
 */
function wcs_get_address_type_to_display($address_type)
{
}

/**
 * Returns an array of subscription dates
 *
 * @return array
 * @since  2.0
 */
function wcs_get_subscription_date_types()
{
}

/**
 * Find whether to display a specific date type in the admin area
 *
 * @param string A subscription date type key. One of the array key values returned by @see wcs_get_subscription_date_types().
 * @param WC_Subscription
 * @return bool
 * @since 2.1
 */
function wcs_display_date_type($date_type, $subscription)
{
}

/**
 * Get the meta key value for storing a date in the subscription's post meta table.
 *
 * @param string $date_type Internally, 'trial_end', 'next_payment' or 'end', but can be any string
 * @since 2.0
 */
function wcs_get_date_meta_key($date_type)
{
}

/**
 * Accept a variety of date type keys and normalise them to current canonical key.
 *
 * This method saves code calling the WC_Subscription date functions, e.g. self::get_date(), needing
 * to make sure they pass the correct date type key, which can involve transforming a prop key or
 * deprecated date type key.
 *
 * @param string $date_type_key String referring to a valid date type, can be: 'date_created', 'trial_end', 'next_payment', 'last_order_date_created' or 'end', or any other value returned by @see this->get_valid_date_types()
 * @return string
 * @since 2.2.0
 */
function wcs_normalise_date_type_key($date_type_key, $display_deprecated_notice = false)
{
}

/**
 * Utility function to standardise status keys:
 * - turns 'pending' into 'wc-pending'.
 * - turns 'wc-pending' into 'wc-pending'
 *
 * @param string $status_key The status key going in
 * @return string             Status key guaranteed to have 'wc-' at the beginning
 */
function wcs_sanitize_subscription_status_key($status_key)
{
}

/**
 * A general purpose function for grabbing an array of subscriptions in form of post_id => WC_Subscription
 *
 * The $args parameter is based on the parameter of the same name used by the core WordPress @param array $args A set of name value pairs to determine the return value.
 *   'subscriptions_pefned. Can be 'ASC' or 'DESC'. Defaults to 'DESC'
 *   'customer_id' The user ID of a customer on the site.
 *   'product_id' The post ID of a WC_Product_Subscription, WC_Product_Variable_Subscription or WC_Product_Subscription_Variation object
 *   'order_id' The post ID of a shop_order post/WC_Order object which was used to create the subscription
 *   'subscription_status' Any valid subscription status. Can be 'any', 'active', 'cancelled', 'on-hold', 'expired', 'pending' or 'trash'. Defaults to 'any'.
 * @return array Subscription details in post_id => WC_Subscription form.
 * @see get_posts() function.
 * It can be used to choose which subscriptions should be returned by the function, how many subscriptions should be returned
 * and in what order those subscriptions should be returned.
 *
 * @since  2.0
 */
function wcs_get_subscriptions($args)
{
}

/**
 * Get subscriptions that contain a certain product, specified by ID.
 *
 * @param int|array $product_ids Either the post ID of a product or variation or an array of product or variation IDs
 * @param string $fields The fields to return, either "ids" to receive only post ID's for the match subscriptions, or "subscription" to receive WC_Subscription objects
 * @param array $args A set of name value pairs to determine the returned subscriptions.
 *      'subscription_statuses' Any valid subscription status. Can be 'any', 'active', 'cancelled', 'on-hold', 'expired', 'pending' or 'trash' or an array of statuses. Defaults to 'any'.
 *      'limit' The number of subscriptions to return. Default is all (-1).
 *      'offset' An optional number of subscriptions to displace or pass over. Default 0. A limit arg is required for the offset to be applied.
 * @return array
 * @since  2.0
 */
function wcs_get_subscriptions_for_product($product_ids, $fields = 'ids', $args = array())
{
}

/**
 * Get all subscription items which have a trial.
 *
 * @param mixed WC_Subscription|post_id
 * @return array
 * @since 2.0
 */
function wcs_get_line_items_with_a_trial($subscription_id)
{
}

/**
 * Checks if the user can be granted the permission to remove a line item from the subscription.
 *
 * @param WC_Subscription $subscription An instance of a WC_Subscription object
 * @since 2.0
 */
function wcs_can_items_be_removed($subscription)
{
}

/**
 * Checks if the user can be granted the permission to remove a particular line item from the subscription.
 *
 * @param WC_Order_item $item An instance of a WC_Order_item object
 * @param WC_Subscription $subscription An instance of a WC_Subscription object
 * @since 2.2.15
 */
function wcs_can_item_be_removed($item, $subscription)
{
}

/**
 * Get the Product ID for an order's line item (only the product ID, not the variation ID, even if the order item
 * is for a variation).
 *
 * @param int An order item ID
 * @since 2.0
 */
function wcs_get_order_items_product_id($item_id)
{
}

/**
 * Get the variation ID for variation items or the product ID for non-variation items.
 *
 * When acting on cart items or order items, Subscriptions often needs to use an item's canonical product ID. For
 * items representing a variation, that means the 'variation_id' value, if the item is not a variation, that means
 * the product_id value. This function helps save keystrokes on the idiom to check if an item is to a variation or not.
 *
 * @param array or object $item Either a cart item, order/subscription line item, or a product.
 */
function wcs_get_canonical_product_id($item_or_product)
{
}

/**
 * Return an array statuses used to describe when a subscriptions has been marked as ending or has ended.
 *
 * @return array
 * @since 2.0
 */
function wcs_get_subscription_ended_statuses()
{
}

/**
 * Returns true when on the My Account > View Subscription front end page.
 *
 * @return bool
 * @since 2.0
 */
function wcs_is_view_subscription_page()
{
}

/**
 * Get a WooCommerce Subscription's image asset url.
 *
 * @param string $file_name The image file name.
 * @return string The image asset url.
 * @since 2.2.20
 */
function wcs_get_image_asset_url($file_name)
{
}

/**
 * Search subscriptions
 *
 * @param string $term Term to search
 * @return array of subscription ids
 * @since 2.3.0
 */
function wcs_subscription_search($term)
{
}

/**
 * Set payment method meta data for a subscription or order.
 *
 * @param WC_Subscription|WC_Order $subscription The subscription or order to set the post payment meta on.
 * @param array $payment_meta Associated array of the form: $database_table => array( 'meta_key' => array( 'value' => '' ) )
 * @throws InvalidArgumentException
 * @since 2.4.3
 */
function wcs_set_payment_meta($subscription, $payment_meta)
{
}

/**
 * Get total quantity of a product on a subscription or order, even across multiple line items.
 *
 * @param WC_Order|WC_Subscription $subscription Order or subscription object.
 * @param WC_Product $product The product to get the total quantity of.
 * @param string $product_match_method The way to find matching products. Optional. Default is 'stock_managed' Can be:
 *     'stock_managed'  - Products with matching stock managed IDs are grouped. Helpful for getting the total quantity of variation parents if they are managed on the product level, not on the variation level - @return int $quantity The total quantity of a product on an order or subscription.
 * @see WC_Product::get_stock_managed_by_id().
 *     'parent'         - Products with the same parent ID are grouped. Standard products are matched together by ID. Variations are matched with variations with the same parent product ID.
 *     'strict_product' - Products with the exact same product ID are grouped. Variations are only grouped with other variations that share the variation ID.
 *
 * @since 2.6.0
 *
 */
function wcs_get_total_line_item_product_quantity($order, $product, $product_match_method = 'stock_managed')
{
}

/**
 * Determines if a site can be considered large for the purposes of performance.
 *
 * Sites are considered large if they have more than 3000 subscriptions or more than 25000 orders.
 *
 * @return bool True for large sites, otherwise false.
 * @since 3.0.7
 */
function wcs_is_large_site()
{
}

/**
 * Create a renewal order to record a scheduled subscription payment.
 *
 * This method simply creates an order with the same post meta, order items and order item meta as the subscription
 * passed to it.
 *
 * @param int | WC_Subscription $subscription Post ID of a 'shop_subscription' post, or instance of a WC_Subscription object
 * @return WC_Order | WP_Error
 * @since  2.0
 */
function wcs_create_renewal_order($subscription)
{
}

/**
 * Check if a given order is a subscription renewal order.
 *
 * @param WC_Order|int $order The WC_Order object or ID of a WC_Order order.
 * @return bool
 * @since 2.0
 */
function wcs_order_contains_renewal($order)
{
}

/**
 * Checks the cart to see if it contains a subscription product renewal.
 *
 * @param bool | Array The cart item containing the renewal, else false.
 * @return string
 * @since  2.0
 */
function wcs_cart_contains_renewal()
{
}

/**
 * Checks the cart to see if it contains a subscription product renewal for a failed renewal payment.
 *
 * @return bool|array The cart item containing the renewal, else false.
 * @since  2.0
 */
function wcs_cart_contains_failed_renewal_order_payment()
{
}

/**
 * Get the subscription/s to which a resubscribe order relates.
 *
 * @param WC_Order|int $order The WC_Order object or ID of a WC_Order order.
 * @since 2.0
 */
function wcs_get_subscriptions_for_renewal_order($order)
{
}

/**
 * Get the last renewal order which isn't an early renewal order.
 *
 * @param WC_Subscription $subscription The subscription object.
 * @return WC_Order|bool The last non-early renewal order, otherwise false.
 * @since 2.6.0
 *
 */
function wcs_get_last_non_early_renewal_order($subscription)
{
}

/**
 * Get the subscription related to an order, if any.
 *
 * @param WC_Order|int $order An instance of a WC_Order object or the ID of an order
 * @param array $args A set of name value pairs to filter the returned value.
 *    'subscriptions_per_page' The number of subscriptions to return. Default set to -1 to return all.
 *    'offset' An optional number of subscription to displace or pass over. Default 0.
 *    'orderby' The field which the subscriptions should be ordered by. Can be 'start_date', 'trial_end_date', 'end_date', 'status' or 'order_id'. Defaults to 'start_date'.
 *    'order' The order of the values returned. Can be 'ASC' or 'DESC'. Defaults to 'DESC'
 *    'customer_id' The user ID of a customer on the site.
 *    'product_id' The post ID of a WC_Product_Subscription, WC_Product_Variable_Subscription or WC_Product_Subscription_Variation object
 *    'order_id' The post ID of a shop_order post/WC_Order object which was used to create the subscription
 *    'subscription_status' Any valid subscription status. Can be 'any', 'active', 'cancelled', 'on-hold', 'expired', 'pending' or 'trash'. Defaults to 'any'.
 *    'order_type' Get subscriptions for the any order type in this array. Can include 'any', 'parent', 'renewal' or 'switch', defaults to parent.
 * @return WC_Subscription[] Subscription details in post_id => WC_Subscription form.
 * @since  2.0
 */
function wcs_get_subscriptions_for_order($order, $args = array())
{
}

/**
 * Copy the billing, shipping or all addresses from one order to another (including custom order types, like the
 * WC_Subscription order type).
 *
 * @param WC_Order $to_order The WC_Order object to copy the address to.
 * @param WC_Order $from_order The WC_Order object to copy the address from.
 * @param string $address_type The address type to copy, can be 'shipping', 'billing' or 'all'
 * @return WC_Order The WC_Order object with the new address set.
 * @since  2.0
 */
function wcs_copy_order_address($from_order, $to_order, $address_type = 'all')
{
}

/**
 * Utility function to copy order meta between two orders. Originally intended to copy meta between
 * first order and subscription object, then between subscription and renewal orders.
 *
 * The hooks used here in those cases are
 * - wcs_subscription_meta_query
 * - wcs_subscription_meta
 * - wcs_renewal_order_meta_query
 * - wcs_renewal_order_meta
 *
 * @param WC_Order $from_order Order to copy meta from
 * @param WC_Order $to_order Order to copy meta to
 * @param string $type type of copy
 */
function wcs_copy_order_meta($from_order, $to_order, $type = 'subscription')
{
}

/**
 * Function to create an order from a subscription. It can be used for a renewal or for a resubscribe
 * order creation. It is the common in both of those instances.
 *
 * @param WC_Subscription|int $subscription Subscription we're basing the order off of
 * @param string $type Type of new order. Default values are 'renewal_order'|'resubscribe_order'
 * @return WC_Order|WP_Error New order or error object.
 */
function wcs_create_order_from_subscription($subscription, $type)
{
}

/**
 * Function to create a post title based on the type and the current date and time for new orders. By
 * default it's either renewal or resubscribe orders.
 *
 * @param string $type type of new order. By default 'renewal_order'|'resubscribe_order'
 * @return string       new title for a post
 */
function wcs_get_new_order_title($type)
{
}

/**
 * Utility function to check type. Filterable. Rejects if not in allowed new order types, rejects
 * if not actually string.
 *
 * @param string $type type of new order
 * @return string|WP_Error the same type thing if no problems are found, or WP_Error.
 */
function wcs_validate_new_order_type($type)
{
}

/**
 * Wrapper function to get the address from an order / subscription in array format
 * @param WC_Order $order The order / subscription we want to get the order from
 * @param string $address_type shipping|billing. Default is shipping
 * @return array
 */
function wcs_get_order_address($order, $address_type = 'shipping')
{
}

/**
 * Checks an order to see if it contains a subscription.
 *
 * @param mixed $order A WC_Order object or the ID of the order which the subscription was purchased in.
 * @param array|string $order_type Can include 'parent', 'renewal', 'resubscribe' and/or 'switch'. Defaults to 'parent', 'resubscribe' and 'switch' orders.
 * @return bool True if the order contains a subscription that belongs to any of the given order types, otherwise false.
 * @since 2.0
 */
function wcs_order_contains_subscription($order, $order_type = array('parent', 'resubscribe', 'switch'))
{
}

/**
 * Get all the orders that relate to a subscription in some form (rather than only the orders associated with
 * a specific subscription).
 *
 * @param string $return_fields The columns to return, either 'all' or 'ids'
 * @param array|string $order_type Can include 'any', 'parent', 'renewal', 'resubscribe' and/or 'switch'. Defaults to 'parent'.
 * @return array The orders that relate to a subscription, if any. Will contain either as just IDs or WC_Order objects depending on $return_fields value.
 * @since 2.1
 */
function wcs_get_subscription_orders($return_fields = 'ids', $order_type = 'parent')
{
}

/**
 * A wrapper for getting a specific item from an order or subscription.
 *
 * WooCommerce has a wc_add_order_item() function, wc_update_order_item() function and wc_delete_order_item() function,
 * but no `wc_get_order_item()` function, so we need to add our own (for now).
 *
 * @param int $item_id The ID of an order item
 * @param WC_Order|WC_Subscription $order The order or order object the item belongs to.
 *
 * @return WC_Order_Item|array The order item object or an empty array if the item doesn't exist.
 *
 * @since 2.0
 */
function wcs_get_order_item($item_id, $order)
{
}

/**
 * A wrapper for wc_update_order_item() which consistently deletes the cached item after update, unlike WC.
 *
 * @param int $item_id The ID of an order item
 * @param string $new_type The new type to set as the 'order_item_type' value on the order item.
 * @param int $order_or_subscription_id The order or subscription ID the line item belongs to - optional. Deletes the order item cache if provided.
 * @since 2.2.12
 */
function wcs_update_order_item_type($item_id, $new_type, $order_or_subscription_id = 0)
{
}

/**
 * Get an instance of WC_Order_Item_Meta for an order item
 *
 * @param array
 * @return WC_Order_Item_Meta
 * @since 2.0
 */
function wcs_get_order_item_meta($item, $product = null)
{
}

/**
 * Create a string representing an order item's name and optionally include attributes.
 *
 * @param array $order_item An order item.
 * @since 2.0
 */
function wcs_get_order_item_name($order_item, $include = array())
{
}

/**
 * Get the full name for a order/subscription line item, including the items non hidden meta
 * (i.e. attributes), as a flat string.
 *
 * @param array
 * @return string
 */
function wcs_get_line_item_name($line_item)
{
}

/**
 * Display item meta data in a version compatible way.
 *
 * @param WC_Item $item
 * @param WC_Order $order
 * @return void
 * @since  2.2.0
 */
function wcs_display_item_meta($item, $order)
{
}

/**
 * Display item download links in a version compatible way.
 *
 * @param WC_Item $item
 * @param WC_Order $order
 * @return void
 * @since  2.2.0
 */
function wcs_display_item_downloads($item, $order)
{
}

/**
 * Copy the order item data and meta data from one item to another.
 *
 * @param WC_Order_Item $from_item The order item to copy data from
 * @param WC_Order_Item $to_item The order item to copy data to
 * @since  2.2.0
 */
function wcs_copy_order_item($from_item, &$to_item)
{
}

/**
 * Checks an order to see if it contains a manual subscription.
 *
 * @param WC_Order|int $order The WC_Order object or ID to get related subscriptions from.
 * @param string|array $order_type The order relationship type(s). Can be single string or an array of order types. Optional. Default is 'any'.
 * @return bool
 * @since 2.4.3
 */
function wcs_order_contains_manual_subscription($order, $order_type = 'any')
{
}

/**
 * Copy payment method from a subscription to an order.
 *
 * @param WC_Subscription $subscription
 * @param WC_Order $order
 * @since 2.4.3
 */
function wcs_copy_payment_method_to_order($subscription, $order)
{
}

/**
 * Returns how many minutes ago the order was created.
 *
 * @param WC_Order $order
 *
 * @return int
 * @since 2.5.3
 */
function wcs_minutes_since_order_created($order)
{
}

/**
 * Returns how many seconds ago the order was created.
 *
 * @param WC_Order $order
 *
 * @return int
 * @since 2.5.3
 */
function wcs_seconds_since_order_created($order)
{
}

/**
 * Finds a corresponding subscription line item on an order.
 *
 * @param WC_Abstract_Order $order The order object to look for the item in.
 * @param WC_Order_Item $subscription_item The line item on the the subscription to find on the order.
 * @param string $match_type Optional. The type of comparison to make. Can be 'match_product_ids' to compare product|variation IDs or 'match_attributes' to also compare by item attributes on top of matching product IDs. Default 'match_product_ids'.
 *
 * @return WC_Order_Item|bool The order item which matches the subscription item or false if one cannot be found.
 * @since 2.6.0
 *
 */
function wcs_find_matching_line_item($order, $subscription_item, $match_type = 'match_product_ids')
{
}

/**
 * Checks if an order contains a product.
 *
 * @param WC_Order $order An order object
 * @param WC_Product $product A product object
 *
 * @return bool $order_has_product Whether the order contains a line item matching that product
 * @since 2.6.0
 *
 */
function wcs_order_contains_product($order, $product)
{
}

/**
 * Get page ID for a specific WC resource.
 *
 * @param string $for Name of the resource.
 *
 * @return string Page ID. Empty string if resource not found.
 */
function wc_get_page_screen_id( $for ) {}

/**
 * Subscription Product Variation Class
 *
 * The subscription product variation class extends the WC_Product_Variation product class
 * to create subscription product variations.
 *
 * @class    WC_Product_Subscription
 * @package  WooCommerce Subscriptions
 * @category Class
 * @since    1.0.0 - Migrated from WooCommerce Subscriptions v1.3
 *
 */
class WC_Product_Subscription_Variation extends WC_Product_Variation {}

/**
 * Variable Subscription Product Class
 *
 * This class extends the WC Variable product class to create variable products with recurring payments.
 *
 * @class WC_Product_Variable_Subscription
 * @package WooCommerce Subscriptions
 * @category Class
 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v1.3
 *
 */
class WC_Product_Variable_Subscription extends WC_Product_Variable {}

class WCS_Manual_Renewal_Manager {

	/**
	 * Initalise the class and attach callbacks.
	 */
	public static function init() {
	}

	/**
	 * Adds the manual renewal settings.
	 *
	 * @since 4.0.0
	 * @param $settings The full subscription settings array.
	 * @return $settings.
	 */
	public static function add_settings( $settings ) {
	}

	/**
	 * Checks if manual renewals are required - automatic renewals are disabled.
	 *
	 * @since 4.0.0
	 * @return bool Weather manual renewal is required.
	 */
	public static function is_manual_renewal_required() {
	}

	/**
	 * Checks if manual renewals are enabled.
	 *
	 * @since 4.0.0
	 * @return bool Weather manual renewal is enabled.
	 */
	public static function is_manual_renewal_enabled() {
	}
}
