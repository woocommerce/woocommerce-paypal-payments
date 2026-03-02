<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ClearableContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\MutableContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerExceptionInterface;
/**
 * A container for data that is accessible once per init.
 *
 * The {@see init()} method copies data from the internal container into memory,
 * then clears it. The data is still accessible from memory,
 * but no longer from internal container.
 *
 * This is useful for flash data, i.e. data that should only be accessible
 * once per request. If a session-specific persistent container is used
 * as storage, this will become session-based flash data.
 */
class FlashContainer implements MutableContainerInterface, ClearableContainerInterface
{
    /** @var MutableContainerInterface */
    protected $data;
    /** @var string */
    protected $dataKey;
    /** @var array<array-key, scalar> */
    protected $flashData = [];
    /**
     * @param MutableContainerInterface $data The storage.
     * @param string $dataKey The key to be used to store data in the storage.
     */
    public function __construct(MutableContainerInterface $data, string $dataKey)
    {
        $this->data = $data;
        $this->dataKey = $dataKey;
    }
    /**
     * Prepare storage before use.
     *
     * Should be called once before accessing data through this class.
     * Will clear the data for the configured key from the storage.
     */
    public function init(): void
    {
        $this->flashData = $this->data->has($this->dataKey) ? $this->data->get($this->dataKey) : [];
        $this->purgePersistentData();
    }
    /**
     * @inheritDoc
     */
    public function has($key)
    {
        $key = (string) $key;
        return array_key_exists($key, $this->flashData);
    }
    /**
     * @inheritDoc
     *
     * Retrieves the value for the specified key from memory.
     */
    public function get($key)
    {
        if (!array_key_exists($key, $this->flashData)) {
            throw new NotFoundException(sprintf('Flash data not found for key "%1$s"', $key));
        }
        return $this->flashData[$key];
    }
    /**
     * @inheritDoc
     *
     * Assigns the given value to the specified key in memory, and persists this change in storage.
     */
    public function set(string $key, $value): void
    {
        $this->flashData[$key] = $value;
        $this->persist($this->flashData);
    }
    /**
     * @inheritDoc
     *
     * Removes the specified key from memory, and persists this change in storage.
     */
    public function unset(string $key): void
    {
        if (!array_key_exists($key, $this->flashData)) {
            throw new NotFoundException(sprintf('Flash data not found for key "%1$s"', $key));
        }
        unset($this->flashData[$key]);
        $this->persist($this->flashData);
    }
    /**
     * @inheritDoc
     *
     * Clears all of this instance's data from memory.
     */
    public function clear(): void
    {
        $this->flashData = [];
        $this->persist($this->flashData);
    }
    /**
     * Clear data from internal storage.
     */
    protected function purgePersistentData(): void
    {
        $this->data->set($this->dataKey, []);
    }
    /**
     * Persist this instance's data from memory into storage.
     *
     * @param array<array-key, scalar> $data The data to persist.
     */
    protected function persist(array $data): void
    {
        $this->data->set($this->dataKey, $data);
    }
}
