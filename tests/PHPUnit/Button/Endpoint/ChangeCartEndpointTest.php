<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;


use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use function Brain\Monkey\Functions\expect;

class ChangeCartEndpointTest extends TestCase
{

    /**
     * @dataProvider dataForTestProducts
     */
    public function testProducts($data, $products, $responseExpectation) {

        $dataStore = Mockery::mock(\WC_Data_Store::class);
        $cart = Mockery::mock(\WC_Cart::class);
        foreach ($data['products'] as $productKey => $singleProductArray) {
            expect('wc_get_product')
                ->once()
                ->with($singleProductArray['id'])
                ->andReturn($products[$productKey]);

            if ($singleProductArray['__test_data_is_variation'] ?? false) {
                $dataStore
                    ->expects('find_matching_product_variation')
                    ->with($products[$productKey], $singleProductArray['__test_data_variation_map'])
                    ->andReturn($singleProductArray['__test_data_variation_id']);
                $cart
                    ->expects('add_to_cart')
                    ->with(
                        $singleProductArray['id'],
                        $singleProductArray['quantity'],
                        $singleProductArray['__test_data_variation_id'],
                        $singleProductArray['__test_data_variation_map']
                    )
                    ->andReturnTrue();
            }
			elseif ($singleProductArray['__test_data_is_booking'] ?? false) {

				$processedBooking = array();
				foreach ($singleProductArray['booking'] as $key => $value) {
					$processedBooking['_processed_' . $key] = $value;
				}

				expect('wc_bookings_get_posted_data')
					->with($singleProductArray['booking'])
					->andReturn($processedBooking);
				$cart
					->expects('add_to_cart')
					->with(
						$singleProductArray['id'],
						$singleProductArray['quantity'],
						0,
						array(),
						array('booking' => $processedBooking)
					)
					->andReturnTrue();
			}
			else {
				$cart
					->expects('add_to_cart')
					->with($singleProductArray['id'], $singleProductArray['quantity'])
					->andReturnTrue();
			}
		}
        $cart
            ->expects('empty_cart')
            ->with(false);
        $shipping = Mockery::mock(\WC_Shipping::class);
        $shipping
            ->expects('reset_shipping');
        $requestData = Mockery::mock(RequestData::class);
        $requestData
            ->expects('read_request')
            ->with(ChangeCartEndpoint::nonce())
            ->andReturn($data);

		$pu = Mockery::mock(PurchaseUnit::class);
		$pu
			->shouldReceive('to_array')
			->andReturn($responseExpectation[0]);
		$purchase_unit_factory = Mockery::mock(PurchaseUnitFactory::class);
		$purchase_unit_factory
            ->expects('from_wc_cart')
            ->andReturn($pu);

		$productsHelper = new CartProductsHelper(
			$dataStore
		);

        $testee = new ChangeCartEndpoint(
            $cart,
            $shipping,
            $requestData,
            $purchase_unit_factory,
			$productsHelper,
			new NullLogger()
        );

        expect('wp_send_json_success')
            ->with($responseExpectation);
        $this->assertTrue($testee->handle_request());
    }

    public function dataForTestProducts() : array {
        $defaultProduct = Mockery::mock(\WC_Product::class);
        $defaultProduct
            ->shouldReceive('get_id')
            ->andReturn(1);
		$defaultProduct
			->shouldReceive('is_type')
			->with('booking')
			->andReturn(false);
        $defaultProduct
            ->shouldReceive('is_type')
            ->with('variable')
            ->andReturn(false);

        $variationProduct = Mockery::mock(\WC_Product::class);
        $variationProduct
            ->shouldReceive('get_id')
            ->andReturn(2);
		$variationProduct
			->shouldReceive('is_type')
			->with('booking')
			->andReturn(false);
        $variationProduct
            ->shouldReceive('is_type')
            ->with('variable')
            ->andReturn(true);

		$bookingData = [
			'_duration' => 2,
			'_start_day' => 12,
			'_start_month' => 6,
			'_start_year' => 2023,
		];

		$bookingProduct = Mockery::mock(\WC_Product::class);
		$bookingProduct
			->shouldReceive('get_id')
			->andReturn(3);
		$bookingProduct
			->shouldReceive('is_type')
			->with('booking')
			->andReturn(true);
		$bookingProduct
			->shouldReceive('is_type')
			->with('variable')
			->andReturn(false);

		$testData = [
            'default' => [
                [
                    'products' => [
                        [
                            'quantity' => 2,
                            'id' => 1,
                        ],
                    ]
                ],
                [
                    $defaultProduct,
                ],
                [
                    [1],
                ]
            ],
            'variation' => [
				[
					'products' => [
						[
							'quantity' => 2,
							'id' => 1,
						],
						[
							'quantity' => 2,
							'id' => 2,
							'variations' => [
								[
									'name' => 'variation-1',
									'value' => 'abc',
								],
								[
									'name' => 'variation-2',
									'value' => 'def',
								],
							],
							'__test_data_is_variation' => true,
							'__test_data_variation_id' => 123,
							'__test_data_variation_map' => [
								'variation-1' => 'abc',
								'variation-2' => 'def',
							]
						],
					]
				],
				[
					$defaultProduct,
					$variationProduct,
				],
				[
					[1, 2]
				]
			],
			'booking' => [
				[
					'products' => [
						[
							'quantity' => 2,
							'id' => 1,
						],
						[
							'quantity' => 1,
							'id' => 3,
							'booking' => $bookingData,
							'__test_data_is_booking' => true,
						],
					]
				],
				[
					$defaultProduct,
					$bookingProduct,
				],
				[
					[1, 3]
				]
			],
        ];

        return $testData;
    }
}
