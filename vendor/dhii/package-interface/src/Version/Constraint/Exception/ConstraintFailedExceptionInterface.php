<?php

declare (strict_types=1);
namespace Dhii\Package\Version\Constraint\Exception;

use Dhii\Validation\Exception\ValidationFailedExceptionInterface;
/**
 * Represents a case when a version does not match a constraint.
 */
interface ConstraintFailedExceptionInterface extends ValidationFailedExceptionInterface
{
}
