<?php

/**
 * Data transfer object. Hold the one-time connection details for the OAuth authentication flow.
 *
 * @package WooCommerce\PayPalCommerce\Settings\DTO;
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\DTO;

/**
 * DTO that holds OAuth connection details, that are used to retrieve a
 * permanent client ID/secret later.
 *
 * Intentionally has no internal logic, sanitation or validation.
 */
class OAuthConnectionDTO
{
    /**
     * Whether this connection is a sandbox account.
     *
     * @var bool
     */
    public bool $is_sandbox = \false;
    /**
     * The shared authentication ID.
     *
     * @var string
     */
    public string $shared_id = '';
    /**
     * The authentication token.
     *
     * @var string
     */
    public string $auth_token = '';
    /**
     * Timestamp when the OAuth details were generated.
     *
     * @var int
     */
    public int $timestamp = 0;
    /**
     * Constructor.
     *
     * @param bool   $is_sandbox Whether this connection is a sandbox account.
     * @param string $shared_id  The shared oauth ID.
     * @param string $auth_token The authentication token.
     * @param int    $timestamp  Optional. When the credentials were generated.
     */
    public function __construct(bool $is_sandbox, string $shared_id, string $auth_token, int $timestamp = 0)
    {
        $this->is_sandbox = $is_sandbox;
        $this->shared_id = $shared_id;
        $this->auth_token = $auth_token;
        $this->timestamp = 0 === $timestamp ? time() : $timestamp;
    }
}
