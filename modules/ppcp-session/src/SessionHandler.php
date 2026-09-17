<?php

/**
 * The Session Handler.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Session;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
/**
 * Class SessionHandler
 */
class SessionHandler
{
    private const SESSION_KEY = 'ppcp';
    /**
     * The Order.
     *
     * @var Order|null
     */
    private $order;
    /**
     * The BN Code.
     *
     * @var string
     */
    private $bn_code = '';
    /**
     * If PayPal responds with INSTRUMENT_DECLINED, we only
     * want to go max. three times through the process of trying again.
     *
     * @var int
     */
    private $insufficient_funding_tries = 0;
    /**
     * The funding source of the current checkout (venmo, ...) or null.
     *
     * @var string|null
     */
    private $funding_source = null;
    /**
     * The checkout form data.
     *
     * @var array
     */
    private $checkout_form = array();
    /**
     * Returns the order.
     *
     * @return Order|null
     */
    public function order()
    {
        $this->load_session();
        do_action('ppcp_session_get_order', $this->order, $this);
        return $this->order;
    }
    /**
     * Replaces the current order.
     *
     * @param Order $order The new order.
     */
    public function replace_order(Order $order): void
    {
        $this->load_session();
        $this->order = $order;
        $this->store_session();
    }
    /**
     * Returns the checkout form data.
     *
     * @return array
     */
    public function checkout_form(): array
    {
        $this->load_session();
        return $this->checkout_form;
    }
    /**
     * Replaces the checkout form data.
     *
     * @param array $checkout_form The checkout form data.
     */
    public function replace_checkout_form(array $checkout_form): void
    {
        $this->load_session();
        $this->checkout_form = $checkout_form;
        $this->store_session();
    }
    /**
     * Returns the BN Code.
     *
     * @return string
     */
    public function bn_code(): string
    {
        $this->load_session();
        return $this->bn_code;
    }
    /**
     * Replaces the BN Code.
     *
     * @param string $bn_code The new BN Code.
     */
    public function replace_bn_code(string $bn_code): void
    {
        $this->load_session();
        $this->bn_code = $bn_code;
        $this->store_session();
    }
    /**
     * Returns the funding source of the current checkout (venmo, ...) or null.
     *
     * @return string|null
     */
    public function funding_source(): ?string
    {
        $this->load_session();
        return $this->funding_source;
    }
    /**
     * Replaces the funding source of the current checkout.
     *
     * @param string|null $funding_source The funding source.
     */
    public function replace_funding_source(?string $funding_source): void
    {
        $this->load_session();
        $this->funding_source = $funding_source;
        $this->store_session();
    }
    /**
     * Returns how many times the customer tried to use the PayPal Gateway in this session.
     *
     * @return int
     */
    public function insufficient_funding_tries(): int
    {
        $this->load_session();
        return $this->insufficient_funding_tries;
    }
    /**
     * Increments the number of tries, the customer has done in this session.
     */
    public function increment_insufficient_funding_tries(): void
    {
        $this->load_session();
        ++$this->insufficient_funding_tries;
        $this->store_session();
    }
    /**
     * Destroys the session data.
     *
     * @return SessionHandler
     */
    public function destroy_session_data(): \WooCommerce\PayPalCommerce\Session\SessionHandler
    {
        $this->order = null;
        $this->bn_code = '';
        $this->insufficient_funding_tries = 0;
        $this->funding_source = null;
        $this->checkout_form = array();
        $this->store_session();
        return $this;
    }
    /**
     * Stores the data into the WC session.
     */
    private function store_session(): void
    {
        WC()->session->set(self::SESSION_KEY, self::make_array($this));
    }
    /**
     * Loads the data from the session.
     */
    private function load_session(): void
    {
        if (isset(WC()->session)) {
            $data = WC()->session->get(self::SESSION_KEY);
        } else {
            $data = array();
        }
        if ($data instanceof \WooCommerce\PayPalCommerce\Session\SessionHandler) {
            $data = self::make_array($data);
        } elseif (!is_array($data)) {
            $data = array();
        }
        $this->order = $data['order'] ?? null;
        if (!$this->order instanceof Order) {
            $this->order = null;
        }
        $this->bn_code = (string) ($data['bn_code'] ?? '');
        $this->insufficient_funding_tries = (int) ($data['insufficient_funding_tries'] ?? '');
        $this->funding_source = $data['funding_source'] ?? null;
        if (!is_string($this->funding_source)) {
            $this->funding_source = null;
        }
        $this->checkout_form = $data['checkout_form'] ?? array();
    }
    /**
     * Converts given SessionHandler object into an array.
     *
     * @param SessionHandler $obj The object to convert.
     * @return array
     */
    private static function make_array(\WooCommerce\PayPalCommerce\Session\SessionHandler $obj): array
    {
        return array('order' => $obj->order, 'bn_code' => $obj->bn_code, 'insufficient_funding_tries' => $obj->insufficient_funding_tries, 'funding_source' => $obj->funding_source, 'checkout_form' => $obj->checkout_form);
    }
}
