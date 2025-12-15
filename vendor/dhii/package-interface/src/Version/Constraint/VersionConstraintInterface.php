<?php

declare (strict_types=1);
namespace Dhii\Package\Version\Constraint;

use Dhii\Package\Version\Constraint\Exception\ConstraintFailedExceptionInterface;
use Dhii\Package\Version\VersionInterface;
use Dhii\Validation\ValidatorInterface;
use Exception;
/**
 * Represents a version constraint.
 */
interface VersionConstraintInterface extends ValidatorInterface
{
    /**
     * Validates a package version.
     *
     * @param VersionInterface|mixed $version The version to validate.
     *
     * @throws ConstraintFailedExceptionInterface If version does not match this constraint.
     * @throws Exception If problem validating.
     */
    public function validate($version): void;
}
