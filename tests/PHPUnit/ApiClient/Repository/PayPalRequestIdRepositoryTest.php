<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class PayPalRequestIdRepositoryTest extends TestCase
{
	private $testee;

	private $data = [];

	public function setUp(): void
	{
		parent::setUp();

		$this->testee = new PayPalRequestIdRepository();

		when('get_option')->alias(function () {
			return $this->data;
		});
		when('update_option')->alias(function (string $key, array $data) {
			$this->data = $data;
		});
	}

	public function testForOrder()
    {
		$this->testee->set_for_order($this->createPaypalOrder('42'), 'request1');
		$this->testee->set_for_order($this->createPaypalOrder('43'), 'request2');

		self::assertEquals('request1', $this->testee->get_for_order($this->createPaypalOrder('42')));
		self::assertEquals('request2', $this->testee->get_for_order($this->createPaypalOrder('43')));
		self::assertEquals('', $this->testee->get_for_order($this->createPaypalOrder('41')));
    }

	public function testExpiration()
    {
		$this->testee->set_for_order($this->createPaypalOrder('42'), 'request1');
		$this->data['42']['expiration'] = time() - 1;
		$this->testee->set_for_order($this->createPaypalOrder('43'), 'request2');

		self::assertEquals('', $this->testee->get_for_order($this->createPaypalOrder('42')));
		self::assertEquals('request2', $this->testee->get_for_order($this->createPaypalOrder('43')));
    }

	private function createPaypalOrder(string $id): Order {
		$order = Mockery::mock(Order::class);
		$order
			->shouldReceive('id')
			->andReturn($id);
		return $order;
	}
}
