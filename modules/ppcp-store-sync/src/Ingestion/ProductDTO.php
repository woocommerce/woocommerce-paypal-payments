<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

/**
 * Single product entry of the agentic-commerce ingestion payload.
 *
 * A passive value carrier: it defines the payload schema and serialises itself via
 * {@see ProductDTO::to_array()}, which owns the wire-format contract — the
 * camelCase `merchantStoreUrl` key and the omit-when-empty behaviour for optional
 * fields. All extraction logic lives in {@see ProductsPayload}.
 */
class ProductDTO
{
    private string $id;
    private string $item_group_id;
    private string $title;
    private string $link;
    private string $image_link;
    private string $description;
    private string $price;
    private string $availability;
    private string $merchant_store_url;
    private ?string $mpn;
    private ?string $sale_price;
    private ?string $product_type;
    private ?string $color;
    private ?string $size;
    private ?string $gender;
    public function __construct(string $merchant_store_url, string $id, string $item_group_id, string $title, string $link, string $image_link, string $description, string $price, string $availability, ?string $mpn = null, ?string $sale_price = null, ?string $product_type = null, ?string $color = null, ?string $size = null, ?string $gender = null)
    {
        $this->merchant_store_url = $merchant_store_url;
        $this->id = $id;
        $this->item_group_id = $item_group_id;
        $this->title = $title;
        $this->link = $link;
        $this->image_link = $image_link;
        $this->description = $description;
        $this->price = $price;
        $this->availability = $availability;
        $this->mpn = $mpn;
        $this->sale_price = $sale_price;
        $this->product_type = $product_type;
        $this->color = $color;
        $this->size = $size;
        $this->gender = $gender;
    }
    /**
     * Serialises the entry to the API wire format, omitting empty optional fields.
     *
     * @return array<string, string>
     */
    public function to_array(): array
    {
        $data = array('id' => $this->id, 'item_group_id' => $this->item_group_id, 'title' => $this->title, 'link' => $this->link, 'image_link' => $this->image_link, 'description' => $this->description, 'price' => $this->price, 'availability' => $this->availability, 'merchantStoreUrl' => $this->merchant_store_url);
        if ($this->color !== null) {
            $data['color'] = $this->color;
        }
        if ($this->size !== null) {
            $data['size'] = $this->size;
        }
        if ($this->gender !== null) {
            $data['gender'] = $this->gender;
        }
        if ($this->mpn !== null) {
            $data['mpn'] = $this->mpn;
        }
        if ($this->sale_price !== null) {
            $data['sale_price'] = $this->sale_price;
        }
        if ($this->product_type !== null) {
            $data['product_type'] = $this->product_type;
        }
        return $data;
    }
}
