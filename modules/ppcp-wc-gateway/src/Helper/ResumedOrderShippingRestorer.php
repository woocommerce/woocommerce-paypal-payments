<?php
/**
 * Restores the shipping line items WooCommerce drops when it resumes a failed order.
 *
 *
 * This helper snapshots the shipping items while they still exist and re-adds them after
 * the rebuild, only when the divergence actually happened.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Psr\Log\LoggerInterface;
use Throwable;
use WC_Order;
use WC_Order_Item_Shipping;

/**
 * ResumedOrderShippingRestorer class.
 */
class ResumedOrderShippingRestorer {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The shipping line items captured before the rebuild, keyed by order ID.
	 *
	 * Lives for the duration of the request only: the snapshot is taken and consumed
	 * within a single checkout submission.
	 *
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	private $snapshots = array();

	/**
	 * ResumedOrderShippingRestorer constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Records the shipping line items of an order that is about to be rebuilt.
	 *
	 * Hooked to `woocommerce_resume_order`, which fires immediately before
	 * WC_Order::remove_order_items() deletes them.
	 *
	 * @param int $order_id The ID of the order being resumed.
	 * @return void
	 */
	public function snapshot( int $order_id ): void {
		try {
			if ( $order_id <= 0 ) {
				return;
			}

			$wc_order = wc_get_order( $order_id );
			if ( ! $wc_order instanceof WC_Order ) {
				return;
			}

			$items = array();

			foreach ( $wc_order->get_shipping_methods() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Shipping ) {
					continue;
				}

				$items[] = array(
					'method_title' => $item->get_method_title(),
					'method_id'    => $item->get_method_id(),
					'instance_id'  => $item->get_instance_id(),
					'total'        => $item->get_total(),
					'total_tax'    => $item->get_total_tax(),
					'taxes'        => $item->get_taxes(),
					'tax_status'   => $item->get_tax_status(),
					'meta'         => $this->meta_of( $item ),
				);
			}

			if ( ! $items ) {
				return;
			}

			$this->snapshots[ $order_id ] = $items;
		} catch ( Throwable $exception ) {
			unset( $this->snapshots[ $order_id ] );

			$this->logger->error(
				sprintf(
					'Could not snapshot the shipping line items of resumed order %1$d: %2$s',
					$order_id,
					$exception->getMessage()
				)
			);
		}
	}

	/**
	 * Re-adds the snapshotted shipping line items when the rebuild dropped them.
	 *
	 * Restoration is best-effort: a failure is logged and swallowed so that checkout and
	 * payment proceed exactly as they do today.
	 *
	 * @param int $order_id The ID of the processed order.
	 * @return void
	 */
	public function restore( int $order_id ): void {
		$shipping_total = '';

		try {
			if ( ! isset( $this->snapshots[ $order_id ] ) ) {
				return;
			}

			$wc_order = wc_get_order( $order_id );
			if ( ! $wc_order instanceof WC_Order ) {
				unset( $this->snapshots[ $order_id ] );

				return;
			}

			$shipping_total = (string) $wc_order->get_shipping_total();
			if ( (float) $shipping_total <= 0.0 ) {
				return;
			}

			if ( $wc_order->get_shipping_methods() ) {
				return;
			}

			foreach ( $this->snapshots[ $order_id ] as $item_data ) {
				$wc_order->add_item( $this->item_from( $item_data ) );
			}

			/**
			 * Only the line items are persisted. The order's shipping total and grand total
			 * were already correct and must stay byte-identical, so no total is recalculated
			 * here: the amount sent to PayPal is unaffected by the restoration.
			 */
			$wc_order->save();

			unset( $this->snapshots[ $order_id ] );
		} catch ( Throwable $exception ) {
			$this->logger->error(
				sprintf(
					'Could not restore the shipping line item of resumed order %1$d, which has a shipping total of %2$s and no shipping line item backing it: %3$s',
					$order_id,
					'' !== $shipping_total ? $shipping_total : 'unknown',
					$exception->getMessage()
				)
			);
		}
	}

	/**
	 * Builds a fresh shipping line item from snapshotted data.
	 *
	 * A new item is built rather than the original object being reused, because the
	 * original row was deleted by WC_Order::remove_order_items() and its ID no longer
	 * exists in the database.
	 *
	 * @param array<string, mixed> $item_data The snapshotted item data.
	 * @return WC_Order_Item_Shipping
	 */
	private function item_from( array $item_data ): WC_Order_Item_Shipping {
		$item = new WC_Order_Item_Shipping();

		$item->set_method_title( (string) ( $item_data['method_title'] ?? '' ) );
		$item->set_method_id( (string) ( $item_data['method_id'] ?? '' ) );
		$item->set_instance_id( (string) ( $item_data['instance_id'] ?? '' ) );
		$item->set_total( (string) ( $item_data['total'] ?? '0' ) );

		/**
		 * WC_Order_Item_Shipping::set_total_tax() is protected; set_taxes() derives the
		 * total tax from the same rate breakdown the original item carried. The snapshotted
		 * tax status needs no counterpart, since WC_Order_Item::get_tax_status() is fixed.
		 */
		$taxes = $item_data['taxes'] ?? array();
		$item->set_taxes( is_array( $taxes ) ? $taxes : array() );

		$meta = $item_data['meta'] ?? array();
		if ( is_array( $meta ) ) {
			foreach ( $meta as $meta_entry ) {
				$item->add_meta_data( (string) $meta_entry['key'], $meta_entry['value'], true );
			}
		}

		return $item;
	}

	/**
	 * Extracts the item meta as plain key/value pairs.
	 *
	 * The WC_Meta_Data objects themselves carry IDs of rows that are about to be deleted,
	 * so only the values are kept.
	 *
	 * @param WC_Order_Item_Shipping $item The shipping line item.
	 * @return array<int, array<string, mixed>>
	 */
	private function meta_of( WC_Order_Item_Shipping $item ): array {
		$meta = array();

		foreach ( $item->get_meta_data() as $meta_data ) {
			$data = $meta_data->get_data();

			if ( ! isset( $data['key'] ) || '' === (string) $data['key'] ) {
				continue;
			}

			$meta[] = array(
				'key'   => (string) $data['key'],
				'value' => $data['value'] ?? '',
			);
		}

		return $meta;
	}
}
