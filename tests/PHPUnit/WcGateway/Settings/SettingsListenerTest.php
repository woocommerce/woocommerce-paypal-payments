<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
use function Brain\Monkey\Functions\when;

class SettingsListenerTest extends TestCase
{
    public function testListen()
    {
        $settings = Mockery::mock(Settings::class);
        $setting_fields = [];
        $webhook_registrar = Mockery::mock(WebhookRegistrar::class);
        $cache = Mockery::mock(Cache::class);
        $state = Mockery::mock(State::class);

        $testee = new SettingsListener(
            $settings,
            $setting_fields,
            $webhook_registrar,
            $cache,
            $state
        );

        $_REQUEST['section'] = 'ppcp-gateway';
        $_POST['ppcp-nonce'] = 'foo';
        $_POST['ppcp'] = [
            'client_id' => 'client_id',
        ];
        when('sanitize_text_field')->justReturn('ppcp-gateway');
        when('wp_unslash')->justReturn('ppcp-gateway');
        when('current_user_can')->justReturn(true);
        when('wp_verify_nonce')->justReturn(true);

        $settings->shouldReceive('has')
            ->with('client_id')
            ->andReturn('client_id');
        $settings->shouldReceive('get')
            ->with('client_id')
            ->andReturn('client_id');
        $settings->shouldReceive('has')
            ->with('client_secret')
            ->andReturn('client_secret');
        $settings->shouldReceive('get')
            ->with('client_secret')
            ->andReturn('client_secret');

        // run
        $testee->listen();

        // assert
        $this->assertTrue(true);
    }
}
