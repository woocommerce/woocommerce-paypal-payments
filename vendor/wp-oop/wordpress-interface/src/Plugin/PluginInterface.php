<?php

declare (strict_types=1);
namespace WpOop\WordPress\Plugin;

use Dhii\Package\PackageInterface;
use Dhii\Package\Version\VersionInterface;
use Dhii\Util\String\DescriptionAwareInterface;
use Dhii\Util\String\TitleAwareInterface;
use Exception;
/**
 * Represents a WordPress plugin.
 */
interface PluginInterface extends PackageInterface, TitleAwareInterface, DescriptionAwareInterface
{
    /**
     * Retrieves the minimal version of PHP required by this plugin.
     *
     * @return VersionInterface The version.
     *
     * @throws Exception If problem retrieving.
     */
    public function getMinPhpVersion(): VersionInterface;
    /**
     * Retrieves the minimal version of WP required by this plugin.
     *
     * @return VersionInterface The version.
     *
     * @throws Exception If problem retrieving.
     */
    public function getMinWpVersion(): VersionInterface;
    /**
     * Retrieves the text domain of this plugin
     *
     * @return string The text domain.
     *
     * @throws Exception If problem retrieving.
     */
    public function getTextDomain(): string;
    /**
     * Retrieves the basename of this plugin.
     *
     * @return string The basename.
     *                A path to the plugin main file, relative to plugins directory.
     *
     * @throws Exception If problem retrieving.
     */
    public function getBaseName(): string;
}
