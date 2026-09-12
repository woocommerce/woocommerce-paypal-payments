<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Order;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use LogicException;

/**
 * Proves a persisted empty invoice prefix survives the full container stack: the value
 * written to the real settings option reaches PurchaseUnitFactory through the live
 * 'api.factory.purchase-unit' service, with no "WC-" default substituted along the way.
 *
 * @covers \WooCommerce\PayPalCommerce\WcGateway\extensions.php
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory
 */
class InvoicePrefixTest extends TestCase
{
	protected $postIds = [];

	/**
	 * @var ContainerInterface|null
	 */
	private $originalContainer;

	/**
	 * @var mixed
	 */
	private $originalSettingsOption;

	public function setUp(): void
	{
		parent::setUp();

		try {
			$this->originalContainer = PPCP::container();
		} catch (LogicException $exception) {
			$this->originalContainer = null;
		}

		$this->originalSettingsOption = get_option(SettingsModel::OPTION_KEY, null);
	}

	public function tearDown(): void
	{
		foreach ($this->postIds as $id) {
			wp_delete_post($id);
		}

		if ($this->originalContainer) {
			PPCP::init($this->originalContainer);
		}

		if (null === $this->originalSettingsOption) {
			delete_option(SettingsModel::OPTION_KEY);
		} else {
			update_option(SettingsModel::OPTION_KEY, $this->originalSettingsOption);
		}

		parent::tearDown();
	}

	/**
	 * GIVEN a merchant who cleared the Invoice Prefix field, persisting an empty string
	 *       in the real 'woocommerce-ppcp-data-settings' option
	 * WHEN a real WooCommerce order is turned into a PayPal purchase unit through the live
	 *      container-resolved 'api.factory.purchase-unit' service
	 * THEN the resulting invoice ID equals the bare order number, with no "WC-" prepended
	 */
	public function test_empty_stored_invoice_prefix_produces_unprefixed_invoice_id(): void
	{
		update_option(SettingsModel::OPTION_KEY, ['invoice_prefix' => '']);

		$container = $this->bootstrap_fresh_container();

		$purchase_unit_factory = $container->get('api.factory.purchase-unit');
		assert($purchase_unit_factory instanceof PurchaseUnitFactory);

		$wc_order = new WC_Order();
		$wc_order->set_currency('USD');
		$wc_order->save();
		$this->postIds[] = $wc_order->get_id();

		$purchase_unit = $purchase_unit_factory->from_wc_order($wc_order);

		$this->assertSame($wc_order->get_order_number(), $purchase_unit->invoice_id());
	}

	/**
	 * Boots a brand-new application container so it resolves 'api.prefix' from the option
	 * written above.
	 *
	 * Container services are memoized for the lifetime of the container instance
	 * (WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Container\ReadOnlyContainer caches
	 * every non-factory service after its first resolution), so reusing the container built at
	 * WordPress boot time could still return whatever invoice prefix was resolved before this
	 * test ran. Rebuilding the container here is what proves the persisted empty string reaches
	 * the factory through the real wiring, rather than a value cached from an earlier request.
	 */
	private function bootstrap_fresh_container(): ContainerInterface
	{
		$bootstrap = require ROOT_DIR . '/bootstrap.php';
		$container = $bootstrap(ROOT_DIR, [], []);

		PPCP::init($container);

		return $container;
	}
}