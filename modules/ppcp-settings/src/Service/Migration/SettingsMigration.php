<?php

/**
 * Handles migration of general settings from legacy format to new structure.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
use WooCommerce\PayPalCommerce\Settings\Service\SellerTypeResolver;
/**
 * Class SettingsMigration
 *
 * Handles migration of general plugin settings.
 */
class SettingsMigration implements \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigrationInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $settings;
    protected GeneralSettings $general_settings;
    protected PartnersEndpoint $partners_endpoint;
    protected LoggerInterface $logger;
    protected SellerTypeResolver $seller_type_resolver;
    public function __construct(array $settings, GeneralSettings $general_settings, PartnersEndpoint $partners_endpoint, LoggerInterface $logger, SellerTypeResolver $seller_type_resolver)
    {
        $this->settings = $settings;
        $this->general_settings = $general_settings;
        $this->partners_endpoint = $partners_endpoint;
        $this->logger = $logger;
        $this->seller_type_resolver = $seller_type_resolver;
    }
    public function migrate(): void
    {
        if (empty($this->settings['client_id']) || empty($this->settings['client_secret']) || empty($this->settings['merchant_id'])) {
            return;
        }
        $country = '';
        $seller_type = SellerTypeEnum::UNKNOWN;
        try {
            $seller_status = $this->partners_endpoint->seller_status();
            $country = $seller_status->country();
            $seller_type = $this->seller_type_resolver->resolve($seller_status);
        } catch (Exception $exception) {
            $this->logger->warning('Seller status API call failed during settings migration; using defaults.', array('error' => $exception->getMessage()));
        }
        $connection = new MerchantConnectionDTO(!empty($this->settings['sandbox_on']), $this->settings['client_id'], $this->settings['client_secret'], $this->settings['merchant_id'], $this->settings['merchant_email'] ?? '', $country, $seller_type);
        $this->general_settings->set_merchant_data($connection);
        $this->general_settings->save();
    }
}
