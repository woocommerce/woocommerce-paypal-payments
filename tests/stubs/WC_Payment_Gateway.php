<?php
declare(strict_types=1);

class WC_Payment_Gateway
{

    protected function get_option(string $key) : string {
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