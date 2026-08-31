<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
/**
 * The WebhookRegistrar registers and unregisters webhooks with PayPal.
 */
class WebhookRegistrar
{
    const EVENT_HOOK = 'ppcp-register-event';
    const KEY = 'ppcp-webhook';
    private WebhookFactory $webhook_factory;
    private WebhookEndpoint $endpoint;
    private \WooCommerce\PayPalCommerce\Webhooks\IncomingWebhookEndpoint $incoming_webhook_endpoint;
    private \WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage $last_webhook_event_storage;
    private WebhookSimulation $webhook_simulation;
    private \WooCommerce\PayPalCommerce\Webhooks\WebhookOrchestrator $webhook_orchestrator;
    private LoggerInterface $logger;
    public function __construct(WebhookFactory $webhook_factory, WebhookEndpoint $endpoint, \WooCommerce\PayPalCommerce\Webhooks\IncomingWebhookEndpoint $incoming_webhook_endpoint, \WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage $last_webhook_event_storage, WebhookSimulation $webhook_simulation, \WooCommerce\PayPalCommerce\Webhooks\WebhookOrchestrator $webhook_orchestrator, LoggerInterface $logger)
    {
        $this->webhook_factory = $webhook_factory;
        $this->endpoint = $endpoint;
        $this->incoming_webhook_endpoint = $incoming_webhook_endpoint;
        $this->last_webhook_event_storage = $last_webhook_event_storage;
        $this->webhook_simulation = $webhook_simulation;
        $this->webhook_orchestrator = $webhook_orchestrator;
        $this->logger = $logger;
    }
    /**
     * Register Webhooks with PayPal.
     *
     * @return bool
     */
    public function register(): bool
    {
        $result = $this->webhook_orchestrator->with_lock('register', fn() => $this->do_register());
        // If locked (null), treat as failure.
        return $result ?? \false;
    }
    /**
     * Unregister webhooks with PayPal.
     */
    public function unregister(): void
    {
        $this->webhook_orchestrator->with_lock('unregister', fn() => $this->do_unregister());
    }
    /**
     * Internal registration logic.
     *
     * @return bool
     */
    private function do_register(): bool
    {
        $this->do_unregister();
        // TEMPORARY diagnostic for the ngrok webhook-host investigation. Remove
        // once the registered webhook host is confirmed correct in CI. Written
        // to a plain file rather than error_log(), since PHP's error_log
        // destination isn't guaranteed to land in the container's stdout/stderr
        // stream that `wp-env logs` captures.
        file_put_contents(
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary diagnostic.
            WP_CONTENT_DIR . '/ngrok-diag.log',
            sprintf("[ngrok-diag] REGISTERING webhook: IncomingWebhookEndpoint::url()=%s rest_url(paypal/v1/incoming)=%s getenv(NGROK_HOST)=%s home_url()=%s\n", $this->incoming_webhook_endpoint->url(), rest_url('paypal/v1/incoming'), var_export(getenv('NGROK_HOST'), \true), home_url()),
            \FILE_APPEND
        );
        $webhook = $this->webhook_factory->for_url_and_events($this->incoming_webhook_endpoint->url(), $this->incoming_webhook_endpoint->handled_event_types());
        try {
            $created = $this->endpoint->create($webhook);
            if (empty($created->id())) {
                return \false;
            }
            update_option(self::KEY, $created->to_array());
            // TEMPORARY diagnostic for the ngrok webhook-host investigation.
            file_put_contents(
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary diagnostic.
                WP_CONTENT_DIR . '/ngrok-diag.log',
                sprintf("[ngrok-diag] REGISTERED webhook id=%s url=%s\n", $created->id(), $created->url()),
                \FILE_APPEND
            );
            $this->last_webhook_event_storage->clear();
            // Check whether webhooks are arriving (e.g. for the Status page).
            $this->webhook_simulation->start($created);
            $this->logger->info('Webhooks subscribed.');
            return \true;
        } catch (RuntimeException $error) {
            // TEMPORARY diagnostic for the ngrok webhook-host investigation. Remove
            // alongside the REGISTERING/REGISTERED lines above. Without this, a
            // failed create() only ever reaches the WC logger, which isn't
            // captured anywhere in the CI diagnostics.
            file_put_contents(
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary diagnostic.
                WP_CONTENT_DIR . '/ngrok-diag.log',
                sprintf("[ngrok-diag] CREATE FAILED: class=%s status=%s message=%s\n", get_class($error), $error instanceof PayPalApiException ? (string) $error->status_code() : 'n/a', $error->getMessage()),
                \FILE_APPEND
            );
            $this->logger->error('Failed to subscribe webhooks: ' . $error->getMessage());
            return \false;
        }
    }
    /**
     * Internal unregister logic.
     *
     * Only webhooks that belong to this site are deleted. Webhooks registered for
     * other sites or services on the same PayPal account are left untouched, so
     * connecting a staging or secondary site to shared credentials no longer wipes
     * the primary site's webhook. See GitHub issue #4604.
     */
    private function do_unregister(): void
    {
        $stored_id = (string) (((array) get_option(self::KEY, array()))['id'] ?? '');
        $own_identity = $this->webhook_identity($this->incoming_webhook_endpoint->url());
        try {
            $webhooks = $this->endpoint->list();
            // TEMPORARY diagnostic for the ngrok webhook-host investigation. Remove
            // alongside the other [ngrok-diag] lines. Confirms whether list() (GET)
            // succeeds on the same host/bearer where create() (POST) 404s.
            file_put_contents(
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary diagnostic.
                WP_CONTENT_DIR . '/ngrok-diag.log',
                sprintf("[ngrok-diag] LIST OK: count=%d\n", count($webhooks)),
                \FILE_APPEND
            );
            foreach ($webhooks as $webhook) {
                if (!$this->is_own_webhook($webhook, $own_identity, $stored_id)) {
                    $this->logger->warning("Skipping deletion of webhook {$webhook->id()} ({$webhook->url()}): it belongs to a different site and is not managed by this install.");
                    continue;
                }
                try {
                    $this->endpoint->delete($webhook);
                } catch (RuntimeException $deletion_error) {
                    $this->logger->error("Failed to delete webhook {$webhook->id()}: {$deletion_error->getMessage()}");
                }
            }
        } catch (RuntimeException $error) {
            // TEMPORARY diagnostic for the ngrok webhook-host investigation. Remove
            // alongside the other [ngrok-diag] lines.
            file_put_contents(
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary diagnostic.
                WP_CONTENT_DIR . '/ngrok-diag.log',
                sprintf("[ngrok-diag] LIST FAILED: class=%s status=%s message=%s\n", get_class($error), $error instanceof PayPalApiException ? (string) $error->status_code() : 'n/a', $error->getMessage()),
                \FILE_APPEND
            );
            $this->logger->error('Failed to delete webhooks: ' . $error->getMessage());
        }
        delete_option(self::KEY);
        $this->last_webhook_event_storage->clear();
        $this->logger->info('Webhooks deleted.');
    }
    /**
     * Whether the given webhook belongs to this site and may be deleted.
     *
     * A webhook is considered ours when either:
     * - its id matches the one we previously stored (provably ours regardless of the
     *   host it currently uses, so a domain migration or NGROK_HOST rotation still
     *   cleans up our former webhook instead of leaking it), or
     * - its URL identity (host + path) equals this install's incoming endpoint. The
     *   path is included so that same-host installs (e.g. example.com/shop and
     *   example.com/staging, or subdirectory multisite) do not delete each other's
     *   webhooks. The host comparison honours the NGROK_HOST development override.
     *
     * A webhook whose URL cannot be parsed (empty identity) is left in place.
     *
     * @param Webhook $webhook      The webhook to check.
     * @param string  $own_identity This install's incoming endpoint identity (host + path).
     * @param string  $stored_id    The id of the webhook this install previously registered.
     * @return bool
     */
    private function is_own_webhook(Webhook $webhook, string $own_identity, string $stored_id): bool
    {
        if ('' !== $stored_id && $webhook->id() === $stored_id) {
            return \true;
        }
        $webhook_identity = $this->webhook_identity($webhook->url());
        return '' !== $own_identity && $own_identity === $webhook_identity;
    }
    /**
     * Builds a host+path identity for a webhook URL, used to compare webhooks
     * independently of scheme, port, query string or a trailing slash.
     *
     * @param string $url The webhook URL.
     * @return string The lower-cased host and path, or an empty string when the host cannot be parsed.
     */
    private function webhook_identity(string $url): string
    {
        $host = wp_parse_url($url, \PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            return '';
        }
        $path = wp_parse_url($url, \PHP_URL_PATH);
        $path = is_string($path) ? rtrim($path, '/') : '';
        return strtolower($host) . $path;
    }
}
