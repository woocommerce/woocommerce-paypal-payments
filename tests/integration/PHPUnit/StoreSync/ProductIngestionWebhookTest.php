<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\StoreSync;

use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;

/**
 * Integration tests for the product ingestion webhook (PCP-6532).
 * These tests make REAL HTTP requests to PayPal's staging ingestion endpoint.
 *
 * The endpoint and merchant URL are used verbatim: the merchant URL is
 * registered on the staging environment and is able to ingest products.
 *
 * @group external
 * @group integration
 */
class ProductIngestionWebhookTest extends TestCase {

	/**
	 * Staging ingestion endpoint, used verbatim per PCP-6532.
	 */
	private const ENDPOINT = 'https://d-staging.joinhoney.com/webhooks/products';

	/**
	 * Merchant URL registered on staging, used verbatim per PCP-6532.
	 */
	private const MERCHANT_URL = 'https://wc-pp.ddev.site';

	/**
	 * GIVEN an empty products array
	 * WHEN the ingestion webhook is called
	 * THEN it responds with a validation failure stating at least one product is required
	 */
	public function test_empty_products_array_is_rejected(): void {
		$result = $this->post_products( array() );

		$body = $result['body'];

		$this->assertFalse( $body['success'] ?? null, 'Empty products array must be rejected (success === false).' );
		$this->assertSame( 'VALIDATION_ERROR', $body['error'] ?? null, 'Error code must be VALIDATION_ERROR.' );
		$this->assertStringContainsString(
			'data/products must NOT have fewer than 1 items',
			$body['message'] ?? '',
			'Validation message must explain that at least one product is required.'
		);
	}

	/**
	 * GIVEN three valid simple products
	 * WHEN the ingestion webhook is called
	 * THEN it accepts them with an HTTP 200 success response
	 */
	public function test_ingest_simple_products_succeeds(): void {
		$products = array(
			array(
				'id'               => 'ITEM-1',
				'item_group_id'    => 'ITEM-1',
				'title'            => 'Whiskey-Tumblers (4pcs)',
				'link'             => 'https://wc-pp.ddev.site/products/item-1',
				'image_link'       => 'no-image',
				'description'      => 'Drink like a pro, with this set of high-quality Whiskey glasses!',
				'price'            => '19.94',
				'availability'     => 'in stock',
				'merchantStoreUrl' => self::MERCHANT_URL,
			),
			array(
				'id'               => 'ITEM-2',
				'item_group_id'    => 'ITEM-2',
				'title'            => 'Sencha Tea-Ceremony Set',
				'link'             => 'https://wc-pp.ddev.site/products/item-2',
				'image_link'       => 'no-image',
				'description'      => 'Reward yourself with the inner peace you deserve (hot water not included in purchase)',
				'price'            => '0',
				'availability'     => 'in stock',
				'merchantStoreUrl' => self::MERCHANT_URL,
			),
			array(
				'id'               => 'ITEM-3',
				'item_group_id'    => 'ITEM-3',
				'title'            => 'Surprise bag',
				'link'             => 'https://wc-pp.ddev.site/products/item-3',
				'image_link'       => 'it-is-a-surprise!',
				'description'      => "Nobody knows what's actually in the bag, until you open it! Note: Ignore the Google reviews",
				'price'            => '32.00',
				'availability'     => 'in stock',
				'merchantStoreUrl' => self::MERCHANT_URL,
			),
		);

		$result = $this->post_products( $products );

		$this->assertSame( 200, $result['status'], 'Ingesting valid simple products must return HTTP 200.' );
		$this->assertTrue( $result['body']['success'] ?? null, 'Ingesting valid simple products must succeed (success === true).' );
	}

	/**
	 * Sends a products payload to the ingestion webhook and returns the
	 * decoded response.
	 *
	 * @param array<int, array<string, string>> $products The products to ingest.
	 *
	 * @return array{status: int, body: array<string, mixed>}
	 */
	private function post_products( array $products ): array {
		$body = array(
			'merchant_url' => self::MERCHANT_URL,
			'products'     => $products,
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => (string) wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->fail(
				'Request to the ingestion webhook failed: ' . $response->get_error_message()
				. ' (check network access to ' . self::ENDPOINT . ').'
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => is_array( $decoded ) ? $decoded : array(),
		);
	}
}
