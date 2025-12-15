<?php

/**
 * Extracts plugin info from plugin file path.
 *
 * @package WooCommerce\PayPalCommerce
 *
 * @phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
 * @phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce;

use Dhii\Package\Version\StringVersionFactoryInterface;
use Dhii\Package\Version\VersionInterface;
use Exception;
use RuntimeException;
use UnexpectedValueException;
use WpOop\WordPress\Plugin\FilePathPluginFactoryInterface;
use WpOop\WordPress\Plugin\PluginInterface;
/**
 * Extracts plugin info from plugin file path.
 */
class FilePathPluginFactory implements FilePathPluginFactoryInterface
{
    /**
     * The version factory.
     *
     * @var StringVersionFactoryInterface
     */
    protected $version_factory;
    /**
     * FilePathPluginFactory constructor.
     *
     * @param StringVersionFactoryInterface $version_factory The version factory.
     */
    public function __construct(StringVersionFactoryInterface $version_factory)
    {
        $this->version_factory = $version_factory;
    }
    /**
     * Extracts plugin info from plugin file path.
     *
     * @param string $filePath The plugin file path.
     */
    public function createPluginFromFilePath(string $filePath): PluginInterface
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException(sprintf('Plugin file "%1$s" does not exist or is not readable', $filePath));
        }
        $default_headers = array('Name' => 'Plugin Name', 'PluginURI' => 'Plugin URI', 'Version' => 'Version', 'Description' => 'Description', 'TextDomain' => 'Text Domain', 'RequiresWP' => 'Requires at least', 'RequiresPHP' => 'Requires PHP', 'RequiresPlugins' => 'Requires Plugins');
        $plugin_data = \get_file_data($filePath, $default_headers, 'plugin');
        if (empty($plugin_data)) {
            throw new UnexpectedValueException(sprintf('Plugin file "%1$s" does not have a valid plugin header', $filePath));
        }
        $plugin_data = array_merge(array('Name' => '', 'Version' => '0.1.0-alpha1+default', 'Title' => '', 'Description' => '', 'TextDomain' => '', 'RequiresWP' => '6.5', 'RequiresPHP' => '7.4'), $plugin_data);
        $base_dir = dirname($filePath);
        $base_name = plugin_basename($filePath);
        $slug = $this->get_plugin_slug($base_name);
        $text_domain = !empty($plugin_data['TextDomain']) ? $plugin_data['TextDomain'] : $slug;
        return new \WooCommerce\PayPalCommerce\Plugin($plugin_data['Name'], $this->create_version($plugin_data['Version']), $base_dir, $base_name, $plugin_data['PluginURI'], $plugin_data['Description'], $text_domain, $this->create_version($plugin_data['RequiresPHP']), $this->create_version($plugin_data['RequiresWP']));
    }
    /**
     * Creates a new version from a version string.
     *
     * @param string $version_string The SemVer-compliant version string.
     *
     * @return VersionInterface The new version.
     *
     * @throws Exception If version string is malformed.
     */
    protected function create_version(string $version_string): VersionInterface
    {
        return $this->version_factory->createVersionFromString($version_string);
    }
    /**
     * Retrieves a plugin slug from its basename.
     *
     * @param string $base_name The plugin's basename.
     *
     * @return string The plugin's slug.
     */
    protected function get_plugin_slug(string $base_name): string
    {
        $directory_separator = '/';
        // If plugin is in a directory, use directory name.
        if (strstr($base_name, $directory_separator) !== \false) {
            $parts = explode($directory_separator, $base_name);
            if ($parts) {
                return $parts[0];
            }
        }
        // If plugin is not in a directory, return plugin file basename.
        return basename($base_name);
    }
}
