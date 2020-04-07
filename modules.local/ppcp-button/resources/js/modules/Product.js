class Product {

    constructor(id, quantity, variations) {
        this.id = id;
        this.quantity = quantity;
        this.variations = variations;
    }

    data() {
        return {
            id:this.id,
            quantity:this.quantity,
            variations:this.variations
        }
    }
}

export default Product;