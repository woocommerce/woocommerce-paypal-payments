<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Subscription\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentToken;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;

class PaymentTokenRepository
{

    public const USER_META = 'ppcp-vault-token';
    private $factory;
    private $endpoint;
    public function __construct(
        PaymentTokenFactory $factory,
        PaymentTokenEndpoint $endpoint
    ) {

        $this->factory = $factory;
        $this->endpoint = $endpoint;
    }

    public function forUserId(int $id): ?PaymentToken
    {
        try {
            $token = (array) get_user_meta($id, self::USER_META, true);
            if (! $token || ! isset($token['id'])) {
                return $this->fetchForUserId($id);
            }

            $token = $this->factory->fromArray($token);
            return $token;
        } catch (RuntimeException $error) {
            return null;
        }
    }

    public function deleteToken(int $userId, PaymentToken $token): bool
    {
        delete_user_meta($userId, self::USER_META);
        return $this->endpoint->deleteToken($token);
    }

    private function fetchForUserId(int $id): PaymentToken
    {

        $tokens = $this->endpoint->forUser($id);
        $token = current($tokens);
        $tokenArray = $token->toArray();
        update_user_meta($id, self::USER_META, $tokenArray);
        return $token;
    }
}
