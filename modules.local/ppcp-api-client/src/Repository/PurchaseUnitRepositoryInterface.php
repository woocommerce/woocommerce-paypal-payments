<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;


use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;

interface PurchaseUnitRepositoryInterface
{

    /**
     * @return PurchaseUnit[]
     */
    public function all() : array;
}