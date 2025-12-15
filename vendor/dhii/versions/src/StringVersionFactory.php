<?php

declare (strict_types=1);
namespace Dhii\Versions;

use Dhii\Package\Version\StringVersionFactoryInterface;
use Dhii\Package\Version\VersionInterface;
use DomainException;
use Exception;
use RangeException;
use RuntimeException;
use UnexpectedValueException;
/**
 * @inheritDoc
 */
class StringVersionFactory implements StringVersionFactoryInterface
{
    protected const SEP_PRERELEASE = '-';
    protected const SEP_BUILD = '+';
    /**
     * @inheritDoc
     */
    public function createVersionFromString(string $versionString): VersionInterface
    {
        try {
            $components = $this->parseVersion($versionString);
            $version = new \Dhii\Versions\Version($components['major'], $components['minor'], $components['patch'], $components['pre_release'], $components['build']);
        } catch (RangeException $e) {
            throw new DomainException(sprintf('Version string "%1$s" is malformed', $versionString), 0, $e);
        }
        return $version;
    }
    /**
     * Parses a SemVer-compliant version string into components.
     *
     * @param string $version The version string.
     *
     * @return array{
     *  major: int,
     *  minor: int,
     *  patch: int,
     *  pre_release: string[],
     *  build: string[]
     * }
     *
     * @throws DomainException If version string is malformed.
     * @throws Exception If problem parsing.
     */
    protected function parseVersion(string $version): array
    {
        $preReleaseSepPos = ($preReleaseSepPos = strpos($version, static::SEP_PRERELEASE)) !== \false ? $preReleaseSepPos : null;
        $buildSepPos = ($buildSepPos = strpos($version, static::SEP_BUILD)) !== \false ? $buildSepPos : null;
        if ($preReleaseSepPos === 0 || $buildSepPos === 0) {
            throw new DomainException(sprintf('Pre-release or build information in version string "%1$s" must be preceded by at least one version number', $version));
        }
        $preRelease = '';
        $build = '';
        $numbers = $this->getSubstring($version, 0, $preReleaseSepPos ?? $buildSepPos);
        if ($preReleaseSepPos) {
            $preRelease = $this->getSubstring($version, $preReleaseSepPos + 1, $buildSepPos ? $buildSepPos - strlen($numbers) - 1 : $buildSepPos);
        }
        if ($buildSepPos) {
            $build = $this->getSubstring($version, $buildSepPos + 1, null);
        }
        $numbers = strlen($numbers) ? explode('.', $numbers, 3) : [];
        $preRelease = strlen($preRelease) ? explode('.', $preRelease) : [];
        $build = strlen($build) ? explode('.', $build) : [];
        $major = $numbers[0] ?? 0;
        $minor = $numbers[1] ?? 0;
        $patch = $numbers[2] ?? 0;
        if (!is_numeric($major) || !is_numeric($minor) || !is_numeric($patch)) {
            throw new DomainException(sprintf('Major, minor, and patch numbers in version string "%1$s" must be numeric', $version));
        }
        return ['major' => (int) $major, 'minor' => (int) $minor, 'patch' => (int) $patch, 'pre_release' => $preRelease, 'build' => $build];
    }
    /**
     * Retrieves a string from withing a string.
     *
     * @see substr()
     *
     * @param string   $string The string to get a substring from.
     * @param int      $start  The index of the character from which to start the substring, inclusive.
     * @param int|null $length The length of the substring, or `null` to get the remaining characters.
     *
     * @return string The substring.
     *
     * @throws RuntimeException If problem retrieving.
     */
    protected function getSubstring(string $string, int $start, ?int $length): string
    {
        $substring = is_null($length) ? substr($string, $start) : substr($string, $start, $length);
        if (!is_string($substring)) {
            throw new UnexpectedValueException(sprintf('Could not extract a substring of a string "%1$d" chars long, starting from "%2$d" for "%3$d" chars', $string, $start, $length ?? 'null'));
        }
        return $substring;
    }
}
