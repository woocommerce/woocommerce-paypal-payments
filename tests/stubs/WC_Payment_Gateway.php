<?php
declare(strict_types=1);

class WC_Payment_Gateway
{

    public $plugin_id = 'woocommerce_';
    public $id = '';
    public $errors = array();
    public $settings = array();
    public $form_fields = array();

    public $enabled = 'no';
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

    public function is_available() {
        return 'yes' === $this->enabled;
    }

    public function get_option(string $key, $empty_value = null) {
        return $key;
    }

    protected function init_settings() {

    }

    protected function get_return_url($wcOrder) {
        return $wcOrder;
    }

    public function process_admin_options() {

    }
}
