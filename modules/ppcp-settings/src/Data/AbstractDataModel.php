<?php

/**
 * Abstract Data Model Base Class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

use RuntimeException;
/**
 * Abstract class AbstractDataModel
 *
 * Provides a base implementation for data models that can be serialized to and from arrays,
 * and provide persistence capabilities.
 */
abstract class AbstractDataModel
{
    /**
     * Stores the model data.
     *
     * @var array
     */
    protected array $data = array();
    /**
     * Option key for WordPress storage.
     * Must be overridden by the child class!
     */
    protected const OPTION_KEY = '';
    /**
     * Default values for the model.
     * Child classes should override this method to define their default structure.
     *
     * @return array
     */
    abstract protected function get_defaults(): array;
    /**
     * Constructor.
     *
     * @throws RuntimeException If the OPTION_KEY is not defined in the child class.
     */
    public function __construct()
    {
        if (empty(static::OPTION_KEY)) {
            throw new RuntimeException('OPTION_KEY must be defined in child class.');
        }
        $this->data = $this->get_defaults();
        $this->load();
    }
    /**
     * Loads the model data from WordPress options.
     */
    public function load(): void
    {
        $saved_data = get_option(static::OPTION_KEY, array());
        $filtered_data = array_intersect_key((array) $saved_data, $this->data);
        $this->data = array_merge($this->data, $filtered_data);
    }
    /**
     * Saves the model data to WordPress options.
     */
    public function save(): void
    {
        update_option(static::OPTION_KEY, $this->data);
    }
    /**
     * Deletes the settings entry from the WordPress database.
     */
    public function purge(): void
    {
        delete_option(static::OPTION_KEY);
    }
    /**
     * Gets all model data as an array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array_merge(array(), $this->data);
    }
    /**
     * Sets all model data from an array.
     *
     * @param array $data The model data.
     */
    public function from_array(array $data): void
    {
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $this->data)) {
                continue;
            }
            $setter = $this->get_setter_name($key);
            if ($setter && method_exists($this, $setter)) {
                $this->{$setter}($value);
            }
        }
    }
    /**
     * Generates a setter method name for a given key, stripping the prefix from
     * boolean fields (is_, use_, has_).
     *
     * @param int|string $field_key The key for which to generate a setter name.
     *
     * @return string The generated setter method name.
     */
    private function get_setter_name($field_key): string
    {
        if (!is_string($field_key)) {
            return '';
        }
        $prefixes_to_strip = array('is_', 'use_', 'has_');
        $stripped_key = $field_key;
        foreach ($prefixes_to_strip as $prefix) {
            if (str_starts_with($field_key, $prefix)) {
                $stripped_key = substr($field_key, strlen($prefix));
                break;
            }
        }
        return $stripped_key ? "set_{$stripped_key}" : '';
    }
}
