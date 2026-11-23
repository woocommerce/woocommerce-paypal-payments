<?php

/**
 * The Plan object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Plan
 */
class Plan
{
    /**
     * Plan ID.
     *
     * @var string
     */
    private $id;
    /**
     * Plan name.
     *
     * @var string
     */
    private $name;
    /**
     * Product ID.
     *
     * @var string
     */
    private $product_id;
    /**
     * Billing cycles.
     *
     * @var array
     */
    private $billing_cycles;
    /**
     * Payment preferences.
     *
     * @var PaymentPreferences
     */
    private $payment_preferences;
    /**
     * Plan status.
     *
     * @var string
     */
    private $status;
    /**
     * Plan constructor.
     *
     * @param string             $id Plan ID.
     * @param string             $name Plan name.
     * @param string             $product_id Product ID.
     * @param array              $billing_cycles Billing cycles.
     * @param PaymentPreferences $payment_preferences Payment preferences.
     * @param string             $status Plan status.
     */
    public function __construct(string $id, string $name, string $product_id, array $billing_cycles, \WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentPreferences $payment_preferences, string $status = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->product_id = $product_id;
        $this->billing_cycles = $billing_cycles;
        $this->payment_preferences = $payment_preferences;
        $this->status = $status;
    }
    /**
     * Returns Plan ID.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }
    /**
     * Returns Plan name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }
    /**
     * Returns Product ID.
     *
     * @return string
     */
    public function product_id(): string
    {
        return $this->product_id;
    }
    /**
     * Returns Billing cycles.
     *
     * @return array
     */
    public function billing_cycles(): array
    {
        return $this->billing_cycles;
    }
    /**
     * Returns Payment preferences.
     *
     * @return PaymentPreferences
     */
    public function payment_preferences(): \WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentPreferences
    {
        return $this->payment_preferences;
    }
    /**
     * Returns Plan status.
     *
     * @return string
     */
    public function status(): string
    {
        return $this->status;
    }
    /**
     * Returns Plan as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array('id' => $this->id(), 'name' => $this->name(), 'product_id' => $this->product_id(), 'billing_cycles' => $this->billing_cycles(), 'payment_preferences' => $this->payment_preferences(), 'status' => $this->status());
    }
}
