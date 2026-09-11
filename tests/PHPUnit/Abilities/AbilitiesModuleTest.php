<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetConnectionStatusHandler;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetOrderTrackingHandler;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetPaymentMethodsHandler;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetPaypalOrderHandler;
use WooCommerce\PayPalCommerce\Abilities\Helper\EnvelopeParser;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpointCached;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CommonRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PaymentRestEndpoint;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;

/**
 * Unit tests for the abilities module's DI wiring.
 *
 * PCP-6418: the module used to declare ServiceModule against an empty
 * services.php and reach its collaborators through PPCP::container() at
 * execute()-time. These tests pin the replacement contract: real service
 * definitions, constructor-injected collaborators, and a run() that uses
 * the container it is handed.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilitiesModule
 */
class AbilitiesModuleTest extends TestCase
{
	const HANDLER_SERVICE_IDS = array(
		'abilities.handler.get-connection-status',
		'abilities.handler.get-payment-methods',
		'abilities.handler.get-order-tracking',
		'abilities.handler.get-paypal-order',
	);

	public function setUp(): void
	{
		parent::setUp();

		AbilityHandlers::set(array());
	}

	public function tearDown(): void
	{
		AbilityHandlers::set(array());

		parent::tearDown();
	}

	/**
	 * @scenario services.php returns a non-empty array registering an abilities.registrar
	 *           service and one handler service per ability.
	 *
	 * Given the abilities module's service definitions
	 * When they are loaded
	 * Then the registrar and all four ability handlers are declared as factories.
	 */
	public function test_services_registers_the_registrar_and_one_handler_per_ability(): void
	{
		// Arrange / When.
		$services = require ROOT_DIR . '/modules/ppcp-abilities/services.php';

		// Then.
		$this->assertIsArray($services);
		$this->assertNotEmpty(
			$services,
			'services.php must declare real services — an empty array is what made the ServiceModule implementation meaningless.'
		);

		$this->assertArrayHasKey('abilities.registrar', $services);
		$this->assertIsCallable($services['abilities.registrar']);

		foreach (self::HANDLER_SERVICE_IDS as $service_id) {
			$this->assertArrayHasKey(
				$service_id,
				$services,
				sprintf('Every ability needs its own handler service; %s is missing.', $service_id)
			);
			$this->assertIsCallable($services[$service_id]);
		}
	}

	/**
	 * @scenario Each handler receives its backing endpoint service and a
	 *           Psr\Log\LoggerInterface through its constructor.
	 *
	 * Given a container exposing the backing endpoints and the plugin logger
	 * When each handler factory is invoked
	 * Then it builds its handler from the injected collaborators, asking the
	 *      container for exactly the ids the module depends on.
	 */
	public function test_handler_factories_receive_their_backing_endpoint_and_a_logger(): void
	{
		// Arrange.
		$requested = array();
		$container = $this->container_double($requested);
		$services  = require ROOT_DIR . '/modules/ppcp-abilities/services.php';

		$expected_classes = array(
			'abilities.handler.get-connection-status' => GetConnectionStatusHandler::class,
			'abilities.handler.get-payment-methods'   => GetPaymentMethodsHandler::class,
			'abilities.handler.get-order-tracking'    => GetOrderTrackingHandler::class,
			'abilities.handler.get-paypal-order'      => GetPaypalOrderHandler::class,
		);

		foreach ($expected_classes as $service_id => $expected_class) {
			// When.
			$handler = $services[$service_id]($container);

			// Then.
			$this->assertInstanceOf($expected_class, $handler);
		}

		// Then: the collaborators came from the container, not from a locator.
		$this->assertContains('settings.rest.common', $requested);
		$this->assertContains('settings.rest.payment', $requested);
		$this->assertContains('order-tracking.endpoint.controller', $requested);
		$this->assertContains('api.endpoint.order.cached', $requested);
		$this->assertContains('abilities.logger', $requested);
	}

