<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use WC_Payment_Gateway;

class WcGatewayBase extends WC_Payment_Gateway implements WcGatewayInterface
{
    const ID = 'ppcp-gateway';

    public function __construct()
    {
        $this->id = self::ID;
    }
}
