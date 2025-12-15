<?php

declare (strict_types=1);
namespace Dhii\Util\String;

use Stringable;
/**
 * Something that is aware of a message.
 */
interface MessageAwareInterface
{
    /**
     * Retrieves the message related to this instance.
     *
     * A message is a human-readable string that provides information. It differs from a description in that it is more
     * intrinsic to the instance. Example: exceptions, notifications, etc.
     *
     * @return string|Stringable
     */
    public function getMessage();
}
