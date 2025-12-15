<?php

declare (strict_types=1);
namespace Dhii\Util\String;

use Stringable;
/**
 * Something that is aware of a caption.
 */
interface CaptionAwareInterface
{
    /**
     * Retrieves the caption related to this instance.
     *
     * A caption is a human-readable string that provides a brief description for some element.
     * It typically accompanies some illustration or screen fragment.
     *
     * @return string|Stringable
     */
    public function getCaption();
}
