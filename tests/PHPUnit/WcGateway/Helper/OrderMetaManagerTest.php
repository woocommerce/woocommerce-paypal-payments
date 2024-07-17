<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Mockery;
use WC_Abstract_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order as PayPalOrder;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Brain\Monkey\Actions;

class OrderMetaManagerTest extends TestCase {
    private $wc_order;
    private $pp_order;
    private $manager;

    public function setUp() : void {
        parent::setUp();
        $this->wc_order = Mockery::mock(WC_Abstract_Order::class);
        $this->pp_order = Mockery::mock(PayPalOrder::class);
        $this->manager = new OrderMetaManager($this->wc_order, $this->pp_order);
    }

    /**
     * Tests that the order status is updated correctly when it has changed.
     * Verifies that the new PayPal order status is stored in the WooCommerce order meta data
     * and triggers the appropriate WooCommerce action.
     */
    public function testStatusOperations() {
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus->shouldReceive('name')->andReturn('COMPLETED');
        $this->pp_order->shouldReceive('status')->andReturn($orderStatus);

        $this->wc_order->shouldReceive('get_meta')->with(OrderMetaManager::STATUS_META_KEY)->andReturn('PENDING')->once();
        $this->wc_order->shouldReceive('update_meta_data')->with(OrderMetaManager::STATUS_META_KEY, 'COMPLETED')->once();

        Actions\expectDone('woocommerce_paypal_payments_order_status_changed')->once();

        $result = $this->manager->set_status();
        $this->assertSame($this->manager, $result);

        $this->wc_order->shouldReceive('get_meta')->with(OrderMetaManager::STATUS_META_KEY)->andReturn('COMPLETED')->once();
        $this->assertEquals('COMPLETED', $this->manager->get_status());
    }

    /**
     * Tests that the order status is not updated when it remains the same.
     * Ensures that no redundant update is performed and no WooCommerce action is triggered.
     */
    public function testUpdateStatusWithNoChange() {
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus->shouldReceive('name')->andReturn('COMPLETED');
        $this->pp_order->shouldReceive('status')->andReturn($orderStatus);

        $this->wc_order->shouldReceive('get_meta')->with(OrderMetaManager::STATUS_META_KEY)->andReturn('COMPLETED')->once();
        $this->wc_order->shouldNotReceive('update_meta_data');

        Actions\expectDone('woocommerce_paypal_payments_order_status_changed')->never();

        $result = $this->manager->set_status();
        $this->assertSame($this->manager, $result);
    }

    /**
     * Tests that the meta data is correctly persisted.
     * Verifies that the save_meta_data method is called on the WooCommerce order.
     */
    public function testPersist() {
        $this->wc_order->shouldReceive('save_meta_data')->once();
        $result = $this->manager->persist();
        $this->assertSame($this->manager, $result);
    }
}
