<?php

declare (strict_types=1);
namespace Dhii\Validation;

use Dhii\Validation\Exception\ValidationFailedExceptionInterface;
use RuntimeException;
/**
 * Something that can validate a value.
 */
interface ValidatorInterface
{
    /**
     * Validates a value.
     *
     * @param mixed $value The subject of validation.
     *
     * @throws RuntimeException                                    If problem validating.
     * @throws ValidationFailedExceptionInterface                  If validation failed. Must extend {@see RuntimeException}.
     */
    public function validate($value): void;
}
