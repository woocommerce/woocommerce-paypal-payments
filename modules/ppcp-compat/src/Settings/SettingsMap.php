<?php

/**
 * A map of old to new settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\Settings;

use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
/**
 * A map of old to new settings.
 *
 * @psalm-type newSettingsKey = string
 * @psalm-type oldSettingsKey = string
 */
class SettingsMap
{
    /**
     * The new settings model.
     *
     * @var AbstractDataModel
     */
    private AbstractDataModel $model;
    /**
     * The map of the old setting key to the new setting keys.
     *
     * @var array<oldSettingsKey, newSettingsKey>
     */
    private array $map;
    /**
     * The constructor.
     *
     * @param AbstractDataModel                     $model The new settings model.
     * @param array<oldSettingsKey, newSettingsKey> $map The map of the old setting key to the new setting keys.
     */
    public function __construct(AbstractDataModel $model, array $map)
    {
        $this->model = $model;
        $this->map = $map;
    }
    /**
     * The model.
     *
     * @return AbstractDataModel
     */
    public function get_model(): AbstractDataModel
    {
        return $this->model;
    }
    /**
     * The map of the old setting key to the new setting keys.
     *
     * @return array<oldSettingsKey, newSettingsKey>
     */
    public function get_map(): array
    {
        return $this->map;
    }
}
