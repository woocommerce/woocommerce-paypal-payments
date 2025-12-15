<?php

declare (strict_types=1);
namespace Dhii\Util\String;

use Stringable;
/**
 * Something that is aware of a label.
 */
interface LabelAwareInterface
{
    /**
     * Retrieves the label related to this instance.
     *
     * A label is a relatively short human readable string that can be used to identify or refer to this instance.
     *
     * @return string|Stringable
     */
    public function getLabel();
}
