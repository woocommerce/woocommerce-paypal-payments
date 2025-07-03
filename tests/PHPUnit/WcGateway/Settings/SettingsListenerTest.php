<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingAgreementsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Helper\RedirectorStub;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\Onboarding\State;
use Mockery;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
use function Brain\Monkey\Functions\when;

class SettingsListenerTest extends ModularTestCase
{
	public function setUp(): void
	{
		parent::setUp();
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testListen()
	{
		$settings = Mockery::mock(Settings::class);
		$settings->shouldReceive('set');

		$setting_fields = [];

		$webhook_registrar = Mockery::mock(WebhookRegistrar::class);
		$webhook_registrar->shouldReceive('unregister')->andReturnTrue();
		$webhook_registrar->shouldReceive('register')->andReturnTrue();
		$cache = Mockery::mock(Cache::class);
		$state = Mockery::mock(State::class);
		$state->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
		$bearer = Mockery::mock(Bearer::class);
		$signup_link_cache = Mockery::mock(Cache::class);
		$signup_link_ids = array();
        $pui_status_cache = Mockery::mock(Cache::class);
        $dcc_status_cache = Mockery::mock(Cache::class);
		$billing_agreement_endpoint = Mockery::mock(BillingAgreementsEndpoint::class);
		$subscription_helper = Mockery::mock(SubscriptionHelper::class);
		$logger = Mockery::mock(LoggerInterface::class);
		$client_credentials_cache = Mockery::mock(Cache::class);

		$testee = new SettingsListener(
			$settings,
			$setting_fields,
			$webhook_registrar,
			$cache,
			$state,
			$bearer,
			PayPalGateway::ID,
			$signup_link_cache,
			$signup_link_ids,
            $pui_status_cache,
            $dcc_status_cache,
			new RedirectorStub(),
			'',
			'',
			$billing_agreement_endpoint,
			$logger,
			$client_credentials_cache
		);

		$_GET['section'] = PayPalGateway::ID;
		$_POST['ppcp-nonce'] = 'foo';
		$_POST['ppcp'] = [
			'client_id' => 'client_id',
		];
		$_GET['ppcp-tab'] = PayPalGateway::ID;

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
		$signup_link_cache->shouldReceive('has')
			->andReturn(false);
        $pui_status_cache->shouldReceive('has')
            ->andReturn(false);
        $dcc_status_cache->shouldReceive('has')
            ->andReturn(false);
		$client_credentials_cache->shouldReceive('has')->andReturn(true);
		$client_credentials_cache->shouldReceive('delete');


		$testee->listen();
	}
}
