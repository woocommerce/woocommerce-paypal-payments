<?php

/**
 * A one-shot store for user-facing onboarding/connection notices.
 *
 * Some onboarding failures happen during a server-side request (for example the
 * OAuth "Return to Store" redirect handled by the ConnectionListener) where there
 * is no REST response the settings app can read. This store lets that request
 * queue a short-lived, user-scoped notice that the settings app picks up and
 * displays on its next page load.
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

/**
 * Persists transient, user-facing onboarding notices for one-time display.
 */
class OnboardingNotices
{
    /**
     * Prefix for the per-user transient that holds pending notices.
     */
    private const TRANSIENT_PREFIX = 'ppcp_onboarding_notices_';
    /**
     * How long a pending notice is kept before it expires (in seconds).
     */
    private const TTL = 5 * MINUTE_IN_SECONDS;
    /**
     * Valid notice types. Unknown types fall back to 'error'.
     */
    private const TYPES = array('error', 'success', 'info', 'warning');
    /**
     * Queues a notice for the current user, to be displayed on the next page load.
     *
     * @param string $message The user-facing, translated message.
     * @param string $type    Notice type: 'error', 'success', 'info' or 'warning'.
     */
    public function add(string $message, string $type = 'error'): void
    {
        $message = trim($message);
        if ('' === $message) {
            return;
        }
        if (!in_array($type, self::TYPES, \true)) {
            $type = 'error';
        }
        $notices = $this->all();
        $notices[] = array('type' => $type, 'message' => $message);
        set_transient($this->transient_key(), $notices, self::TTL);
    }
    /**
     * Returns all pending notices for the current user and clears the store.
     *
     * This is a one-shot read: each notice is returned exactly once.
     *
     * @return array<int, array{type: string, message: string}> The pending notices.
     */
    public function pull(): array
    {
        $notices = $this->all();
        if ($notices) {
            delete_transient($this->transient_key());
        }
        return $notices;
    }
    /**
     * Reads the pending notices for the current user without clearing them.
     *
     * @return array<int, array{type: string, message: string}> The pending notices.
     */
    private function all(): array
    {
        $notices = get_transient($this->transient_key());
        return is_array($notices) ? $notices : array();
    }
    /**
     * Builds the per-user transient key, so one admin never sees another's notices.
     */
    private function transient_key(): string
    {
        return self::TRANSIENT_PREFIX . get_current_user_id();
    }
}
