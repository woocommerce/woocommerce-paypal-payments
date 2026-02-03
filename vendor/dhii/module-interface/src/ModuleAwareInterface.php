<?php

declare (strict_types=1);
namespace Dhii\Modular\Module;

/**
 * Something that can have a module instance retrieved.
 *
 * @since 0.2
 */
interface ModuleAwareInterface
{
    /**
     * Retrieves the module that is associated with this instance.
     *
     * @since 0.2
     *
     * @return ModuleInterface|null The module, if applicable; otherwise, null.
     */
    public function getModule(): ?\Dhii\Modular\Module\ModuleInterface;
}
