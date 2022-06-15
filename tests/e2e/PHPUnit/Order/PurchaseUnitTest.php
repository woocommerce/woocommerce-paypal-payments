<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\E2e\Order;

use Exception;
use WC_Coupon;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Tests\E2e\TestCase;

class PurchaseUnitTest extends TestCase
{
	protected $postIds = [];

	const CURRENCY = 'EUR';

	public function tearDown(): void
	{
		foreach ($this->postIds as $id) {
			wp_delete_post($id);
		}

		parent::tearDown();
	}

	/**
	 * @dataProvider successData
	 */
    public function testOrder(array $orderData, array $expectedAmount)
    {
		$wcOrder = $this->createWcOrder($orderData);

		$this->container = $this->getContainer();

		$factory = $this->container->get( 'api.factory.purchase-unit' );
		assert($factory instanceof PurchaseUnitFactory);

		$pu = $factory->from_wc_order($wcOrder);
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

		$wcOrder->calculate_totals();
		$wcOrder->save();

		$this->postIds[] = $wcOrder->get_id();

		foreach ($data['coupons'] ?? [] as $couponData) {
			$coupon = new WC_Coupon();
			$coupon->set_amount($couponData['amount']);
			$coupon->set_discount_type($couponData['type']);
			$coupon->set_code(uniqid());
			$coupon->set_virtual(true);
			$coupon->save();

			$this->postIds[] = $coupon->get_id();

			$ret = $wcOrder->apply_coupon($coupon);
			if (is_wp_error($ret)) {
				throw new Exception('Incorrect coupon. ' . $ret->get_error_message());
			}
		}

		$wcOrder->calculate_totals();
		$wcOrder->save();

		return $wcOrder;
	}

	public function successData() {
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
					['price' => 11.99, 'quantity' => 3],
				],
				'shipping' => ['total' => 4.99],
				'billing' => ['city' => 'city1'],
				'coupons' => [
					['amount' => 2.39, 'type' => 'fixed_cart'],
					['amount' => 7.33, 'type' => 'fixed_cart'],
				]
			],
			self::adaptAmountFormat([
				'value' => 34.77,
				'breakdown' => [
					'item_total' => 35.97,
					'tax_total' => 2.76,
					'shipping' => 4.99,
					'discount' => 8.95,
				],
			]),
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
