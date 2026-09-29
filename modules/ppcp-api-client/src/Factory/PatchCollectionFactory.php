<?php

/**
 * The PatchCollection factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Patch;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PatchCollection;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
/**
 * Class PatchCollectionFactory
 */
class PatchCollectionFactory
{
    /**
     * Creates a Patch Collection by comparing two orders.
     *
     * @param Order $from The initial order.
     * @param Order $to The target order.
     *
     * @return PatchCollection
     */
    public function from_orders(Order $from, Order $to): PatchCollection
    {
        $all_patches = array();
        $all_patches += $this->purchase_units($from->purchase_units(), $to->purchase_units());
        return new PatchCollection(...$all_patches);
    }
    /**
     * Returns patches from purchase units diffs.
     *
     * @param PurchaseUnit[] $from The Purchase Units to start with.
     * @param PurchaseUnit[] $to The Purchase Units to end with after patches where applied.
     *
     * @return Patch[]
     */
    private function purchase_units(array $from, array $to): array
    {
        $patches = array();
        $path = '/purchase_units';
        foreach ($to as $purchase_unit_to) {
            $needs_update = !count(array_filter($from, static function (PurchaseUnit $unit) use ($purchase_unit_to): bool {
                // Loose comparison needed to compare two objects.
                // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
                return $unit == $purchase_unit_to;
            }));
            if (!$needs_update) {
                continue;
            }
            $purchase_unit_from = current(array_filter($from, static function (PurchaseUnit $unit) use ($purchase_unit_to): bool {
                return $purchase_unit_to->reference_id() === $unit->reference_id();
            }));
            $operation = $purchase_unit_from ? 'replace' : 'add';
            $value = $purchase_unit_to->to_array();
            if (!isset($value['shipping'])) {
                $shipping = $purchase_unit_from && null !== $purchase_unit_from->shipping() ? $purchase_unit_from->shipping() : null;
                if ($shipping) {
                    $value['shipping'] = $shipping->to_array();
                }
            }
            $patches[] = new Patch($operation, $path . "/@reference_id=='" . $purchase_unit_to->reference_id() . "'", $value);
        }
        return $patches;
    }
}
