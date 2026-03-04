<?php

/**
 * Updates PayPal order with the current shipping methods.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Patch;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PatchCollection;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
/**
 * Class UpdateShippingEndpoint
 */
class UpdateShippingEndpoint implements EndpointInterface
{
    const ENDPOINT = 'ppc-update-shipping';
    const WC_STORE_API_ENDPOINT = '/wp-json/wc/store/v1/cart/';
    /**
     * The Request Data Helper.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * The purchase unit factory.
     *
     * @var PurchaseUnitFactory
     */
    private $purchase_unit_factory;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * UpdateShippingEndpoint constructor.
     *
     * @param RequestData         $request_data The Request Data Helper.
     * @param OrderEndpoint       $order_endpoint The order endpoint.
     * @param PurchaseUnitFactory $purchase_unit_factory The purchase unit factory.
     * @param LoggerInterface     $logger The logger.
     */
    public function __construct(RequestData $request_data, OrderEndpoint $order_endpoint, PurchaseUnitFactory $purchase_unit_factory, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->order_endpoint = $order_endpoint;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->logger = $logger;
    }
    /**
     * Returns the nonce.
     *
     * @return string
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Handles the request.
     *
     * @return bool
     */
    public function handle_request(): bool
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
            $order_id = $data['order_id'];
            $pu = $this->purchase_unit_factory->from_wc_cart(null, \true);
            $pu_data = $pu->to_array();
            // TODO: maybe should patch only if methods changed.
            // But it seems a bit difficult to detect,
            // e.g. ->order($id) may not have Shipping because we drop it when address or name are missing.
            // Also may consider patching only amount and options instead of the whole PU, though not sure if it makes any difference.
            $patches = new PatchCollection(new Patch('replace', "/purchase_units/@reference_id=='{$pu->reference_id()}'", $pu_data));
            $this->order_endpoint->patch($order_id, $patches);
            wp_send_json_success();
            return \true;
        } catch (Exception $error) {
            wp_send_json_error(array('message' => $error->getMessage()));
            return \false;
        }
    }
}
