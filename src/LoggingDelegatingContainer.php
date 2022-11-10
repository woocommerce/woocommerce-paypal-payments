<?php // phpcs:disable
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce;

use Dhii\Container\DelegatingContainer;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;

class LoggingDelegatingContainer extends DelegatingContainer {
	/**
	 * @var \WC_Logger|null
	 */
	private $logger;

	/**
	 * @var int
	 */
	private $start_time;

	public function __construct(ServiceProviderInterface $provider, PsrContainerInterface $parent = null)
	{
		$this->logger = wc_get_logger();
		$this->start_time = $this->milliseconds();

		$this->log('Start.');

		parent::__construct($provider, $parent);
	}

	public function get($id)
	{
		$stacktrace = (new \Exception())->getTraceAsString();
		$level = substr_count($stacktrace, 'LoggingDelegatingContainer->get');

		$msg = str_repeat('  ', $level - 1)
			. $id;

		$this->log($msg);

		if ($level === 1) {
			$this->log($stacktrace);
		}

		return parent::get($id);
	}

	private function log(string $msg): void
	{
		if (!$this->logger) {
			return;
		}

		$timestamp = str_pad((string) ($this->milliseconds() - $this->start_time), 4, '0', STR_PAD_LEFT);

		$this->logger->debug($timestamp . ' ' . $msg, ['source' => 'debug-woocommerce-paypal-payments']);
	}

	private function milliseconds(): int
	{
		$date = date_create();
		if (!$date) {
			return 0;
		}
		return (int) $date->format('Uv');
	}
}
