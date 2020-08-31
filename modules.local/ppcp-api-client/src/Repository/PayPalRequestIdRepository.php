<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;

//phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoSetter
//phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoGetter
class PayPalRequestIdRepository
{
    public const KEY = 'ppcp-request-ids';
    public function getForOrderId(string $orderId): string
    {
        $all = $this->all();
        return isset($all[$orderId]) ? (string) $all[$orderId]['id'] : '';
    }

    public function getForOrder(Order $order): string
    {
        return $this->getForOrderId($order->id());
    }

    public function setForOrder(Order $order, string $requestId): bool
    {
        $all = $this->all();
        $all[$order->id()] = [
            'id' => $requestId,
            'expiration' => time() + 10 * DAY_IN_SECONDS,
        ];
        $all = $this->cleanup($all);
        update_option(self::KEY, $all);
        return true;
    }

    private function all(): array
    {

        return (array) get_option('ppcp-request-ids', []);
    }

    private function cleanup(array $all): array
    {

        foreach ($all as $orderId => $value) {
            if (time() < $value['expiration']) {
                continue;
            }
            unset($all[$orderId]);
        }
        return $all;
    }
}
