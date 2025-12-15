<?php

declare (strict_types=1);
namespace Dhii\Package;

use Exception;
use Dhii\Package\Version\VersionInterface;
/**
 * Represents a software package.
 */
interface PackageInterface
{
    /**
     * Retrieves the package name.
     *
     * @return string The unique package name.
     *                All lowercase alphanumeric characters, '.', '_', '-'. Also, a '/' is used to separate the vendor.
     *
     * @throws Exception If problem retrieving.
     */
    public function getName(): string;
    /**
     * Retrieves the package version.
     *
     * @return VersionInterface The SemVer-compliant package version.
     *
     * @throws Exception If problem retrieving.
     */
    public function getVersion(): VersionInterface;
    /**
     * Retrieves the path to the package base directory.
     *
     * @return string The absolute path to the base directory of the package.
     *
     * @throws Exception If problem retrieving.
     */
    public function getBaseDir(): string;
}
