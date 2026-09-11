<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities;

use ReflectionClass;
use WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Structural guards for PCP-6418.
 *
 * The DI conversion is only durable if the patterns it removed cannot
 * creep back: a service locator instead of injection, an internal REST
 * round trip instead of a direct call, and a stringly-typed class gate.
 * These read the module's own sources, so they fail on the pre-refactor
 * code and stay meaningful afterwards.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar
 * @covers \WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility
 */
class ModuleStructureTest extends TestCase
{
	/**
	 * @scenario No file under modules/ppcp-abilities/ references PPCP::container().
	 *
	 * Given the abilities module sources
	 * When they are scanned for the plugin's service locator
	 * Then no file uses it — collaborators arrive through constructors.
	 */
	public function test_module_sources_contain_no_container_service_locator(): void
	{
		// Arrange / When.
		$offenders = $this->module_files_matching('/PPCP::container\s*\(/');

		// Then.
		$this->assertSame(
			array(),
			$offenders,
			"PPCP::container() is a service locator; every other module injects via \$c in run(). Offending files:\n" . implode("\n", $offenders)
		);
	}

	/**
	 * @scenario set_logger(), logger(), reset_logger_for_testing() and
	 *           resolve_service() are gone from AbstractPpcpAbility.
	 *
	 * Given the shared ability base class
	 * When its method table is inspected
	 * Then none of the static logger or locator seams survive.
	 */
	public function test_abstract_ability_drops_the_static_logger_and_service_locator_seams(): void
	{
		// Arrange.
		$removed = array( 'set_logger', 'logger', 'reset_logger_for_testing', 'resolve_service' );

		// When / Then.
		foreach ($removed as $method) {
			$this->assertFalse(
				method_exists(AbstractPpcpAbility::class, $method),
				sprintf('%s() must not survive the DI conversion — logging and services are injected now.', $method)
			);
		}

		// Then: no mutable static state is left on the base class either.
		$this->assertSame(
			array(),
			(new ReflectionClass(AbstractPpcpAbility::class))->getStaticProperties(),
			'AbstractPpcpAbility must hold no static state.'
		);
	}

	/**
	 * @scenario delegate_to_rest_controller() is removed and no file in the
	 *           module calls rest_do_request().
	 *
	 * Given the abilities module sources
	 * When they are scanned for the internal REST round trip
	 * Then nothing dispatches rest_do_request() and the helper is gone.
	 */
	public function test_module_sources_never_dispatch_rest_do_request(): void
	{
		// When.
		$offenders = $this->module_files_matching('/rest_do_request\s*\(/');

		// Then.
		$this->assertSame(
			array(),
			$offenders,
			"Shape-2 abilities call their injected endpoint services directly; rest_do_request() re-enters the REST stack to reach this plugin's own code. Offending files:\n" . implode("\n", $offenders)
		);
		$this->assertFalse(
			method_exists(AbstractPpcpAbility::class, 'delegate_to_rest_controller'),
			'delegate_to_rest_controller() exists only to serve the removed round trip.'
		);
	}

	/**
	 * @scenario The WC 10.9 loader gate uses class_exists( AbilitiesLoader::class ),
	 *           not a string literal.
	 *
	 * Given the registrar source
	 * When the loader gate is inspected
	 * Then the class is imported and referenced by ::class, with no quoted FQCN left.
	 */
	public function test_loader_gate_uses_an_imported_class_constant_not_a_string_literal(): void
	{
		// Arrange.
		$source = (string) file_get_contents(ROOT_DIR . '/modules/ppcp-abilities/src/AbilitiesRegistrar.php');

		// Then.
		$this->assertMatchesRegularExpression(
			'/use\s+Automattic\\\\WooCommerce\\\\Internal\\\\Abilities\\\\AbilitiesLoader\s*;/',
			$source,
			'New code imports the FQCN so "find usages" and static analysis can see the dependency.'
		);
		$this->assertMatchesRegularExpression(
			'/class_exists\(\s*AbilitiesLoader::class\s*\)/',
			$source
		);
		$this->assertDoesNotMatchRegularExpression(
			"/class_exists\(\s*['\"]/",
			$source,
			'The stringly-typed gate must be gone — a literal FQCN silently rots on a rename.'
		);
	}

	/**
	 * @scenario AbilitiesModule::run() must resolve ability names without ever
	 *           naming a class under Abilities\Domain\.
	 *
	 * Given src/AbilitiesModule.php
	 * When its source is scanned for a Domain\ import or a Domain\Get*-style
	 *      static reference
	 * Then neither form is present. The docblock legitimately mentions
	 *      `Domain\Get*::get_name()` in prose (describing what run() must NOT
	 *      do), so the scan matches concrete code forms only — an actual `use`
	 *      import or a `Domain\GetSomething::` static call — never the bare
	 *      word `Domain\`.
	 */
	public function test_abilities_module_carries_no_domain_reference(): void
	{
		// Arrange.
		$source = (string) file_get_contents(ROOT_DIR . '/modules/ppcp-abilities/src/AbilitiesModule.php');

		// Then.
		$this->assertDoesNotMatchRegularExpression(
			'/use\s+[\w\\\\]*Abilities\\\\Domain\\\\/',
			$source,
			'AbilitiesModule.php must not import anything under Abilities\\Domain\\; the handler map is keyed by AbilityNames constants, and naming a Domain shell here would autoload it ahead of the WC 10.9 gate.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/Domain\\\\Get\w+::/',
			$source,
			'AbilitiesModule.php must not statically reference a Domain\\Get* class — each shell implements AbilityDefinition, an interface absent below WC 10.9.'
		);
	}

	/**
	 * Every PHP file the abilities module ships whose contents match $pattern.
	 *
	 * @return array<int, string> Paths relative to the module root.
	 */
	private function module_files_matching(string $pattern): array
	{
		$root  = ROOT_DIR . '/modules/ppcp-abilities';
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

		$offenders = array();

		foreach ($files as $file) {
			if (! $file->isFile() || 'php' !== $file->getExtension()) {
				continue;
			}

			$contents = (string) file_get_contents($file->getPathname());

			if (preg_match($pattern, $contents)) {
				$offenders[] = str_replace($root . '/', '', $file->getPathname());
			}
		}

		sort($offenders);

		return $offenders;
	}
}
