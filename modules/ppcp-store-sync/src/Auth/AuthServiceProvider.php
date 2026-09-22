<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Auth;

use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;
class AuthServiceProvider
{
    private ?\WooCommerce\PayPalCommerce\StoreSync\Auth\JwtAuthService $instance = null;
    private ConnectionState $connection_state;
    private \WooCommerce\PayPalCommerce\StoreSync\Auth\PayPalJwkProvider $jwk_provider;
    private MerchantMetadataProvider $metadata_provider;
    public function __construct(ConnectionState $connection_state, \WooCommerce\PayPalCommerce\StoreSync\Auth\PayPalJwkProvider $jwk_provider, MerchantMetadataProvider $metadata_provider)
    {
        $this->connection_state = $connection_state;
        $this->jwk_provider = $jwk_provider;
        $this->metadata_provider = $metadata_provider;
    }
    public function auth_service(): \WooCommerce\PayPalCommerce\StoreSync\Auth\JwtAuthService
    {
        if (!$this->instance) {
            $this->instance = $this->choose_auth_service();
        }
        return $this->instance;
    }
    private function choose_auth_service(): \WooCommerce\PayPalCommerce\StoreSync\Auth\JwtAuthService
    {
        $is_sandbox = $this->connection_state->is_sandbox();
        $use_full_auth = defined('PPCP_AGENTIC_FULL_AUTH') && PPCP_AGENTIC_FULL_AUTH;
        if ($is_sandbox && !$use_full_auth) {
            return new \WooCommerce\PayPalCommerce\StoreSync\Auth\SandboxAuthService($this->jwk_provider, $this->metadata_provider);
        }
        return new \WooCommerce\PayPalCommerce\StoreSync\Auth\JwtAuthService($this->jwk_provider, $this->metadata_provider);
    }
}
