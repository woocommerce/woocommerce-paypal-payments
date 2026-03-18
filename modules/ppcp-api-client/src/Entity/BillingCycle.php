<?php

/**
 * The Billing Cycle object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class BillingCycle
 */
class BillingCycle
{
    /**
     * Frequency.
     *
     * @var array
     */
    private $frequency;
    /**
     * Sequence.
     *
     * @var int
     */
    private $sequence;
    /**
     * Tenure Type.
     *
     * @var string
     */
    private $tenure_type;
    /**
     * Pricing scheme.
     *
     * @var array
     */
    private $pricing_scheme;
    /**
     * Total cycles.
     *
     * @var int
     */
    private $total_cycles;
    /**
     * BillingCycle constructor.
     *
     * @param array  $frequency Frequency.
     * @param int    $sequence Sequence.
     * @param string $tenure_type Tenure type.
     * @param array  $pricing_scheme Pricing scheme.
     * @param int    $total_cycles Total cycles.
     */
    public function __construct(array $frequency, int $sequence, string $tenure_type, array $pricing_scheme = array(), int $total_cycles = 1)
    {
        $this->frequency = $frequency;
        $this->sequence = $sequence;
        $this->tenure_type = $tenure_type;
        $this->pricing_scheme = $pricing_scheme;
        $this->total_cycles = $total_cycles;
    }
    /**
     * Returns frequency.
     *
     * @return array
     */
    public function frequency(): array
    {
        return $this->frequency;
    }
    /**
     * Returns sequence.
     *
     * @return int
     */
    public function sequence(): int
    {
        return $this->sequence;
    }
    /**
     * Returns tenure type.
     *
     * @return string
     */
    public function tenure_type(): string
    {
        return $this->tenure_type;
    }
    /**
     * Returns pricing scheme.
     *
     * @return array
     */
    public function pricing_scheme(): array
    {
        return $this->pricing_scheme;
    }
    /**
     * Return total cycles.
     *
     * @return int
     */
    public function total_cycles(): int
    {
        return $this->total_cycles;
    }
    /**
     * Returns Billing Cycle as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array('frequency' => $this->frequency(), 'sequence' => $this->sequence(), 'tenure_type' => $this->tenure_type(), 'pricing_scheme' => $this->pricing_scheme(), 'total_cycles' => $this->total_cycles());
    }
}
