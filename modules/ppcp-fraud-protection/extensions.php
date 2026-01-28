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
    return array_merge($notes, array($inbox_note_factory->create_note(__('Fraud protection is now required — enable today', 'woocommerce-paypal-payments'), __('Card networks like Visa, Mastercard and American Express now require fraud prevention controls, and non-compliance may result in fines and processing restrictions. Please enable reCAPTCHA in your PayPal Payments settings to help protect your store and maintain compliance.', 'woocommerce-paypal-payments'), Note::E_WC_ADMIN_NOTE_INFORMATIONAL, 'ppcp-recaptcha-protection-note12', Note::E_WC_ADMIN_NOTE_UNACTIONED, !$is_recaptcha_enabled, new InboxNoteAction('protect-paypal-with-recaptcha', __('Enable reCAPTCHA →', 'woocommerce-paypal-payments'), admin_url('admin.php?page=wc-settings&tab=integration&section=ppcp-recaptcha'), Note::E_WC_ADMIN_NOTE_UNACTIONED, \true), new InboxNoteAction('learn-more-paypal-recaptcha', __('Learn more', 'woocommerce-paypal-payments'), 'https://woocommerce.com/document/woocommerce-paypal-payments/fraud-and-disputes/#section-4', Note::E_WC_ADMIN_NOTE_UNACTIONED, \false))));
}, 'wcgateway.settings.wc-tasks.task-config-services' => function (array $service_ids, ContainerInterface $container): array {
    return array_merge($service_ids, array('fraud-protection.wc-tasks.recaptcha-task-config'));
});
