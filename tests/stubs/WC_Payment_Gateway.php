<?php
declare(strict_types=1);

class WC_Payment_Gateway
{

    public $enabled = 'no';

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
