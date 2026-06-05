<?php

/**
 * Plan Factory.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Plan;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class PlanFactory
 */
class PlanFactory
{
    /**
     * Billing cycle factory.
     *
     * @var BillingCycleFactory
     */
    private $billing_cycle_factory;
    /**
     * Payment preferences factory.
     *
     * @var PaymentPreferencesFactory
     */
    private $payment_preferences_factory;
    /**
     * PlanFactory constructor.
     *
     * @param BillingCycleFactory       $billing_cycle_factory Billing cycle factory.
     * @param PaymentPreferencesFactory $payment_preferences_factory Payment preferences factory.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Factory\BillingCycleFactory $billing_cycle_factory, \WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentPreferencesFactory $payment_preferences_factory)
    {
        $this->billing_cycle_factory = $billing_cycle_factory;
        $this->payment_preferences_factory = $payment_preferences_factory;
    }
    /**
     * Returns a Plan from PayPal response.
     *
     * @param stdClass $data The data.
     *
     * @return Plan
     *
     * @throws RuntimeException If it could not create Plan.
     */
    public function from_paypal_response(stdClass $data): Plan
    {
        if (!isset($data->id)) {
            throw new RuntimeException('No id for given plan');
        }
        if (!isset($data->name)) {
            throw new RuntimeException('No name for plan given');
        }
        if (!isset($data->product_id)) {
            throw new RuntimeException('No product id for given plan');
        }
        if (!isset($data->billing_cycles)) {
            throw new RuntimeException('No billing cycles for given plan');
        }
        $billing_cycles = array();
        foreach ($data->billing_cycles as $billing_cycle) {
            $billing_cycles[] = $this->billing_cycle_factory->from_paypal_response($billing_cycle);
        }
        $payment_preferences = $this->payment_preferences_factory->from_paypal_response($data->payment_preferences);
        return new Plan($data->id, $data->name, $data->product_id, $billing_cycles, $payment_preferences, $data->status ?? '');
    }
}
