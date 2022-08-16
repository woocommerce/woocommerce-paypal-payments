<?php
declare(strict_types=1);

class WC_Payment_Gateway
{

    public function get_option(string $key, $empty_value = null) {
        return $key;
    }

    protected function init_settings() {

    }

    protected function get_return_url($order = null) {
        return '';
    }

    public function process_admin_options() {

    }
}
