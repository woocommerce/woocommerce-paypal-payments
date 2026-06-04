<?php

/**
 * Abstract base class for WooCommerce PayPal Payments ability definitions.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use LogicException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
/**
 * Shared helpers for PayPal Payments ability definitions.
 *
 * Mirrors Woo Core's `Internal\Abilities\Domain\AbstractDomainAbility`
 * (WC 10.9) without coupling to it — that class lives under `Internal\`.
 *
 * @internal
 */
abstract class AbstractPpcpAbility
{
    /**
     * Ability category slug. `woocommerce` is owned/registered by Woo Core
     * 10.9+; plugin ownership lives in the ability namespace, not here.
     * Mirrors AbilitiesRegistrar::CATEGORY_SLUG.
     */
    public const CATEGORY_SLUG = 'woocommerce';
    /**
     * Shared PSR-3 logger for runtime error paths. Static because the
     * Abilities API requires callable arrays, forcing static methods with no
     * per-instance DI hook. Wired by AbilitiesModule::run().
     *
     * @var LoggerInterface|null
     */
    private static $logger = null;
    /**
     * Inject the plugin's PSR-3 logger. Called from AbilitiesModule::run().
     *
     * @internal
     * @param LoggerInterface $logger The PSR-3 logger.
     * @return void
     */
    public static function set_logger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }
    /**
     * Resolve the runtime logger, falling back to a NullLogger.
     *
     * @return LoggerInterface
     */
    protected static function logger(): LoggerInterface
    {
        if (!self::$logger instanceof LoggerInterface) {
            self::$logger = new NullLogger();
        }
        return self::$logger;
    }
    /**
     * @internal Test-isolation helper. Not part of the public API.
     * @return void
     */
    public static function reset_logger_for_testing(): void
    {
        self::$logger = null;
    }
    /**
     * Dispatch a backing REST route via rest_do_request() and return its
     * unwrapped data (or the WP_REST_Response when $return_response, so
     * callers can read pagination headers).
     *
     * @param string $controller_class FQCN of the backing controller (surfaces a clear error when not loaded).
     * @param string $method           HTTP method (GET, POST, PUT, DELETE).
     * @param string $route            Resolved route path.
     * @param array  $params           Request parameters.
     * @param bool   $return_response  Return the WP_REST_Response instead of its data.
     * @return array|\WP_REST_Response|\WP_Error
     */
    protected static function delegate_to_rest_controller(string $controller_class, string $method, string $route, array $params = array(), bool $return_response = \false)
    {
        if (!class_exists($controller_class)) {
            return new \WP_Error('woocommerce_paypal_payments_missing_controller', sprintf(
                /* translators: %s: fully-qualified class name of the missing REST controller. */
                __('REST controller %s is not loaded.', 'woocommerce-paypal-payments'),
                $controller_class
            ), array('status' => 500));
        }
        $request = new \WP_REST_Request($method, $route);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        $response = rest_do_request($request);
        if (is_wp_error($response)) {
            return $response;
        }
        if ($response instanceof \WP_REST_Response) {
            if ($response->is_error()) {
                $error = $response->as_error();
                if ($error instanceof \WP_Error) {
                    return $error;
                }
            }
            if ($return_response) {
                return $response;
            }
            return $response->get_data();
        }
        return is_array($response) ? $response : array($response);
    }
    /**
     * Unwrap the plugin's `{ success, data, … }` REST envelope to `data`.
     * Returns a WP_Error on success=false.
     *
     * Security: with $redact_message (default), the envelope `message` and
     * `details` are logged server-side and replaced with a generic string —
     * backing endpoints propagate raw PayPalApiException text, whose
     * information_link URLs disclose internal API paths. Pass false only when
     * the message is known-safe.
     *
     * @param mixed $payload        Decoded REST response.
     * @param bool  $redact_message Redact + log the error message/details (default true).
     * @return mixed Inner `data`, the original payload, or WP_Error on success=false.
     */
    protected static function unwrap_envelope($payload, bool $redact_message = \true)
    {
        if (!is_array($payload)) {
            return $payload;
        }
        $envelope_error = self::envelope_error_or_null($payload, $redact_message);
        if (null !== $envelope_error) {
            return $envelope_error;
        }
        if (array_key_exists('data', $payload)) {
            return $payload['data'];
        }
        return $payload;
    }
    /**
     * The success=false branch of unwrap_envelope(), extracted so callers
     * whose endpoint returns extra top-level keys (e.g. CommonRestEndpoint's
     * merchant/features) can reuse the redaction without having those keys
     * discarded by `data` extraction.
     *
     * @param array $payload        Decoded REST envelope.
     * @param bool  $redact_message See unwrap_envelope().
     * @return \WP_Error|null WP_Error on success=false; null otherwise.
     */
    protected static function envelope_error_or_null(array $payload, bool $redact_message = \true): ?\WP_Error
    {
        if (!array_key_exists('success', $payload) || \false !== $payload['success']) {
            return null;
        }
        $raw_message = isset($payload['message']) && is_string($payload['message']) ? $payload['message'] : '';
        if ($redact_message) {
            if ('' !== $raw_message) {
                self::logger()->warning('[ppcp-abilities] endpoint returned success=false: ' . $raw_message);
            }
            if (isset($payload['details'])) {
                // Redact `details` like the message: log server-side, keep out of the agent payload.
                self::logger()->warning('[ppcp-abilities] endpoint returned success=false details: ' . wp_json_encode($payload['details']));
            }
            return new \WP_Error('woocommerce_paypal_payments_endpoint_error', __('PayPal Payments endpoint returned an error; see server log for details.', 'woocommerce-paypal-payments'));
        }
        return new \WP_Error('woocommerce_paypal_payments_endpoint_error', '' !== $raw_message ? $raw_message : __('PayPal Payments endpoint returned an error.', 'woocommerce-paypal-payments'), isset($payload['details']) ? array('details' => $payload['details']) : array());
    }
    /**
     * Resolve a service from the plugin container, asserting its type.
     *
     * Distinguishes "container not initialized" (LogicException from
     * PPCP::container()) from "service unresolvable" (Throwable from ->get())
     * so a factory bug is not mislabeled "not initialized". Uses error_log(),
     * NOT self::logger(): this path can fire before the container can resolve
     * the logger itself.
     *
     * @phpstan-template T of object
     * @phpstan-param class-string<T> $expected_class
     * @phpstan-return T|\WP_Error
     *
     * @param string $service_id     Container service id.
     * @param string $expected_class FQCN the resolved service must satisfy.
     * @return object|\WP_Error An instance of $expected_class on success, otherwise WP_Error.
     */
    protected static function resolve_service(string $service_id, string $expected_class)
    {
        try {
            $container = PPCP::container();
        } catch (LogicException $e) {
            return new \WP_Error(
                'woocommerce_paypal_payments_not_initialized',
                /* translators: %s: container service id. */
                sprintf(__('WooCommerce PayPal Payments is not initialized; service %s is unavailable.', 'woocommerce-paypal-payments'), $service_id)
            );
        }
        try {
            $service = $container->get($service_id);
        } catch (Throwable $e) {
            error_log('[ppcp-abilities] resolve_service(' . $service_id . ') threw ' . get_class($e) . ': ' . $e->getMessage());
            return new \WP_Error(
                'woocommerce_paypal_payments_service_unavailable',
                /* translators: %s: container service id. */
                sprintf(__('Service %s could not be resolved.', 'woocommerce-paypal-payments'), $service_id)
            );
        }
        if (!$service instanceof $expected_class) {
            error_log('[ppcp-abilities] resolve_service(' . $service_id . ') returned unexpected type ' . (is_object($service) ? get_class($service) : gettype($service)));
            return new \WP_Error(
                'woocommerce_paypal_payments_service_unavailable',
                /* translators: %s: container service id. */
                sprintf(__('Service %s returned an unexpected type.', 'woocommerce-paypal-payments'), $service_id)
            );
        }
        return $service;
    }
}
