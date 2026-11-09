<?php

/**
 * Extends the Message class to permanently dismiss notices for single users.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AdminNotices\Entity;

/**
 * Class PersistentMessage
 */
class PersistentMessage extends \WooCommerce\PayPalCommerce\AdminNotices\Entity\Message
{
    /**
     * Prefix for DB keys to store IDs of permanently muted notices.
     */
    public const USER_META_PREFIX = '_ppcp_notice_';
    /**
     * An internal ID to permanently dismiss the persistent message.
     *
     * @var string
     */
    private $message_id;
    /**
     * Message constructor.
     *
     * @param string $id      ID of this message, to allow permanent dismissal.
     * @param string $message The message text.
     * @param string $type    The message type.
     * @param string $wrapper The wrapper selector that will contain the notice.
     */
    public function __construct(string $id, string $message, string $type, string $wrapper = '')
    {
        parent::__construct($message, $type, \true, $wrapper);
        $this->message_id = sanitize_key($id);
    }
    /**
     * Returns the sanitized ID that identifies a permanently dismissible message.
     *
     * @param bool $with_db_prefix Whether to add the user-meta prefix.
     *
     * @return string
     */
    public function id(bool $with_db_prefix = \false): string
    {
        if (!$this->message_id) {
            return '';
        }
        return $with_db_prefix ? self::USER_META_PREFIX . $this->message_id : $this->message_id;
    }
    /**
     * {@inheritDoc}
     */
    public function to_array(): array
    {
        $data = parent::to_array();
        $data['id'] = $this->message_id;
        return $data;
    }
    /**
     * {@inheritDoc}
     *
     * @return PersistentMessage
     */
    public static function from_array(array $data): \WooCommerce\PayPalCommerce\AdminNotices\Entity\Message
    {
        return new \WooCommerce\PayPalCommerce\AdminNotices\Entity\PersistentMessage((string) ($data['id'] ?? ''), (string) ($data['message'] ?? ''), (string) ($data['type'] ?? ''), (string) ($data['wrapper'] ?? ''));
    }
    /**
     * Whether the message was permanently muted by the current user.
     *
     * @return bool
     */
    public function is_muted(): bool
    {
        $user_id = get_current_user_id();
        if (!$this->message_id || !$user_id) {
            return \false;
        }
        return 0 < (int) get_user_meta($user_id, $this->id(\true), \true);
    }
    /**
     * Mark the message as permanently muted by the current user.
     *
     * @return void
     */
    public function mute(): void
    {
        $user_id = get_current_user_id();
        if ($this->message_id && $user_id && !$this->is_muted()) {
            update_user_meta($user_id, $this->id(\true), time());
        }
    }
    /**
     * Removes all user-meta flags for muted messages.
     *
     * @return void
     */
    public static function clear_all(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like(self::USER_META_PREFIX) . '%'));
    }
}
