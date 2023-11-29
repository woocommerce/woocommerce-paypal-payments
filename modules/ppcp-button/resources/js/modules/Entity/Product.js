class Product {

    constructor(id, quantity, variations, extra) {
        this.id = id;
        this.quantity = quantity;
        this.variations = variations;
        this.extra = extra;
    }
    data() {
        return {
            id:this.id,
            quantity: this.quantity,
            variations: this.variations,
            extra: this.extra,
        }
    }
}

export default Product;
