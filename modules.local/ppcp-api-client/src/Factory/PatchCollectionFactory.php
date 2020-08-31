<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Patch;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PatchCollection;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;

class PatchCollectionFactory
{

    public function fromOrders(Order $from, Order $to): PatchCollection
    {
        $allPatches = [];
        $allPatches += $this->purchaseUnits($from->purchaseUnits(), $to->purchaseUnits());

        return new PatchCollection(...$allPatches);
    }

    /**
     * @param PurchaseUnit[] $from
     * @param PurchaseUnit[] $to
     * @return Patch[]
     */
    private function purchaseUnits(array $from, array $to): array
    {
        $patches = [];

        $path = '/purchase_units';
        foreach ($to as $purchaseUnitTo) {
            $needsUpdate = ! count(
                array_filter(
                    $from,
                    static function (PurchaseUnit $unit) use ($purchaseUnitTo): bool {
                        //phpcs:disable WordPress.PHP.StrictComparisons.LooseComparison
                        // Loose comparison needed to compare two objects.
                        return $unit == $purchaseUnitTo;
                        //phpcs:enable WordPress.PHP.StrictComparisons.LooseComparison
                    }
                )
            );
            $needsUpdate = true;
            if (!$needsUpdate) {
                continue;
            }
            $purchaseUnitFrom = current(array_filter(
                $from,
                static function (PurchaseUnit $unit) use ($purchaseUnitTo): bool {
                    return $purchaseUnitTo->referenceId() === $unit->referenceId();
                }
            ));
            $operation = $purchaseUnitFrom ? 'replace' : 'add';
            $value = $purchaseUnitTo->toArray();
            $patches[] = new Patch(
                $operation,
                $path . "/@reference_id=='" . $purchaseUnitTo->referenceId() . "'",
                $value
            );
        }

        return $patches;
    }
}
