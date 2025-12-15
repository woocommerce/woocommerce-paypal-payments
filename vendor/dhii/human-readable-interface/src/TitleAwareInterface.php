<?php

declare (strict_types=1);
namespace Dhii\Util\String;

use Stringable;
/**
 * Something that is aware of a title.
 */
interface TitleAwareInterface
{
    /**
     * Retrieves the title related to this instance.
     *
     * A title is a human-readable string that serves as a heading for some content.
     *
     * @return string|Stringable
     */
    public function getTitle();
}
