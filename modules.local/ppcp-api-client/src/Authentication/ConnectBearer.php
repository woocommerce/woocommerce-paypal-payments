<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;

class ConnectBearer implements Bearer
{

    public function bearer(): Token
    {
        $data = (object) [
            'created' => time(),
            'expires_in' => 3600,
            'token' => 'token',
        ];
        return new Token($data);
    }
}
