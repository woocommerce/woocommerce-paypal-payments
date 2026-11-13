<?php
declare(strict_types=1);

class WC_Payment_Gateway_CC
{
	public $plugin_id = 'woocommerce_';
	public $id = '';
	public $errors = array();
	public $settings = array();
	public $form_fields = array();

	public $enabled = 'yes';
	public $title;
	public $description;
	public $icon;
	public $has_fields;
	public $countries;
	public $availability;
	public $chosen;
	public $order_button_text;
	public $max_amount = 0;
	public $method_title = '';
	public $method_description = '';
	public $supports = array( 'products' );
	public $view_transaction_url = '';
	public $has_custom_place_order_button = false;
	public $new_method_label = '';
	public $pay_button_id = '';
	protected $tokens = array();

	public function init_settings() {}
	public function process_admin_options() {}

	protected function get_return_url($wcOrder) {
		return $wcOrder;
	}
}
