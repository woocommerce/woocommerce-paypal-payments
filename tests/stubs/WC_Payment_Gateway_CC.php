<?php
declare(strict_types=1);

class WC_Payment_Gateway_CC
{
	public function init_settings() {}
	public function process_admin_options() {}

	protected function get_return_url($wcOrder) {
		return $wcOrder;
	}
}
