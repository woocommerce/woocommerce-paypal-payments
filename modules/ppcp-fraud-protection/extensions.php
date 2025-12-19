<?php

/**
 * The fraud protection module extensions.
 *
 * @package WooCommerce\PayPalCommerce\FraudProtection
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\FraudProtection;

use Automattic\WooCommerce\Admin\Notes\Note;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteAction;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteFactory;
return array('wcgateway.settings.inbox-notes' => function (array $notes, ContainerInterface $container): array {
    $inbox_note_factory = $container->get('wcgateway.settings.inbox-note-factory');
    assert($inbox_note_factory instanceof InboxNoteFactory);
    $recaptcha_settings = get_option('woocommerce_ppcp-recaptcha_settings', array());
    $is_recaptcha_enabled = isset($recaptcha_settings['enabled']) && 'yes' === $recaptcha_settings['enabled'];
    return array_merge($notes, array($inbox_note_factory->create_note(__('Activate PayPal fraud management', 'woocommerce-paypal-payments'), __('PayPal detected increased suspicious card activity in market. Please enable fraud protection in your PayPal Payment settings by enabling CAPTCHA for PayPal Payments.', 'woocommerce-paypal-payments'), Note::E_WC_ADMIN_NOTE_INFORMATIONAL, 'ppcp-recaptcha-protection-note', Note::E_WC_ADMIN_NOTE_UNACTIONED, !$is_recaptcha_enabled, new InboxNoteAction('protect-paypal-with-recaptcha', __('Activate Now', 'woocommerce-paypal-payments'), admin_url('admin.php?page=wc-settings&tab=integration&section=ppcp-recaptcha'), Note::E_WC_ADMIN_NOTE_UNACTIONED, \true))));
}, 'wcgateway.settings.wc-tasks.task-config-services' => function (array $service_ids, ContainerInterface $container): array {
    return array_merge($service_ids, array('fraud-protection.wc-tasks.recaptcha-task-config'));
});
