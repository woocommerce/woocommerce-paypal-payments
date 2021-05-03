<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
use function Brain\Monkey\Functions\when;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SettingsListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testListen()
    {
        $settings = Mockery::mock(Settings::class);
        $setting_fields = [];
        $webhook_registrar = Mockery::mock(WebhookRegistrar::class);
        $cache = Mockery::mock(Cache::class);
        $state = Mockery::mock(State::class);
        $bearer = Mockery::mock(Bearer::class);

        $testee = new SettingsListener(
            $settings,
            $setting_fields,
            $webhook_registrar,
            $cache,
            $state,
            $bearer
        );

        $_REQUEST['section'] = 'ppcp-gateway';
        $_POST['ppcp-nonce'] = 'foo';
        $_POST['ppcp'] = [
            'client_id' => 'client_id',
        ];
        $_GET['ppcp-tab'] = 'just-a-tab';

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
        $settings->shouldReceive('persist');
        $cache->shouldReceive('has')
            ->andReturn(false);

        $testee->listen();
    }
}
