<?php

declare (strict_types=1);
namespace Dhii\Util\String;

use Stringable;
/**
 * Something that is aware of a description.
 */
interface DescriptionAwareInterface
{
    /**
     * Retrieves the description related to this instance.
     *
     * A description is a human readable string that provides verbose explanation, additional information,
     * instructions, and/or context.
     *
     * @return string|Stringable
     */
    public function getDescription();
}
