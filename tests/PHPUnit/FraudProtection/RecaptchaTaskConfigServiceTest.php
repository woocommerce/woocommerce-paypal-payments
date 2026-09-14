<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Functions\when;

/**
 * Covers the 'fraud-protection.wc-tasks.recaptcha-task-config' service.
 */
class RecaptchaTaskConfigServiceTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * @return array<int, array<string, string>>
	 */
	private function build_task_config( array $recaptcha_settings ): array {
		when( 'get_option' )->justReturn( $recaptcha_settings );
		when( 'admin_url' )->returnArg();

		$services = require ROOT_DIR . '/modules/ppcp-fraud-protection/services.php';
		$callback = $services['fraud-protection.wc-tasks.recaptcha-task-config'];

		$container = Mockery::mock( ContainerInterface::class );

		return $callback( $container );
	}

	/**
	 * @dataProvider recaptcha_enabled_settings_provider
	 */
	public function test_task_suppressed_when_recaptcha_enabled( array $recaptcha_settings ): void {
		$this->assertSame( array(), $this->build_task_config( $recaptcha_settings ) );
	}

	public function recaptcha_enabled_settings_provider(): array {
		return array(
			'recaptcha enabled via "yes" string'                    => array( array( 'enabled' => 'yes' ) ),
			'recaptcha enabled via numeric "1" string (regression)' => array( array( 'enabled' => '1' ) ),
		);
	}

	/**
	 * @dataProvider recaptcha_disabled_settings_provider
	 */
	public function test_task_surfaced_when_recaptcha_disabled( array $recaptcha_settings ): void {
		$task_config = $this->build_task_config( $recaptcha_settings );

		$this->assertNotSame( array(), $task_config );
		$this->assertSame( 'ppcp-recaptcha-protection-task', $task_config[0]['id'] );
	}

	public function recaptcha_disabled_settings_provider(): array {
		return array(
			'recaptcha disabled via "no" string'              => array( array( 'enabled' => 'no' ) ),
			'recaptcha settings missing defaults to disabled' => array( array() ),
		);
	}
}