	/**
	 * @scenario The module's logger service is the plugin logger when the
	 *           container can build it.
	 *
	 * Given a container exposing woocommerce.logger.woocommerce
	 * When the abilities.logger factory is invoked
	 * Then it hands back that logger untouched.
	 */
	public function test_logger_service_resolves_the_plugin_logger(): void
	{
		// Arrange.
		$plugin_logger = Mockery::mock(LoggerInterface::class);
		$requested     = array();
		$container     = $this->container_double(
			$requested,
			array( 'woocommerce.logger.woocommerce' => $plugin_logger )
		);
		$services = require ROOT_DIR . '/modules/ppcp-abilities/services.php';

		// When.
		$logger = $services['abilities.logger']($container);

		// Then.
		$this->assertSame($plugin_logger, $logger);
		$this->assertContains('woocommerce.logger.woocommerce', $requested);
	}

	/**
	 * @scenario A logger the container cannot build, or hands back in the
	 *           wrong shape, must not take the whole abilities surface down.
	 *
	 * Given a container that either throws while resolving
	 *       woocommerce.logger.woocommerce or resolves it to something that
	 *       is not a PSR-3 logger
	 * When the abilities.logger factory is invoked
	 * Then it returns the plugin's NullLogger rather than propagating the
	 *      failure or passing on a value the handlers' typed constructors
	 *      would reject with a TypeError.
	 *
	 * Without this fallback the failure surfaces from every handler factory,
	 * and AbilityHandlers::callback() turns it into a service_unavailable
	 * WP_Error — so a broken logger, a side channel, would disable all four
	 * abilities. The pre-DI AbilitiesModule::run() caught this case for the
	 * same reason.
	 *
	 * @dataProvider unusable_plugin_logger_provider
	 */
	public function test_logger_service_falls_back_to_the_null_logger_when_the_plugin_logger_is_unusable(callable $configure_container): void
	{
		// Arrange.
		$container = Mockery::mock(ContainerInterface::class);
		$configure_container($container);
		$services = require ROOT_DIR . '/modules/ppcp-abilities/services.php';

		// When.
		$logger = $services['abilities.logger']($container);

		// Then.
		$this->assertInstanceOf(
			NullLogger::class,
			$logger,
			'The fallback must be the plugin NullLogger, not Psr\\Log\\NullLogger.'
		);
	}

	public function unusable_plugin_logger_provider(): array
	{
		return array(
			'container throws while building the plugin logger' => array(
				static function ($container): void {
					$container->shouldReceive('get')->with('woocommerce.logger.woocommerce')->andThrow(
						new \RuntimeException('woocommerce.logger.woocommerce could not be built')
					);
				},
			),
			'container resolves the id to a non-PSR-3-logger value' => array(
				static function ($container): void {
					$container->shouldReceive('get')->with('woocommerce.logger.woocommerce')->andReturn(new \stdClass());
				},
			),
		);
	}

	/**
	 * @scenario AbilitiesModule::run( ContainerInterface $c ) resolves the registrar
	 *           from $c and invokes it on the instance.
	 *
	 * Given a container exposing the registrar service and a bound handler
	 * When the module runs, and the bound ability's callback is then invoked
	 * Then the registrar instance is initialized and invoking the callback
	 *      dispatches to the container-resolved handler, forwarding its input.
	 */
	public function test_run_resolves_the_registrar_from_the_container_and_initializes_it(): void
	{
		// Arrange.
		$registrar = Mockery::mock(AbilitiesRegistrar::class);
		$registrar->shouldReceive('init')->once();
		$registrar->shouldReceive('can_manage_woocommerce')->andReturn(true);

		$handler = Mockery::mock(GetConnectionStatusHandler::class);
		$handler->shouldReceive('execute')
			->once()
			->with(array( 'foo' => 'bar' ))
			->andReturn(array( 'merchant' => array() ));

		$requested  = array();
		$unexpected = array();

		$container = $this->container_double(
			$requested,
			array(
				'abilities.registrar'                     => $registrar,
				'abilities.handler.get-connection-status' => $handler,
			),
			$unexpected
		);

		// When.
		$result = (new AbilitiesModule())->run($container);

		// Then.
		$this->assertTrue($result);
		$this->assertContains(
			'abilities.registrar',
			$requested,
			'run() must resolve the registrar from the injected container instead of calling a static coordinator.'
		);

		// Then: invoking the bound callback dispatches to the container-resolved handler.
		$callback = AbilityHandlers::callback('woocommerce-paypal-payments/get-connection-status');
		$this->assertIsCallable(
			$callback,
			'run() must bind each ability to a callable that reaches the container-resolved handler.'
		);
		$this->assertSame(
			array( 'merchant' => array() ),
			$callback(array( 'foo' => 'bar' )),
			'Invoking the bound callback must dispatch to the handler run() resolved from the container and forward its input.'
		);

		// Then: the id the bound callback resolved matches services.php exactly.
		$this->assertSame(
			array(),
			$unexpected,
			'AbilitiesModule::run() must bind each ability to exactly the handler service id declared in services.php.'
		);
	}

