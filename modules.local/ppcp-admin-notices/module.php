<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
    return new AdminNotices();
};
