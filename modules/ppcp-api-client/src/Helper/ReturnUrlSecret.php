<?php

/**
 * Issues and verifies the single-use secret that proves a PayPal return URL was
 * handed out by this shop for a specific PayPal order.
 *
 * The secret travels in the return URL as the `ppcp_return_nonce` query argument
 * and is kept server side against the PayPal order ID. It therefore does not
 * depend on the WC session cookie, on a login, or on the browser that the buyer
 * comes back in.
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

class ReturnUrlSecret
{
    /**
     * Prefix of the transient that holds the secret of a PayPal order.
     */
    private const TRANSIENT_PREFIX = 'ppcp_ru_';
    /**
     * Length of a generated secret.
     */
    private const SECRET_LENGTH = 32;
    /**
     * The secret that was handed to a return URL but is not yet bound to a
     * PayPal order, because the order did not exist when the URL was built.
     */
    private string $pending_secret = '';
    /**
     * Makes a secret for a return URL whose PayPal order does not exist yet.
     *
     * The caller puts the value in the URL. bind() attaches it to the order as
     * soon as PayPal returns the new order ID.
     */
    public function issue_pending(): string
    {
        $this->pending_secret = $this->generate();
        return $this->pending_secret;
    }
    /**
     * Attaches the pending secret to a PayPal order, and clears it.
     *
     * Does nothing when no secret is pending. Order creation that uses no return
     * URL, such as a subscription renewal, thus keeps no transient.
     *
     * @param string $paypal_order_id The ID of the new PayPal order.
     */
    public function bind(string $paypal_order_id): void
    {
        if ('' === $this->pending_secret || '' === $paypal_order_id) {
            return;
        }
        $this->store($paypal_order_id, $this->pending_secret);
        $this->pending_secret = '';
    }
    /**
     * Drops the pending secret, but only when it is the one the caller issued.
     *
     * A builder that hands out an endpoint return URL and then replaces that URL
     * with a custom one uses this to withdraw its secret, so that bind() keeps no
     * transient for a URL that the request payload does not carry. The comparison
     * makes the withdrawal safe: a caller can never drop a secret that a different
     * caller issued in the same request.
     *
     * @param string $secret The secret that the caller issued earlier.
     */
    public function discard_pending(string $secret): void
    {
        if ('' === $secret || '' === $this->pending_secret) {
            return;
        }
        if (hash_equals($this->pending_secret, $secret)) {
            $this->pending_secret = '';
        }
    }
    /**
     * Makes a secret for a PayPal order that already exists, and keeps it.
     *
     * @param string $paypal_order_id The PayPal order ID.
     */
    public function issue_for(string $paypal_order_id): string
    {
        $secret = $this->generate();
        $this->store($paypal_order_id, $secret);
        return $secret;
    }
    /**
     * Gives the secret of a PayPal order, and makes one when none is bound yet.
     *
     * A caller that builds a second return URL for an order which already carries a
     * secret uses this instead of issue_for(), so that both URLs hold the same proof
     * and the transient is written one time only.
     *
     * @param string $paypal_order_id The PayPal order ID.
     */
    public function secret_for(string $paypal_order_id): string
    {
        $stored = get_transient($this->key($paypal_order_id));
        if (is_string($stored) && '' !== $stored) {
            return $stored;
        }
        return $this->issue_for($paypal_order_id);
    }
    /**
     * Tells whether the candidate equals the secret of the PayPal order.
     *
     * @param string $paypal_order_id The PayPal order ID.
     * @param string $candidate       The value that the request carries.
     */
    public function verify(string $paypal_order_id, string $candidate): bool
    {
        if ('' === $candidate) {
            return \false;
        }
        $stored = get_transient($this->key($paypal_order_id));
        if (!is_string($stored) || '' === $stored) {
            return \false;
        }
        return hash_equals($stored, $candidate);
    }
    /**
     * Removes the secret of a PayPal order, so that it works one time only.
     *
     * @param string $paypal_order_id The PayPal order ID.
     */
    public function consume(string $paypal_order_id): void
    {
        delete_transient($this->key($paypal_order_id));
    }
    /**
     * Tells whether a secret is bound to the PayPal order.
     *
     * A return for an order that has no secret is a return for an order that
     * this version of the plugin did not create.
     *
     * @param string $paypal_order_id The PayPal order ID.
     */
    public function has_secret(string $paypal_order_id): bool
    {
        $stored = get_transient($this->key($paypal_order_id));
        return is_string($stored) && '' !== $stored;
    }
    /**
     * Keeps a secret against a PayPal order ID.
     *
     * @param string $paypal_order_id The PayPal order ID.
     * @param string $secret          The secret.
     */
    private function store(string $paypal_order_id, string $secret): void
    {
        set_transient($this->key($paypal_order_id), $secret, DAY_IN_SECONDS);
    }
    private function generate(): string
    {
        return (string) wp_generate_password(self::SECRET_LENGTH, \false);
    }
    /** Gives the transient key of a PayPal order. */
    private function key(string $paypal_order_id): string
    {
        return self::TRANSIENT_PREFIX . $paypal_order_id;
    }
}
