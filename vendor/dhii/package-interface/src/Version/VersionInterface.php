<?php

declare (strict_types=1);
namespace Dhii\Package\Version;

use Exception;
use Stringable;
/**
 * Represents a SemVer-compliant version.
 */
interface VersionInterface extends Stringable
{
    /**
     * Retrieves the version's major number.
     *
     * @return int The major number.
     *
     * @throws Exception If problem retrieving.
     */
    public function getMajor(): int;
    /**
     * Retrieves the version's minor number.
     *
     * @return int The minor number.
     *
     * @throws Exception If problem retrieving.
     */
    public function getMinor(): int;
    /**
     * Retrieves the version's patch number.
     *
     * @return int The patch number.
     *
     * @throws Exception If problem retrieving.
     */
    public function getPatch(): int;
    /**
     * Retrieves the version's pre-release identifier.
     *
     * @return string[] A list of identifiers.
     *                  Each is a non-empty alphanumeric+hyphen string.
     *                  If numeric, has no leading zeroes.
     *
     * @throws Exception If problem retrieving.
     */
    public function getPreRelease(): array;
    /**
     * Retrieves the version's build metadata.
     *
     * @return string[] A series of identifiers.
     *                  Each is a non-empty alphanumeric+hyphen string.
     *
     * @throws Exception If problem retrieving.
     */
    public function getBuild(): array;
}
