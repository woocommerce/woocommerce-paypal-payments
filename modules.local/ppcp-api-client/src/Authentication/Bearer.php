<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;

interface Bearer
{

    public function bearer(): Token;
}
