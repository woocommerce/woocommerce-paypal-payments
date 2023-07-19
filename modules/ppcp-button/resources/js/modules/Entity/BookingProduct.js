import Product from "./Product";

class BookingProduct extends Product {

    constructor(id, quantity, booking) {
        super(id, quantity, null);
        this.booking = booking;
    }

    data() {
        return {
            ...super.data(),
            booking: this.booking
        }
    }
}

export default BookingProduct;
