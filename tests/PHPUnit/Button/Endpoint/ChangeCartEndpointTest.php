<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;


use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
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
            if (! $singleProductArray['__test_data_is_variation']) {
                $cart
                    ->expects('add_to_cart')
                    ->with($singleProductArray['id'], $singleProductArray['quantity'])
                    ->andReturnTrue();
            }
            if ($singleProductArray['__test_data_is_variation']) {
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

        $testee = new ChangeCartEndpoint(
            $cart,
            $shipping,
            $requestData,
            $purchase_unit_factory,
            $dataStore,
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
            ->with('variable')
            ->andReturn(false);

        $variationProduct = Mockery::mock(\WC_Product::class);
        $variationProduct
            ->shouldReceive('get_id')
            ->andReturn(2);
        $variationProduct
            ->shouldReceive('is_type')
            ->with('variable')
            ->andReturn(true);

        $testData = [
            'default' => [
                [
                    'products' => [
                        [
                            'quantity' => 2,
                            'id' => 1,
                            '__test_data_is_variation' => false,
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
                        '__test_data_is_variation' => false,
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
        ]
        ];

        return $testData;
    }
}
