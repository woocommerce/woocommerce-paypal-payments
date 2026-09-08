<?php

/**
 * Binds ability definition shells to their container-resolved handlers.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Abilities;

use Throwable;
/**
 * Woo Core's AbilitiesLoader calls get_name()/get_registration_args()
 * statically, so the definition shells need one module-wide way back to their
 * DI-resolved handlers. AbilitiesModule::run() populates this map; the shells
 * read it. Nothing else in the module holds static state.
 *
 * @internal
 */
class AbilityHandlers
{
    /**
     * Ability name => handler instance, or a factory resolving to one.
     *
     * @var array<string, object|callable>
     */
    private static $handlers = array();
    /**
     * Shared permission gate for every ability.
     *
     * @var callable|null
     */
    private static $permission_callback = null;
    /**
     * Bind the ability handlers and the shared permission gate. Replaces the
     * previous binding wholesale, so a re-run rebinds rather than accumulates.
     *
     * @param array<string, object|callable> $handlers            Ability name => handler or factory.
     * @param callable|null                  $permission_callback Shared permission gate.
     * @return void
     */
    public static function set(array $handlers, ?callable $permission_callback = null): void
    {
        self::$handlers = $handlers;
        self::$permission_callback = $permission_callback;
    }
    /**
     * The execute callback for an ability.
     *
     * Deliberately a closure rather than array( $handler, 'execute' ): the
     * shells call this from get_registration_args(), which Woo's loader runs
     * inside `wp_abilities_api_init`. Resolving the handler there would
     * (a) let a container failure escape into an action shared with Core's own
     * ability registration, and (b) build every backing endpoint on requests
     * that never invoke an ability — including cron and WP-CLI, where
     * api.endpoint.order.cached reaches WC()->session through
     * SessionHandler::bn_code(). The closure defers both to invocation time and
     * degrades to a WP_Error, exactly as the removed resolve_service() did.
     *
     * Signature matches the removed static execute( $input = null ) so the
     * registered callback's arity is unchanged.
     *
     * @param string $ability_name Fully namespaced ability name.
     * @return callable
     */
    public static function callback(string $ability_name): callable
    {
        return static function ($input = null) use ($ability_name) {
            try {
                $handler = self::resolve($ability_name);
            } catch (Throwable $e) {
                // error_log(), NOT the plugin logger: resolving the container is
                // what just failed, so the logger may be unreachable too.
                error_log('[ppcp-abilities] resolving the handler for ' . $ability_name . ' threw ' . get_class($e) . ': ' . $e->getMessage());
                return new \WP_Error('woocommerce_paypal_payments_service_unavailable', sprintf(
                    /* translators: %s: ability name. */
                    __('Service %s could not be resolved.', 'woocommerce-paypal-payments'),
                    $ability_name
                ));
            }
            if (!is_object($handler) || !is_callable(array($handler, 'execute'))) {
                return new \WP_Error('woocommerce_paypal_payments_not_initialized', sprintf(
                    /* translators: %s: ability name. */
                    __('The %s ability is not wired yet.', 'woocommerce-paypal-payments'),
                    $ability_name
                ));
            }
            return $handler->execute($input);
        };
    }
    /**
     * The shared permission gate. Fails closed when nothing was bound: an
     * unwired abilities surface must never be readable.
     *
     * @return callable
     */
    public static function permission_callback(): callable
    {
        $callback = self::$permission_callback;
        if (!is_callable($callback)) {
            return static function (): bool {
                return \false;
            };
        }
        return $callback;
    }
    /**
     * Resolve a bound handler, running its factory on first use and memoizing
     * the result for the rest of the request.
     *
     * @param string $ability_name Fully namespaced ability name.
     * @return object|null The handler, or null when the ability is unbound.
     */
    private static function resolve(string $ability_name)
    {
        if (!isset(self::$handlers[$ability_name])) {
            return null;
        }
        $handler = self::$handlers[$ability_name];
        if ($handler instanceof \Closure || !is_object($handler) && is_callable($handler)) {
            $handler = call_user_func($handler);
            self::$handlers[$ability_name] = $handler;
        }
        return is_object($handler) ? $handler : null;
    }
}
