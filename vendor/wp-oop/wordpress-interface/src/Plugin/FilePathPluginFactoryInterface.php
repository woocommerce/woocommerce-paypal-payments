<?php

declare (strict_types=1);
namespace WpOop\WordPress\Plugin;

use Exception;
/**
 * Can create a plugin from a plugin file path.
 */
interface FilePathPluginFactoryInterface
{
    /**
     * Creates a plugin, given a plugin file path.
     *
     * @param string $filePath The path to the plugin's main file.
     *
     * @return PluginInterface The new plugin.
     *
     * @throws Exception If problem creating.
     */
    public function createPluginFromFilePath(string $filePath): \WpOop\WordPress\Plugin\PluginInterface;
}
