<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks\Handler;

trait PrefixTrait
{

    private $prefix = '';

    private function sanitizeCustomId(string $customId): int
    {

        $orderId = str_replace($this->prefix, '', $customId);
        return (int) $orderId;
    }
}
