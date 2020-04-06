<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


class PatchCollection
{

    private $patches;
    public function __construct(Patch ...$patches)
    {
        $this->patches = $patches;
    }

    /**
     * @return Patch[]
     */
    public function patches() : array
    {
        return $this->patches;
    }

    public function toArray() : array
    {
        return array_map(
            function(Patch $patch) : array {
                return $patch->toArray();
            },
            $this->patches()
        );
    }
}