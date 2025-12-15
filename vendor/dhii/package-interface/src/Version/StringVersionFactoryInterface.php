<?php

declare (strict_types=1);
namespace Dhii\Package\Version;

use DomainException;
use Exception;
/**
 * Represents a factory that can create a version from a version string.
 */
interface StringVersionFactoryInterface
{
    /**
     * Creates a new version from a version string.
     *
     * @param string $version The SemVer compatible version string.
     *
     * @return VersionInterface The new version.
     *
     * @throws DomainException If version string is malformed.
     * @throws Exception If problem creating.
     */
    public function createVersionFromString(string $version): \Dhii\Package\Version\VersionInterface;
}
