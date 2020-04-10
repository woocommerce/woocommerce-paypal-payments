<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule;

use Inpsyde\CacheModule\Provider\CacheProvider;
use Inpsyde\CacheModule\Provider\CacheProviderInterface;

return [
    'cache.provider' => function () : CacheProviderInterface {
        return new CacheProvider();
    },

];
