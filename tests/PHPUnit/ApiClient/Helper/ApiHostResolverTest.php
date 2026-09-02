<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

// PHPUnit evaluates data providers before any test's setUp() runs, so these
// constants must be defined at file scope rather than in setUp(). Guarded
// with the same values ModularTestCase::setUp() uses, to avoid drift or
// redefinition clashes regardless of file-load order within a test run.
!defined('PAYPAL_API_URL') && define('PAYPAL_API_URL', 'https://api-m.paypal.com');
!defined('PAYPAL_SANDBOX_API_URL') && define('PAYPAL_SANDBOX_API_URL', 'https://api-m.sandbox.paypal.com');
!defined('CONNECT_WOO_URL') && define('CONNECT_WOO_URL', 'https://connect.woocommerce.com/ppc');
!defined('CONNECT_WOO_SANDBOX_URL') && define('CONNECT_WOO_SANDBOX_URL', 'https://connect.woocommerce.com/ppcsandbox');

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Helper\ApiHostResolver
 */
class ApiHostResolverTest extends TestCase
{
    /**
     * GIVEN a merchant in a given connection status and environment
     * WHEN the current API host is resolved
     * THEN the host matching that connection status and environment is returned
     *
     * @dataProvider connection_state_provider
     */
    public function test_host_reflects_connection_status_and_environment(
        bool $is_connected,
        bool $is_sandbox,
        string $expected_host
    ): void {
        $connection_state = new ConnectionState($is_connected, new Environment($is_sandbox));

        $testee = new ApiHostResolver($connection_state);

        $this->assertSame($expected_host, $testee->host());
    }

    public function connection_state_provider(): array
    {
        return [
            'connected merchant on sandbox resolves to the live sandbox API'         => [true, true, PAYPAL_SANDBOX_API_URL],
            'connected merchant on production resolves to the live production API'   => [true, false, PAYPAL_API_URL],
            'onboarding merchant on sandbox resolves to the sandbox connect service'  => [false, true, CONNECT_WOO_SANDBOX_URL],
            'onboarding merchant on production resolves to the connect service'       => [false, false, CONNECT_WOO_URL],
        ];
    }

    /**
     * GIVEN a merchant who is not yet connected, resolved once before onboarding completes
     * WHEN the merchant's connection state switches to connected mid-request, as
     * ConnectionState::connect() does right after onboarding finishes
     * AND the host is resolved again on the same resolver instance
     * THEN the second resolution reflects the new connection state instead of returning a
     * host cached from before the switch
     */
    public function test_host_reflects_connection_state_changes_mid_request_without_caching(): void
    {
        $connection_state = new ConnectionState(false, new Environment(false));

        $testee = new ApiHostResolver($connection_state);

        $host_before_connect = $testee->host();

        $connection_state->connect(false);

        $host_after_connect = $testee->host();

        $this->assertSame(CONNECT_WOO_URL, $host_before_connect);
        $this->assertSame(PAYPAL_API_URL, $host_after_connect);
        $this->assertNotSame($host_before_connect, $host_after_connect);
    }
}
