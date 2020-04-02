<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
    return new SessionModule();
};