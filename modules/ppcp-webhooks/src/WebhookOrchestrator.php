<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
/**
 * Orchestrates webhook operations with lock protection to prevent race conditions.
 */
class WebhookOrchestrator
{
    private const LOCK_KEY = 'ppcp_webhook_operation_lock';
    private const LOCK_DURATION = 60;
    private LoggerInterface $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    /**
     * Execute a webhook operation with lock protection.
     *
     * @param string   $action    Which operation is started (for logging).
     * @param callable $operation The operation to execute.
     * @return mixed The result of the operation, or null if locked.
     */
    public function with_lock(string $action, callable $operation)
    {
        if ($this->is_locked()) {
            $this->logger->info("Webhook operation '{$action}' skipped - lock held by another process.");
            return null;
        }
        $this->acquire_lock($action);
        try {
            $this->logger->debug("Webhook operation '{$action}' started.");
            return $operation();
        } finally {
            $this->release_lock($action);
        }
    }
    private function is_locked(): bool
    {
        return \false !== get_transient(self::LOCK_KEY);
    }
    private function acquire_lock(string $action): void
    {
        set_transient(self::LOCK_KEY, \true, self::LOCK_DURATION);
        $this->logger->debug("Webhook lock acquired for '{$action}'.");
    }
    private function release_lock(string $action): void
    {
        delete_transient(self::LOCK_KEY);
        $this->logger->debug("Webhook lock released for '{$action}'.");
    }
}
