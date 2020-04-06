<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;


use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Patch;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PatchCollection;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;

class PatchCollectionFactory
{

    public function fromOrders(Order $from, Order $to) : PatchCollection {
        $allPatches = [];
        $allPatches += $this->purchaseUnits($from->purchaseUnits(), $to->purchaseUnits());

        return new PatchCollection(...$allPatches);
    }

    /**
     * ToDo: This patches the purchaseUnits. The way we do it right now, we simply always patch. This simplifies the
     * process but the drawback is, we always have to send a patch request.
     *
     * @param PurchaseUnit[] $from
     * @param PurchaseUnit[] $to
     * @return Patch[]
     */
    private function purchaseUnits(array $from, array $to) : array {
        $patches = [];

        $path = '/purchase_units';
        foreach ($to as $purchaseUnitTo) {
            $needsUpdate = ! count(
                array_filter(
                    $from,
                    function(PurchaseUnit $unit) use ($purchaseUnitTo) : bool {
                        return $unit == $purchaseUnitTo;
                    }
                )
            );
            if (!$needsUpdate) {
                continue;
            }
            $purchaseUnitFrom = current(array_filter(
                $from,
                function(PurchaseUnit $unit) use ($purchaseUnitTo) : bool
                {
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