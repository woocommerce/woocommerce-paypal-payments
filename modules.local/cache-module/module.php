<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
    return new CacheModule();
};
