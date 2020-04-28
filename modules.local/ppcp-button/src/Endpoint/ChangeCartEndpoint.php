<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;

class ChangeCartEndpoint implements EndpointInterface
{

    const ENDPOINT = 'ppc-change-cart';

    private $cart;
    private $shipping;
    private $requestData;
    private $repository;
    public function __construct(
        \WC_Cart $cart,
        \WC_Shipping $shipping,
        RequestData $requestData,
        CartRepository $repository
    ) {

        $this->cart = $cart;
        $this->shipping = $shipping;
        $this->requestData = $requestData;
        $this->repository = $repository;
    }

    public static function nonce() : string
    {
        return self::ENDPOINT;
    }

    public function handleRequest() : bool
    {
        try {
            $data = $this->requestData->readRequest($this->nonce());

            if (! isset($data['products'])
                || ! is_array($data['products'])
            ) {
                wp_send_json_error(
                    __(
                        'Necessary fields not defined. Action aborted.',
                        'woocommerce-paypal-commerce-gateway'
                    )
                );
                return false;
            }

            $products = [];
            foreach ($data['products'] as $product) {
                if (! isset($product['quantity']) || ! isset($product['id'])) {
                    wp_send_json_error(
                        __(
                            'Necessary fields not defined. Action aborted.',
                            'woocommerce-paypal-commerce-gateway'
                        )
                    );
                    return false;
                }

                $wcProduct = wc_get_product((int) $product['id']);
                if (! $wcProduct) {
                    wp_send_json_error(
                        __(
                            'Product not found. Action aborted.',
                            'woocommerce-paypal-commerce-gateway'
                        )
                    );
                    return false;
                }
                $products[] = [
                    'product' => $wcProduct,
                    'quantity' => (int) $product['quantity'],
                    'variations' => isset($product['variations']) ? $product['variations'] : null,
                ];
            }

            $this->shipping->reset_shipping();
            $this->cart->empty_cart(false);
            $success = true;
            foreach ($products as $product) {
                $success = $success && (! $product['product']->is_type('variable')) ?
                    $success = $this->addProduct($product['product'], $product['quantity'])
                    : $this->addVariableProduct($product['product'], $product['quantity'], $product['variations']);
            }
            if (! $success) {
                $message = __('Something went wrong. Action aborted', 'woocommerce-paypal-commerce-gateway');
                $errors = wc_get_notices('error');
                if (count($errors)) {
                    $message = array_reduce(
                        $errors,
                        function (string $add, array $error) : string {
                            return $add . $error['notice'] . ' ';
                        },
                        ''
                    );
                    wc_clear_notices();
                }
                wp_send_json_error($message);
                return $success;
            }

            wp_send_json_success($this->generatePurchaseUnits());
            return $success;
        } catch (RuntimeException $error) {
            wp_send_json_error($error->getMessage());
            return false;
        }
    }

    private function addProduct(\WC_Product $product, int $quantity) : bool
    {
        return false !== $this->cart->add_to_cart($product->get_id(), $quantity);
    }

    private function addVariableProduct(
        \WC_Product $product,
        int $quantity,
        array $postVariations
    ) : bool {

        foreach ($postVariations as $key => $value) {
            $variations[$value['name']] = $value['value'];
        }

        $dataStore = \WC_Data_Store::load('product');
        $variationId = $dataStore->find_matching_product_variation($product, $variations);

        //ToDo: Check stock status for variation.
        return false !== WC()->cart->add_to_cart($product->get_id(), $quantity, $variationId, $variations);
    }

    private function generatePurchaseUnits() : array
    {
        return array_map(
            function (PurchaseUnit $lineItem) : array {
                return $lineItem->toArray();
            },
            $this->repository->all()
        );
    }
}
