<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;


use Inpsyde\PayPalCommerce\ApiClient\Entity\LineItem;

interface LineItemRepositoryInterface
{

    /**
     * @return LineItem[]
     */
    public function all() : array;
}