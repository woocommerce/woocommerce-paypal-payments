<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\E2e\Order;

use Exception;
use WC_Cart;
use WC_Coupon;
use WC_Customer;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Product;
use WC_Product_Simple;
use WC_Session;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Tests\E2e\TestCase;

class PurchaseUnitTest extends TestCase
{
	protected $postIds = [];

	protected $container;

	protected $cart;
	protected $customer;
	protected $session;

	/**
	 * @var PurchaseUnitFactory
	 */
	private $puFactory;

	const CURRENCY = 'EUR';

	public function setUp()
	{
		parent::setUp();

		$this->container = $this->getContainer();
		$this->cart = $this->cart();
		$this->customer = $this->customer();
		$this->session = $this->session();

		$this->puFactory = $this->container->get( 'api.factory.purchase-unit' );
		assert($this->puFactory instanceof PurchaseUnitFactory);
	}

	public function tearDown(): void
	{
		foreach ($this->postIds as $id) {
			wp_delete_post($id);
		}

		$this->cart->empty_cart();

		parent::tearDown();
	}

	/**
	 * @dataProvider orderData
	 */
    public function testOrder(array $orderData, array $expectedAmount)
    {
		$wcOrder = $this->createWcOrder($orderData);

		$pu = $this->puFactory->from_wc_order($wcOrder);
		$puData = $pu->to_array();

		self::assertTrue(isset($puData['amount']['breakdown']));

		self::assertEquals($expectedAmount, $puData['amount']);
    }

	/**
	 * @dataProvider cartData
	 */
    public function testCart(array $cartData, array $expectedAmount)
    {
		$this->fillWcCart($this->cart, $this->customer, $this->session, $cartData);

		$pu = $this->puFactory->from_wc_cart($this->cart);
		$puData = $pu->to_array();

		self::assertTrue(isset($puData['amount']['breakdown']));

		self::assertEquals($expectedAmount, $puData['amount']);
    }

	protected function createWcOrder(array $data): WC_Order {
		$wcOrder = new WC_Order();
		$wcOrder->set_currency( $data['currency'] ?? self::CURRENCY);
		$wcOrder->set_prices_include_tax($data['prices_include_tax'] ?? true);

		foreach ($data['items'] as $itemData) {
			$item = new WC_Order_Item_Product();
			$item->set_name($itemData['name'] ?? 'Test product');
			$item->set_quantity($itemData['quantity'] ?? 1);
			$item->set_total((string) ($itemData['price'] * $itemData['quantity'] ?? 1));
			if (isset($itemData['product'])) {
				$product = $this->createWcProduct($itemData['product']);
				$item->set_product($product);
			}
			$wcOrder->add_item($item);
		}

		$wcOrder->set_address(array_merge([
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'company'    => '',
			'email'      => 'jd@example.com',
			'phone'      => '1234567890',
			'address_1'  => '123 st',
			'address_2'  => '',
			'city'       => 'city0',
			'state'      => 'state0',
			'country'    => 'AQ',
			'postcode'   => '12345',
		], $data['billing'] ?? []));

		if (isset($data['shipping'])) {
			$shipping = new WC_Order_Item_Shipping();
			$shipping->set_total((string) $data['shipping']['total']);
			$wcOrder->add_item($shipping);
		}

		foreach ($data['fees'] ?? [] as $ind => $feeData) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name("Test fee $ind");
			$fee->set_amount((string) $feeData['amount']);
			$fee->set_total((string) $feeData['amount']);
			$fee->set_tax_class('');
			$fee->set_tax_status('taxable');

			$wcOrder->add_item($fee);
		}

		$wcOrder->calculate_totals();
		$wcOrder->save();

		$this->postIds[] = $wcOrder->get_id();

		foreach ($data['coupons'] ?? [] as $couponData) {
			$coupon = $this->createWcCoupon($couponData);

			$ret = $wcOrder->apply_coupon($coupon);
			if (is_wp_error($ret)) {
				throw new Exception('Incorrect coupon. ' . $ret->get_error_message());
			}
		}

		$wcOrder->calculate_totals();
		$wcOrder->save();