	/**
	 * @scenario Building the ability handler bindings must resolve nothing
	 *           besides the registrar.
	 *
	 * Given a container that serves only abilities.registrar and fails the
	 *       test on any other request
	 * When the module runs
	 * Then it still succeeds — the handler map run() builds is lazy factory
	 *      closures, so no backing endpoint or handler service is resolved
	 *      while merely registering the surface.
	 */
	public function test_run_resolves_only_the_registrar_from_the_container(): void
	{
		// Arrange.
		$registrar = Mockery::mock(AbilitiesRegistrar::class);
		$registrar->shouldReceive('init')->once();

		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldReceive('has')->andReturnUsing(
			static function (string $id): bool {
				return 'abilities.registrar' === $id;
			}
		);
		$container->shouldReceive('get')->andReturnUsing(
			function (string $id) use ($registrar) {
				if ('abilities.registrar' !== $id) {
					$this->fail(sprintf('run() must resolve nothing besides abilities.registrar; got %s.', $id));
				}

				return $registrar;
			}
		);

		// When.
		$result = (new AbilitiesModule())->run($container);

		// Then.
		$this->assertTrue($result);
	}

	/**
	 * @scenario Every ability class the registrar contributes to Woo's loader
	 *           has a handler bound by run().
	 *
	 * Given the classes AbilitiesRegistrar::append_classes() adds to the
	 *       loader — the public contribution surface, not the private
	 *       ABILITY_CLASSES list itself
	 * When AbilitiesModule::run() has bound the ability handlers
	 * Then every one of those classes' get_name() is a key AbilityNames::ALL
	 *      also carries (set equality both ways, so ALL is neither missing an
	 *      entry nor carrying a stale one), and each of those names resolves
	 *      to a bound handler — not the woocommerce_paypal_payments_not_initialized
	 *      WP_Error a fifth ability would get back if it were registered
	 *      without ever being wired into run()'s handler map.
	 */
	public function test_every_contributed_ability_class_has_a_handler_bound_by_run(): void
	{
		// Arrange.
		$contributed_names = array_map(
			static function (string $class): string {
				return $class::get_name();
			},
			(new AbilitiesRegistrar())->append_classes(array())
		);

		$registrar = Mockery::mock(AbilitiesRegistrar::class);
		$registrar->shouldReceive('init')->once();
		$registrar->shouldReceive('can_manage_woocommerce')->andReturn(true);

		$requested  = array();
		$unexpected = array();
		$container  = $this->container_double(
			$requested,
			array( 'abilities.registrar' => $registrar ),
			$unexpected
		);

		// When.
		(new AbilitiesModule())->run($container);

		// Then: AbilityNames::ALL is exactly the set of contributed names.
		$this->assertEqualsCanonicalizing(
			$contributed_names,
			AbilityNames::ALL,
			'AbilityNames::ALL must list exactly the ability names the registrar contributes — no missing and no stale entries.'
		);

		// Then: every contributed name resolves to a bound handler.
		foreach ($contributed_names as $ability_name) {
			$callback = AbilityHandlers::callback($ability_name);
			$result   = $callback();

			$this->assertFalse(
				$result instanceof \WP_Error && 'woocommerce_paypal_payments_not_initialized' === $result->get_error_code(),
				sprintf(
					'%s is registered through the loader but run() bound it no handler; it would fail at runtime with woocommerce_paypal_payments_not_initialized.',
					$ability_name
				)
			);
		}

		// Then: the handler service ids run() bound resolve to exactly the ids
		// services.php declares — a misspelled id in run()'s handler map would
		// otherwise resolve to a WP_Error whose code is not the specific
		// "not_initialized" string checked above, and pass silently.
		$this->assertSame(
			array(),
			$unexpected,
			'run() requested a handler service id that services.php does not define.'
		);
	}

