<?php

/**
 * Interface for settings migration classes.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

/**
 * Interface SettingsMigrationInterface
 *
 * Defines the contract for all settings migration classes.
 */
interface SettingsMigrationInterface
{
    /**
     * Migrates legacy settings to new data structure.
     *
     * @return void
     */
    public function migrate(): void;
}