		return $wcOrder;
	}

	protected function fillWcCart(WC_Cart $cart, WC_Customer $customer, WC_Session $session, array $data): void {
		$cart->empty_cart();

		foreach ($data['products'] as $productData) {
			$product = $this->createWcProduct($productData);

			$cart->add_to_cart($product->get_id(), $productData['quantity'] ?? 1);
		}

		foreach ($data['coupons'] ?? [] as $couponData) {
			$coupon = $this->createWcCoupon($couponData);

			$cart->apply_coupon($coupon->get_code());
		}

		$customer->set_billing_country($data['billing']['country'] ?? 'AQ');
		if (isset($data['billing']['city'])) {
			$customer->set_billing_city($data['billing']['city']);
		}

		$cart->calculate_totals();
	}

	protected function createWcCoupon(array $data): WC_Coupon {
		$coupon = new WC_Coupon();
		$coupon->set_amount($data['amount']);
		$coupon->set_discount_type($data['type']);
		$coupon->set_code(uniqid());
		$coupon->set_virtual(true);
		$coupon->save();

		$this->postIds[] = $coupon->get_id();

		return $coupon;
	}

	protected function createWcProduct(array $data): WC_Product {
		$product = new WC_Product_Simple();
		$product->set_name('Test product ' . rand());
		$product->set_status( 'publish');
		$product->set_regular_price((string) $data['price']);
		$product->set_tax_status('taxable');
		$product->set_tax_class('');

		$product->save();

		$this->postIds[] = $product->get_id();

		return $product;
	}

	public function orderData() {
		yield [
			[
				'items' => [
					['price' => 11.99, 'quantity' => 1],
				],
				'shipping' => ['total' => 4.99],
				'billing' => ['city' => 'city1'],
			],
			self::adaptAmountFormat([
				'value' => 18.44,
				'breakdown' => [
					'item_total' => 11.99,
					'tax_total' => 1.46,
					'shipping' => 4.99,
				],
			]),
		];
		yield [
			[
				'items' => [
					['price' => 11.99, 'quantity' => 3],
				],
				'shipping' => ['total' => 4.99],
				'billing' => ['city' => 'city1'],
			],
			self::adaptAmountFormat([
				'value' => 44.49,
				'breakdown' => [
					'item_total' => 35.97,
					'tax_total' => 3.53,
					'shipping' => 4.99,
				],
			]),
		];
		yield [
			[
				'items' => [
					['price' => 18.0, 'quantity' => 1],
				],
				'shipping' => ['total' => 4.99],
				'billing' => ['city' => 'city1'],
			],
			self::adaptAmountFormat([
				'value' => 24.97,
				'breakdown' => [
					'item_total' => 18.0,
					'tax_total' => 1.98,
					'shipping' => 4.99,
				],
			]),
		];
		yield [
			[
				'items' => [
					['price' => 18.0, 'quantity' => 3],
				],
				'shipping' => ['total' => 4.99],
				'billing' => ['city' => 'city1'],
			],
			self::adaptAmountFormat([
				'value' => 64.08,
				'breakdown' => [
					'item_total' => 54.0,
					'tax_total' => 5.09,
					'shipping' => 4.99,
				],
			]),
		];
		yield [
			[
				'items' => [
					['price' => 11.25, 'quantity' => 3],
				],
				'billing' => ['city' => 'city2'],
			],
			self::adaptAmountFormat([
				'value' => 53.99,
				'breakdown' => [
					'item_total' => 33.75,
					'tax_total' => 20.24,
					'shipping' => 0.0,
				],
			]),
		];
		yield [
			[
				'items' => [
					['price' => 11.99, 'quantity' => 3, 'product' => ['price' => 11.99]],
				],
				'shipping' => ['total' => 4.99],
				'billing' => ['city' => 'city1'],
				'coupons' => [
					['amount' => 2.39, 'type' => 'fixed_cart'],
					['amount' => 7.33, 'type' => 'percent'],
				],
			],
			self::adaptAmountFormat([
				'value' => 39.25,
				'breakdown' => [
					'item_total' => 35.97,
					'tax_total' => 3.12,
					'shipping' => 4.99,
					'discount' => 4.83,
				],
			]),
		];
		yield [
			[
				'items' => [
					['price' => 5.99, 'quantity' => 1],
				],
				'billing' => ['city' => 'city1'],
				'fees' => [
					['amount' => 2.89],
					['amount' => 7.13],
				]
			],
			self::adaptAmountFormat([
				'value' => 17.39,
				'breakdown' => [
					'item_total' => 16.01,
					'tax_total' => 1.38,
					'shipping' => 0.0,
				],
			]),
		];

		yield 'no decimals currency' => [
			[
				'currency' => 'JPY',
				'items' => [
					['price' => 18.0, 'quantity' => 2],
				],
				'shipping' => ['total' => 5.0],
				'billing' => ['city' => 'city2'],
			],
			self::adaptAmountFormat([
				'value' => 66,
				'breakdown' => [
					'item_total' => 36,
					'tax_total' => 25, // 24.60
					'shipping' => 5,
				],
			], 'JPY'),
		];
	}

	public function cartData() {
		yield [
			[
				'products' => [
					['price' => 11.99, 'quantity' => 3],
				],
				'billing' => ['city' => 'city1'],
			],
			self::adaptAmountFormat([
				'value' => 39.07,
				'breakdown' => [
					'item_total' => 35.97,
					'tax_total' => 3.10,
					'shipping' => 0.00,
				],
			], get_woocommerce_currency()),
		];
		yield [
			[
				'products' => [
					['price' => 11.25, 'quantity' => 3],
				],
				'billing' => ['city' => 'city2'],
			],
			self::adaptAmountFormat([
				'value' => 53.99,
				'breakdown' => [
					'item_total' => 33.75,
					'tax_total' => 20.24,
					'shipping' => 0.0,
				],
			], get_woocommerce_currency()),
		];
		yield [
			[
				'products' => [
					['price' => 11.99, 'quantity' => 3],
				],
				'billing' => ['city' => 'city1'],
				'coupons' => [
					['amount' => 2.39, 'type' => 'fixed_cart'],
					['amount' => 7.33, 'type' => 'percent'],
				],
			],
			self::adaptAmountFormat([
				'value' => 33.83,
				'breakdown' => [
					'item_total' => 35.97,
					'tax_total' => 2.69,
					'shipping' => 0.00,
					'discount' => 4.83,
				],
			], get_woocommerce_currency()),
		];
	}

	private static function adaptAmountFormat(array $data, string $currency = null): array {
		if (!$currency) {
			$currency = self::CURRENCY;
		}

		$data['currency_code'] = $currency;
		if (isset($data['breakdown'])) {
			foreach ($data['breakdown'] as $key => $value) {
				$data['breakdown'][$key] = [
					'currency_code' => $currency,
					'value' => $value,
				];
			}
		}

		return $data;
	}
}