	/**
	 * Container double that records every requested service id and answers
	 * with the supplied overrides, falling back to endpoint/logger doubles.
	 *
	 * Requesting an id this double does not know about is recorded into
	 * $unexpected instead of failing the test from inside the closure: a
	 * caller reached through AbilityHandlers::callback() converts any
	 * Throwable — including a PHPUnit assertion failure raised here — into a
	 * WP_Error, which would otherwise swallow the failure. Callers assert on
	 * $unexpected themselves, after the call, where nothing can catch it.
	 *
	 * @param array<int, string> $requested Filled with the requested ids.
	 * @param array<string, object> $overrides Service id => instance.
	 * @param array<int, string> $unexpected Filled with ids requested that neither
	 *                                        $overrides nor the built-in defaults serve.
	 */
	private function container_double(array &$requested, array $overrides = array(), array &$unexpected = array()): ContainerInterface
	{
		$connection_status_handler = Mockery::mock(GetConnectionStatusHandler::class);
		$connection_status_handler->shouldReceive('execute')->andReturn(array());

		$payment_methods_handler = Mockery::mock(GetPaymentMethodsHandler::class);
		$payment_methods_handler->shouldReceive('execute')->andReturn(array());

		$order_tracking_handler = Mockery::mock(GetOrderTrackingHandler::class);
		$order_tracking_handler->shouldReceive('execute')->andReturn(array());

		$paypal_order_handler = Mockery::mock(GetPaypalOrderHandler::class);
		$paypal_order_handler->shouldReceive('execute')->andReturn(array());

		$defaults = array(
			'settings.rest.common'              => Mockery::mock(CommonRestEndpoint::class),
			'settings.rest.payment'             => Mockery::mock(PaymentRestEndpoint::class),
			'order-tracking.endpoint.controller' => Mockery::mock(OrderTrackingEndpoint::class),
			'api.endpoint.order.cached'         => Mockery::mock(OrderEndpointCached::class),
			'woocommerce.logger.woocommerce'    => Mockery::mock(LoggerInterface::class),
			'abilities.logger'                  => Mockery::mock(LoggerInterface::class),
			'abilities.envelope-parser'         => Mockery::mock(EnvelopeParser::class),
			// Every ability's own handler service, so run()'s lazily-invoked
			// factory closures resolve to a real bound handler rather than
			// null: resolve() memoises whatever a factory returns, including
			// null, so an unserved id would poison the ability's slot for the
			// rest of the request instead of merely failing the one lookup.
			'abilities.handler.get-connection-status' => $connection_status_handler,
			'abilities.handler.get-payment-methods'   => $payment_methods_handler,
			'abilities.handler.get-order-tracking'    => $order_tracking_handler,
			'abilities.handler.get-paypal-order'      => $paypal_order_handler,
		);

		$services = array_merge($defaults, $overrides);

		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldReceive('has')->andReturnUsing(
			static function (string $id) use ($services): bool {
				return isset($services[$id]);
			}
		);
		$container->shouldReceive('get')->andReturnUsing(
			static function (string $id) use ($services, &$requested, &$unexpected) {
				$requested[] = $id;

				if (! isset($services[$id])) {
					$unexpected[] = $id;

					return null;
				}

				return $services[$id];
			}
		);

		return $container;
	}
}
