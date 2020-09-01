<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices\Repository;

use Inpsyde\PayPalCommerce\AdminNotices\Entity\Message;

interface RepositoryInterface
{

    /**
     * @return Message[]
     */
    public function currentMessages(): array;
}
