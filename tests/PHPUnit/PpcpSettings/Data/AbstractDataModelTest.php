<?php
declare(strict_types=1);

namespace PHPUnit\PpcpSettings\Data;

use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Actions\expectDone;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel
 */
class AbstractDataModelTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();
		when('get_option')->justReturn([]);
	}

	private function create_testee(): TestableDataModel
	{
		return new TestableDataModel();
	}

	/**
	 * GIVEN a listener on the "settings saved" action that calls save() again
	 * WHEN the initial save() dispatches the action
	 * THEN the nested save() still persists the data (a second update_option call)
	 * AND the action is dispatched only once overall, preventing a notification loop
	 */
	public function testReentrantSaveStillPersistsButFiresActionOnlyOnce(): void
	{
		$update_calls = [];
		when('update_option')->alias(function (string $key, $data) use (&$update_calls) {
			$update_calls[] = $data;
			return true;
		});

		$listener_calls = 0;
		expectDone('woocommerce_paypal_payments_settings_saved')
			->once()
			->whenHappen(function ($model) use (&$listener_calls) {
				$listener_calls++;
				$model->save();
			});

		$model = $this->create_testee();
		$model->save();

		$this->assertSame(2, count($update_calls), 'Both the initial and the re-entrant save should persist the data');
		$this->assertSame(1, $listener_calls, 'The saved action must not be dispatched again by the re-entrant save');
	}
}

/**
 * Minimal concrete subclass used only to exercise AbstractDataModel's save()/
 * fire_saved_action() behavior.
 */
class TestableDataModel extends AbstractDataModel
{
	protected const OPTION_KEY = 'test_option_key';

	protected function get_defaults(): array
	{
		return ['foo' => ''];
	}
}
