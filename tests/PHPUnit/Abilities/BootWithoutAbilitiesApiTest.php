<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * Boots the module the way a WooCommerce < 10.9 store does — with the plugin
 * autoloader only and no Automattic\WooCommerce\Abilities\AbilityDefinition.
 *
 * PCP-6418 review: AbilitiesModule::run() briefly keyed its handler map by
 * Domain\Get*::get_name(). Those static calls autoload the shells, every shell
 * is declared `implements AbilityDefinition`, and PHP resolves that interface
 * while linking the class — so on any store below WC 10.9 the plugin fataled
 * on plugins_loaded, ahead of both the feature flag and the AbilitiesLoader
 * gate:
 *
 *   PHP Fatal error: Uncaught Error: Interface
 *   "Automattic\WooCommerce\Abilities\AbilityDefinition" not found in
 *   modules/ppcp-abilities/src/Domain/GetConnectionStatus.php
 *
 * This has to run in a subprocess: tests/PHPUnit/bootstrap.php unconditionally
 * requires tests/stubs/AbilityDefinition.php, so inside the normal unit suite
 * the interface always exists and the regression is invisible. The integration
 * suite runs on WC 11 and is blind to it for the same reason.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilitiesModule
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilityNames
 */
class BootWithoutAbilitiesApiTest extends TestCase
{
	/** @var array<int, string> */
	private $lines = array();

	/** @var int */
	private $exit_code = -1;

	public function setUp(): void
	{
		parent::setUp();

		$fixture = __DIR__ . '/fixtures/boot-without-abilities-api.php';
		$this->assertFileExists($fixture);

		$output = array();
		exec(
			escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' 2>&1',
			$output,
			$this->exit_code
		);

		$this->lines = $output;
	}

	/**
	 * @scenario The module boots on a store whose WooCommerce predates the
	 *           Abilities API.
	 *
	 * Given a process where AbilityDefinition is undefined
	 * When AbilitiesModule::run() executes, as it does on plugins_loaded
	 * Then it completes without a fatal and returns true.
	 */
	public function test_module_boots_without_the_wc_10_9_ability_definition_interface(): void
	{
		// Arrange / When: the subprocess ran in setUp(), the way plugins_loaded
		// would boot the module on a real WC < 10.9 install.

		// Then.
		$this->assertSame(
			'no',
			$this->fixture_value('ability_definition_defined'),
			'Precondition: the fixture must run without the WC 10.9 interface, otherwise it proves nothing.'
		);

		$this->assertSame(
			0,
			$this->exit_code,
			"Booting the abilities module below WC 10.9 must not fatal. Fixture output:\n" . $this->fixture_output()
		);
		$this->assertContains('OK', $this->lines, 'The fixture must reach its final marker.');
		$this->assertSame('true', $this->fixture_value('run_returned'));
	}

	/**
	 * @scenario Booting leaves the version-gated shells untouched.
	 *
	 * Given the same subprocess
	 * When run() has returned
	 * Then none of the four Domain shells has been autoloaded — they are
	 *      reachable only through AbilitiesRegistrar::ABILITY_CLASSES, whose
	 *      ::class constants resolve at compile time, after the
	 *      class_exists( AbilitiesLoader::class ) gate.
	 */
	public function test_boot_leaves_the_domain_shells_unloaded(): void
	{
		// Arrange / When: same subprocess as above, already run in setUp().

		// Then.
		foreach (array( 'GetConnectionStatus', 'GetPaymentMethods', 'GetOrderTracking', 'GetPaypalOrder' ) as $shell) {
			$this->assertSame(
				'no',
				$this->fixture_value('declared_' . $shell),
				sprintf(
					'%s was autoloaded during boot. It implements AbilityDefinition, so on WC < 10.9 that is a fatal, not a slow path.',
					$shell
				)
			);
		}
	}

	/**
	 * Reads one `key=value` marker from the fixture's stdout.
	 */
	private function fixture_value(string $key): string
	{
		foreach ($this->lines as $line) {
			if (0 === strpos($line, $key . '=')) {
				return substr($line, strlen($key) + 1);
			}
		}

		$this->fail(sprintf("The fixture printed no %s marker. Output:\n%s", $key, $this->fixture_output()));
	}

	private function fixture_output(): string
	{
		return implode("\n", $this->lines);
	}
}
