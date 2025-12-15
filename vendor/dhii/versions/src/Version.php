<?php

declare (strict_types=1);
namespace Dhii\Versions;

use Dhii\Package\Version\VersionInterface;
use DomainException;
use Exception;
use RangeException;
use RuntimeException;
use Stringable;
/**
 * A value object containing information about a SemVer-compliant version.
 */
class Version implements VersionInterface
{
    /**
     * @var int
     */
    protected $major;
    /**
     * @var int
     */
    protected $minor;
    /**
     * @var int
     */
    protected $patch;
    /**
     * @var string[]
     */
    protected $preRelease;
    /**
     * @var string[]
     */
    protected $build;
    /**
     * @param int $major The major version number. See {@see getMajor()}.
     * @param int $minor The minor version number See {@see getMinor()}.
     * @param int $patch The patch version number. See {@see getPatch()}.
     * @param array<string|Stringable> $preRelease A list of pre-release identifiers. See {@see getPreRelease()}.
     * @param array<string|Stringable> $build A list of build identifiers. See {@see getBuild()}.
     *
     * @throws RangeException If an identifier is malformed
     * @throws Exception If problem creating.
     */
    public function __construct(int $major, int $minor, int $patch, array $preRelease, array $build)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
        $this->preRelease = $this->normalizePreRelease($preRelease);
        $this->build = $this->normalizeBuild($build);
    }
    /**
     * @inheritDoc
     */
    public function getMajor(): int
    {
        return $this->major;
    }
    /**
     * @inheritDoc
     */
    public function getMinor(): int
    {
        return $this->minor;
    }
    /**
     * @inheritDoc
     */
    public function getPatch(): int
    {
        return $this->patch;
    }
    /**
     * @inheritDoc
     */
    public function getPreRelease(): array
    {
        return $this->preRelease;
    }
    /**
     * @inheritDoc
     */
    public function getBuild(): array
    {
        return $this->build;
    }
    /**
     * Normalizes an identifier.
     *
     * @param string $identifier The identifier to normalize.
     *                           Must be a non-empty alphanumeric+hyphen string.
     *
     * @return string An identifier with all disallowed characters removed.
     *
     * @throws DomainException If identifier is malformed
     * @throws Exception If problem normalizing.
     */
    protected function normalizeIdentifier(string $identifier): string
    {
        $origIdentifier = $identifier;
        $identifier = $this->replace('![^\d\w-]!', '', $identifier);
        if (!strlen($identifier)) {
            throw new DomainException(sprintf('Identifier "%1$s" normalized to "%2$s" is empty', $origIdentifier, $identifier));
        }
        return $identifier;
    }
    /**
     * Normalizes a series of pre-release identifiers.
     *
     * Will remove all illegal characters.
     *
     * @param iterable|array<string|Stringable> $preRelease The series of identifiers to normalize.
     *                             Each is a non-empty alphanumeric+hyphen string.
     *                             If numeric, leading zeroes are not allowed.
     *
     * @return string[] A series of normalized pre-release identifiers.
     *
     * @throws RangeException If could not normalize.
     * @throws Exception If problem normalizing.
     */
    protected function normalizePreRelease(iterable $preRelease): array
    {
        $normalized = [];
        foreach ($preRelease as $idx => $identifier) {
            $identifier = (string) $identifier;
            try {
                $identifier = $this->normalizeIdentifier($identifier);
            } catch (DomainException $e) {
                throw new RangeException(sprintf('Pre-release identifier #%1$d "%2$s" cannot be normalized', $idx, $identifier), 0, $e);
            }
            if (is_numeric($identifier)) {
                $identifier = (string) intval($identifier);
            }
            $normalized[] = $identifier;
        }
        return $normalized;
    }
    /**
     * Normalizes a series of build identifiers.
     *
     * Will remove all illegal characters.
     *
     * @param iterable|array<string|Stringable> $build The series of identifiers to normalize.
     *                             Each is a non-empty alphanumeric+hyphen string.
     *
     * @return string[] A series of normalized build identifiers.
     *
     * @throws RangeException If could not normalize.
     * @throws Exception If problem normalizing.
     */
    protected function normalizeBuild(iterable $build): array
    {
        $normalized = [];
        foreach ($build as $idx => $identifier) {
            $identifier = (string) $identifier;
            try {
                $identifier = $this->normalizeIdentifier($identifier);
            } catch (DomainException $e) {
                throw new RangeException(sprintf('Build identifier #%1$d "%2$s" cannot be normalized', $idx, $identifier), 0, $e);
            }
            $normalized[] = $identifier;
        }
        return $normalized;
    }
    /**
     * Replaces occurrences of $pattern in $subject.
     *
     * @param string $pattern The pattern to use for replacing.
     * @param string $replacement The replacement.
     * @param string $subject The subject.
     *
     * @return string The result of replacement.
     *
     * @throws Exception If problem replacing.
     */
    protected function replace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);
        if ($result === null) {
            $code = preg_last_error();
            $code = $code ? $code : 0;
            $message = preg_last_error_msg();
            $message = !empty($message) ? $message : 'Could not replace';
            throw new RuntimeException($message, $code);
        }
        return $result;
    }
    /**
     * @inheritDoc
     */
    public function __toString()
    {
        $version = "{$this->major}.{$this->minor}.{$this->patch}";
        if (count($this->preRelease)) {
            $version .= '-' . implode('.', $this->preRelease);
        }
        if (count($this->build)) {
            $version .= '+' . implode('.', $this->build);
        }
        return $version;
    }
}
