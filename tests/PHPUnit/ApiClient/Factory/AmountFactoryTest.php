<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Helper\CurrencyGetterStub;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartTotals;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money as StoreApiMoney;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class AmountFactoryTest extends TestCase
{
	private $currency = 'EUR';
	private $currencyGetter;

	private $itemFactory;
	private $moneyFactory;
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->itemFactory = Mockery::mock(ItemFactory::class);
		$this->moneyFactory = new MoneyFactory();
		$this->currencyGetter = new CurrencyGetterStub($this->currency);
		$this->testee = new AmountFactory($this->itemFactory, $this->moneyFactory, $this->currencyGetter);
	}

	public function testFromWcCartDefault()
    {
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->shouldReceive('get_subtotal')
            ->andReturn(5);
        $cart
            ->shouldReceive('get_fee_total')
            ->andReturn(3);
        $cart
            ->shouldReceive('get_discount_total')
            ->andReturn(1);
        $cart
            ->shouldReceive('get_shipping_total')
            ->andReturn(2);
        $cart
            ->shouldReceive('get_total_tax')
            ->andReturn(4);

        $woocommerce = Mockery::mock(\WooCommerce::class);
        $session = Mockery::mock(\WC_Session::class);
        when('WC')->justReturn($woocommerce);
        $woocommerce->session = $session;
        $session->shouldReceive('get')->andReturn([]);

        $result = $this->testee->from_wc_cart($cart);
        $this->assertEquals($this->currency, $result->currency_code());
        $this->assertEquals((float) 13, $result->value());
        $this->assertEquals((float) 1, $result->breakdown()->discount()->value());
        $this->assertEquals($this->currency, $result->breakdown()->discount()->currency_code());
        $this->assertEquals((float) 2, $result->breakdown()->shipping()->value());
        $this->assertEquals($this->currency, $result->breakdown()->shipping()->currency_code());
        $this->assertEquals((float) 8, $result->breakdown()->item_total()->value());
        $this->assertEquals($this->currency, $result->breakdown()->item_total()->currency_code());
        $this->assertEquals((float) 4, $result->breakdown()->tax_total()->value());
        $this->assertEquals($this->currency, $result->breakdown()->tax_total()->currency_code());
    }

    public function testFromWcCartNoDiscount()
    {
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->shouldReceive('get_subtotal')
            ->andReturn(5);
		$cart
			->shouldReceive('get_fee_total')
			->andReturn(0);
        $cart
            ->shouldReceive('get_discount_total')
            ->andReturn(0);
        $cart
            ->shouldReceive('get_shipping_total')
            ->andReturn(2);
		$cart
			->shouldReceive('get_total_tax')
			->andReturn(4);

        $woocommerce = Mockery::mock(\WooCommerce::class);
        $session = Mockery::mock(\WC_Session::class);
        when('WC')->justReturn($woocommerce);
        $woocommerce->session = $session;
        $session->shouldReceive('get')->andReturn([]);
        $result = $this->testee->from_wc_cart($cart);
        $this->assertNull($result->breakdown()->discount());
		$this->assertEquals((float) 11, $result->value());
		$this->assertEquals((float) 2, $result->breakdown()->shipping()->value());
		$this->assertEquals((float) 5, $result->breakdown()->item_total()->value());
		$this->assertEquals((float) 4, $result->breakdown()->tax_total()->value());
    }

	/**
	 * The critical invariant: amount.value must always equal the formatted sum of
	 * its breakdown parts. This verifies the fix for the FCI PATCH rejection that
	 * occurred when WC per-item tax rounding caused get_total() to diverge by ±$0.01.
	 *
	 * @dataProvider dataBreakdownRoundingCases
	 */
	public function testFromWcCartTotalAlwaysEqualsBreakdownSum(
		float $subtotal,
		float $fees,
		float $shipping,
		float $tax,
		float $discount
	): void {
		$cart = Mockery::mock( \WC_Cart::class );
		$cart->shouldReceive( 'get_subtotal' )->andReturn( $subtotal );
		$cart->shouldReceive( 'get_fee_total' )->andReturn( $fees );
		$cart->shouldReceive( 'get_shipping_total' )->andReturn( $shipping );
		$cart->shouldReceive( 'get_total_tax' )->andReturn( $tax );
		$cart->shouldReceive( 'get_discount_total' )->andReturn( $discount );

		$woocommerce          = Mockery::mock( \WooCommerce::class );
		$session              = Mockery::mock( \WC_Session::class );
		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );
		$session->shouldReceive( 'get' )->andReturn( [] );

		$result    = $this->testee->from_wc_cart( $cart );
		$breakdown = $result->breakdown();

		$sum = (float) $breakdown->item_total()->value_str()
			+ (float) $breakdown->shipping()->value_str()
			+ (float) $breakdown->tax_total()->value_str();
		if ( $breakdown->discount() ) {
			$sum -= (float) $breakdown->discount()->value_str();
		}
		$expected_total_str = number_format( $sum, 2, '.', '' );

		$this->assertSame(
			$expected_total_str,
			$result->value_str(),
			"amount.value_str() must equal the formatted breakdown sum (PayPal PATCH invariant)"
		);
	}

	public function dataBreakdownRoundingCases(): array
	{
		return [
			// Classic WC tax-rounding edge case: 8% tax on $13.33 = $1.0664.
			// WC rounds to $1.07 for tax but get_total() may accumulate differently.
			'tax_rounding_edge_case'   => [ 13.33, 0.0, 5.00, 1.07, 0.0 ],
			// Three items at $3.336 each: item total rounds differently at cart vs item level.
			'multi_item_tax_rounding'  => [ 10.01, 0.0, 4.99, 1.50, 0.0 ],
			// Discount present; all components interact.
			'with_discount'            => [ 20.00, 2.00, 3.50, 2.25, 1.75 ],
			// Fees and no discount.
			'with_fees_no_discount'    => [ 15.50, 4.50, 6.00, 2.60, 0.0 ],
			// Large order with all components.
			'large_order'              => [ 199.99, 10.00, 12.95, 22.30, 15.00 ],
			// Zero-value shipping (digital goods).
			'digital_no_shipping'      => [ 49.99, 0.0, 0.0, 4.50, 0.0 ],
		];
	}

	/**
	 * Proves that the total is derived from breakdown components, not get_total().
	 * When WC's get_total() is off by 1 cent, the PATCH total must still match
	 * the breakdown so PayPal accepts it.
	 */
	public function testFromWcCartTotalDerivesFromComponentsNotWcGetTotal(): void
	{
		$cart = Mockery::mock( \WC_Cart::class );
		// Components sum to $19.40 (1333 + 500 + 107 = 1940 cents).
		$cart->shouldReceive( 'get_subtotal' )->andReturn( 13.33 );
		$cart->shouldReceive( 'get_fee_total' )->andReturn( 0.0 );
		$cart->shouldReceive( 'get_shipping_total' )->andReturn( 5.00 );
		$cart->shouldReceive( 'get_total_tax' )->andReturn( 1.07 );
		$cart->shouldReceive( 'get_discount_total' )->andReturn( 0.0 );
		// Enforce that get_total() is never consulted — the total is derived solely
		// from breakdown components. If it were called, the test would fail.
		$cart->shouldNotReceive( 'get_total' );

		$woocommerce          = Mockery::mock( \WooCommerce::class );
		$session              = Mockery::mock( \WC_Session::class );
		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );
		$session->shouldReceive( 'get' )->andReturn( [] );

		$result = $this->testee->from_wc_cart( $cart );

		// Total must be $19.40 (from breakdown), not $19.39 (from get_total()).
		$this->assertSame( '19.40', $result->value_str() );
		$this->assertSame( '13.33', $result->breakdown()->item_total()->value_str() );
		$this->assertSame( '5.00', $result->breakdown()->shipping()->value_str() );
		$this->assertSame( '1.07', $result->breakdown()->tax_total()->value_str() );
	}

	/**
	 * @dataProvider dataFromStoreApiCart
	 */
	public function testFromStoreApiCart(
		int $items,
		int $fees,
		int $shipping,
		int $tax,
		int $discount,
		string $expected_total,
		string $expected_items,
		string $expected_shipping,
		string $expected_tax,
		?string $expected_discount
	): void {
		$currency   = 'USD';
		$minor_unit = 2;
		$make       = fn( int $v ) => new StoreApiMoney( (string) $v, $currency, $minor_unit );

		$totals = Mockery::mock( CartTotals::class );
		$totals->shouldReceive( 'total_items' )->andReturn( $make( $items ) );
		$totals->shouldReceive( 'total_fees' )->andReturn( $make( $fees ) );
		$totals->shouldReceive( 'total_shipping' )->andReturn( $make( $shipping ) );
		$totals->shouldReceive( 'total_tax' )->andReturn( $make( $tax ) );
		$totals->shouldReceive( 'total_discount' )->andReturn( $make( $discount ) );
		$totals->shouldReceive( 'total_price' )->andReturn( $make( 0 ) ); // used for currency metadata only

		$result    = $this->testee->from_store_api_cart( $totals );
		$breakdown = $result->breakdown();

		$this->assertSame( $expected_total, $result->value_str(), 'amount.value_str' );
		$this->assertSame( $expected_items, $breakdown->item_total()->value_str(), 'item_total includes fees' );
		$this->assertSame( $expected_shipping, $breakdown->shipping()->value_str(), 'shipping' );
		$this->assertSame( $expected_tax, $breakdown->tax_total()->value_str(), 'tax_total' );
		if ( null === $expected_discount ) {
			$this->assertNull( $breakdown->discount(), 'discount should be null' );
		} else {
			$this->assertSame( $expected_discount, $breakdown->discount()->value_str(), 'discount' );
		}
		// Core invariant: value must equal breakdown sum.
		$sum = (float) $breakdown->item_total()->value_str()
			+ (float) $breakdown->shipping()->value_str()
			+ (float) $breakdown->tax_total()->value_str();
		if ( $breakdown->discount() ) {
			$sum -= (float) $breakdown->discount()->value_str();
		}
		$this->assertSame( $result->value_str(), number_format( $sum, 2, '.', '' ), 'breakdown invariant' );
	}

	public function dataFromStoreApiCart(): array
	{
		return [
			// items=1000¢, fees=0, shipping=500¢, tax=180¢, discount=0 → total=1680¢=$16.80
			'no_fees_no_discount'  => [ 1000, 0, 500, 180, 0, '16.80', '10.00', '5.00', '1.80', null ],
			// Fees included in item_total: items=1000¢ + fees=200¢ = 1200¢
			'with_fees'            => [ 1000, 200, 500, 180, 0, '18.80', '12.00', '5.00', '1.80', null ],
			// Discount reduces total: items=2000¢ + fees=0, shipping=500¢, tax=225¢, discount=150¢
			'with_discount'        => [ 2000, 0, 500, 225, 150, '25.75', '20.00', '5.00', '2.25', '1.50' ],
			// Fees + discount together.
			'fees_and_discount'    => [ 1500, 300, 700, 250, 100, '26.50', '18.00', '7.00', '2.50', '1.00' ],
		];
	}

    public function testFromWcOrderDefault()
    {
        $order = Mockery::mock(\WC_Order::class);
        $unitAmount = Mockery::mock(Money::class);
        $unitAmount
            ->shouldReceive('value')
            ->andReturn(3);

        $item = Mockery::mock(Item::class);
        $item
            ->shouldReceive('quantity')
            ->andReturn(2);
        $item
            ->shouldReceive('unit_amount')
            ->andReturn($unitAmount);
        $this->itemFactory
            ->expects('from_wc_order')
            ->with($order)
            ->andReturn([$item]);

        $order
            ->shouldReceive('get_payment_method')
            ->andReturn(PayPalGateway::ID);

        $order
            ->shouldReceive('get_subtotal')
            ->andReturn(6);
        $order
            ->shouldReceive('get_total_fees')
            ->andReturn(4);
        $order
            ->shouldReceive('get_currency')
            ->andReturn($this->currency);
        $order
            ->shouldReceive('get_shipping_total')
            ->andReturn(1);
        $order
            ->shouldReceive('get_total_tax')
            ->andReturn(2);
        $order
            ->shouldReceive('get_total_discount')
            ->andReturn(3);
        $order
            ->shouldReceive('get_meta')
            ->andReturn(null);

        $result = $this->testee->from_wc_order($order);
        $this->assertEquals((float) 3, $result->breakdown()->discount()->value());
        $this->assertEquals((float) 10, $result->breakdown()->item_total()->value());
        $this->assertEquals((float) 1, $result->breakdown()->shipping()->value());
        $this->assertEquals((float) 10, $result->value());
        $this->assertEquals((float) 2, $result->breakdown()->tax_total()->value());
        $this->assertEquals($this->currency, $result->breakdown()->discount()->currency_code());
        $this->assertEquals($this->currency, $result->breakdown()->item_total()->currency_code());
        $this->assertEquals($this->currency, $result->breakdown()->shipping()->currency_code());
        $this->assertEquals($this->currency, $result->breakdown()->tax_total()->currency_code());
        $this->assertEquals($this->currency, $result->currency_code());
    }

    public function testFromWcOrderDiscountIsNull()
    {
        $order = Mockery::mock(\WC_Order::class);
        $unitAmount = Mockery::mock(Money::class);
        $unitAmount
            ->shouldReceive('value')
            ->andReturn(3);
        $item = Mockery::mock(Item::class);
        $item
            ->shouldReceive('quantity')
            ->andReturn(2);
        $item
            ->shouldReceive('unit_amount')
            ->andReturn($unitAmount);
        $this->itemFactory
            ->expects('from_wc_order')
            ->with($order)
            ->andReturn([$item]);

		$order
			->shouldReceive('get_payment_method')
			->andReturn(PayPalGateway::ID);

        $order
            ->shouldReceive('get_subtotal')
            ->andReturn(6);
		$order
			->shouldReceive('get_total_fees')
			->andReturn(0);
        $order
            ->shouldReceive('get_currency')
            ->andReturn($this->currency);
        $order
            ->shouldReceive('get_shipping_total')
            ->andReturn(1);
		$order
			->shouldReceive('get_total_tax')
			->andReturn(2);
        $order
            ->shouldReceive('get_total_discount')
            ->andReturn(0);
		$order
			->shouldReceive('get_meta')
			->andReturn(null);

        $result = $this->testee->from_wc_order($order);
        $this->assertNull($result->breakdown()->discount());
    }

	/**
	 * Proves that from_wc_order() derives the total from breakdown components
	 * and never calls get_total(). Mirrors the equivalent from_wc_cart() test.
	 */
	public function testFromWcOrderTotalDerivesFromComponentsNotGetTotal(): void
	{
		$order = Mockery::mock( \WC_Order::class );
		// Enforce get_total() is never consulted.
		$order->shouldNotReceive( 'get_total' );
		$order->shouldReceive( 'get_payment_method' )->andReturn( PayPalGateway::ID );
		$order->shouldReceive( 'get_meta' )->andReturn( null );
		$order->shouldReceive( 'get_currency' )->andReturn( $this->currency );
		// subtotal=$13.33, fees=0, shipping=$5.00, tax=$1.07 → total should be $19.40.
		$order->shouldReceive( 'get_subtotal' )->andReturn( 13.33 );
		$order->shouldReceive( 'get_total_fees' )->andReturn( 0.0 );
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 5.00 );
		$order->shouldReceive( 'get_total_tax' )->andReturn( 1.07 );
		$order->shouldReceive( 'get_total_discount' )->andReturn( 0.0 );
		$this->itemFactory->shouldReceive( 'from_wc_order' )->andReturn( [] );

		$result = $this->testee->from_wc_order( $order );

		$this->assertSame( '19.40', $result->value_str() );
		$this->assertSame( '13.33', $result->breakdown()->item_total()->value_str() );
		$this->assertSame( '5.00', $result->breakdown()->shipping()->value_str() );
		$this->assertSame( '1.07', $result->breakdown()->tax_total()->value_str() );
		$this->assertNull( $result->breakdown()->discount() );
	}

	/**
	 * A manually created order with a negative fee
	 * must not be undercharged on the PayPal side. The negative fee is surfaced as
	 * a discount AND filtered out of the items sent to PayPal, so it must not also
	 * be netted out of item_total — otherwise it is counted twice.
	 *
	 * Order: product $100, fee -$10 → real total $90.
	 */
	public function testFromWcOrderNegativeFeeNotDoubleCounted(): void
	{
		$order = Mockery::mock( \WC_Order::class );

		$positiveAmount = Mockery::mock( Money::class );
		$positiveAmount->shouldReceive( 'value' )->andReturn( 100.0 );
		$positiveItem = Mockery::mock( Item::class );
		$positiveItem->shouldReceive( 'quantity' )->andReturn( 1 );
		$positiveItem->shouldReceive( 'unit_amount' )->andReturn( $positiveAmount );

		$negativeAmount = Mockery::mock( Money::class );
		$negativeAmount->shouldReceive( 'value' )->andReturn( -10.0 );
		$negativeItem = Mockery::mock( Item::class );
		$negativeItem->shouldReceive( 'quantity' )->andReturn( 1 );
		$negativeItem->shouldReceive( 'unit_amount' )->andReturn( $negativeAmount );

		$this->itemFactory
			->expects( 'from_wc_order' )
			->with( $order )
			->andReturn( [ $positiveItem, $negativeItem ] );

		$order->shouldReceive( 'get_payment_method' )->andReturn( PayPalGateway::ID );
		$order->shouldReceive( 'get_meta' )->andReturn( null );
		$order->shouldReceive( 'get_currency' )->andReturn( $this->currency );
		$order->shouldReceive( 'get_subtotal' )->andReturn( 100.0 );
		$order->shouldReceive( 'get_total_fees' )->andReturn( -10.0 );
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 0.0 );
		$order->shouldReceive( 'get_total_tax' )->andReturn( 0.0 );
		$order->shouldReceive( 'get_total_discount' )->andReturn( 0.0 );

		$result = $this->testee->from_wc_order( $order );

		// item_total must match the positive item actually sent to PayPal ($100),
		// the negative fee is represented solely as a $10 discount, total stays $90.
		$this->assertSame( '100.00', $result->breakdown()->item_total()->value_str() );
		$this->assertSame( '10.00', $result->breakdown()->discount()->value_str() );
		$this->assertSame( '90.00', $result->value_str() );
	}

	/**
	 * Plugins like WooCommerce Gift Cards apply discounts via WC_Cart::set_total() rather
	 * than coupons or fees. The filter lets them contribute the missing amount so the
	 * PayPal order total matches the actual WC cart total.
	 */
	public function testFromWcCartAppliesExtraDiscountFilter(): void
	{
		$cart = Mockery::mock( \WC_Cart::class );
		$cart->shouldReceive( 'get_subtotal' )->andReturn( 100.0 );
		$cart->shouldReceive( 'get_fee_total' )->andReturn( 0.0 );
		$cart->shouldReceive( 'get_discount_total' )->andReturn( 0.0 );
		$cart->shouldReceive( 'get_shipping_total' )->andReturn( 10.0 );
		$cart->shouldReceive( 'get_total_tax' )->andReturn( 0.0 );

		$woocommerce          = Mockery::mock( \WooCommerce::class );
		$session              = Mockery::mock( \WC_Session::class );
		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );
		$session->shouldReceive( 'get' )->andReturn( [] );

		\Brain\Monkey\Filters\expectApplied( 'woocommerce_paypal_payments_cart_extra_discount' )
			->once()
			->with( 0.0, $cart )
			->andReturn( 25.0 );

		$result    = $this->testee->from_wc_cart( $cart );
		$breakdown = $result->breakdown();

		$this->assertSame( '85.00', $result->value_str() );
		$this->assertSame( '25.00', $breakdown->discount()->value_str() );
		$this->assertSame(
			$result->value_str(),
			number_format(
				(float) $breakdown->item_total()->value_str()
				+ (float) $breakdown->shipping()->value_str()
				+ (float) $breakdown->tax_total()->value_str()
				- (float) $breakdown->discount()->value_str(),
				2, '.', ''
			)
		);
	}

    /**
     * @dataProvider dataFromPayPalResponse
     * @param $response
     */
    public function testFromPayPalResponse($response, $expectsException)
    {
        if ($expectsException) {
            $this->expectException(RuntimeException::class);
        }
        $result = $this->testee->from_paypal_response($response);
        if ($expectsException) {
            return;
        }
        $this->assertEquals($response->value, $result->value());
        $this->assertEquals($response->currency_code, $result->currency_code());
        $breakdown = $result->breakdown();
        if (! isset($response->breakdown)) {
            $this->assertNull($breakdown);
            return;
        }
        if ($breakdown->shipping()) {
            $this->assertEquals($response->breakdown->shipping->value, $breakdown->shipping()->value());
            $this->assertEquals($response->breakdown->shipping->currency_code, $breakdown->shipping()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->shipping));
        }
        if ($breakdown->item_total()) {
            $this->assertEquals($response->breakdown->item_total->value, $breakdown->item_total()->value());
            $this->assertEquals($response->breakdown->item_total->currency_code, $breakdown->item_total()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->item_total));
        }
        if ($breakdown->tax_total()) {
            $this->assertEquals($response->breakdown->tax_total->value, $breakdown->tax_total()->value());
            $this->assertEquals($response->breakdown->tax_total->currency_code, $breakdown->tax_total()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->tax_total));
        }
        if ($breakdown->handling()) {
            $this->assertEquals($response->breakdown->handling->value, $breakdown->handling()->value());
            $this->assertEquals($response->breakdown->handling->currency_code, $breakdown->handling()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->handling));
        }
        if ($breakdown->insurance()) {
            $this->assertEquals($response->breakdown->insurance->value, $breakdown->insurance()->value());
            $this->assertEquals($response->breakdown->insurance->currency_code, $breakdown->insurance()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->insurance));
        }
        if ($breakdown->shipping_discount()) {
            $this->assertEquals($response->breakdown->shipping_discount->value, $breakdown->shipping_discount()->value());
            $this->assertEquals($response->breakdown->shipping_discount->currency_code, $breakdown->shipping_discount()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->shipping_discount));
        }
        if ($breakdown->discount()) {
            $this->assertEquals($response->breakdown->discount->value, $breakdown->discount()->value());
            $this->assertEquals($response->breakdown->discount->currency_code, $breakdown->discount()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->discount));
        }
    }

    public function dataFromPayPalResponse() : array
    {
        return [
            'no_value' => [
                (object) [
                    "currency_code" => "A",
                ],
                true,
            ],
            'no_currency_code' => [
                (object) [
                    "value" => (float) 1,
                ],
                true,
            ],
            'no_value_in_breakdown' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "currency_code" => "B",
                        ],
                    ],
                ],
                true,
            ],
            'no_currency_code_in_breakdown' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                        ],
                    ],
                ],
                true,
            ],
            'default' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_item_total' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                    ],
                ],
                false,
            ],
            'no_tax_total' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_handling' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_insurance' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_shipping_discount' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_discount' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_shipping' => [
            (object) [
                "value" => (float) 1,
                "currency_code" => "A",
                "breakdown" => (object) [
                    "discount" => (object) [
                        "value" => (float) 2,
                        "currency_code" => "B",
                    ],
                    "shipping_discount" => (object) [
                        "value" => (float) 3,
                        "currency_code" => "C",
                    ],
                    "insurance" => (object) [
                        "value" => (float) 4,
                        "currency_code" => "D",
                    ],
                    "handling" => (object) [
                        "value" => (float) 5,
                        "currency_code" => "E",
                    ],
                    "tax_total" => (object) [
                        "value" => (float) 6,
                        "currency_code" => "F",
                    ],
                    "item_total" => (object) [
                        "value" => (float) 8,
                        "currency_code" => "H",
                    ],
                ],
            ],
            false,
            ],
        ];
    }
}
