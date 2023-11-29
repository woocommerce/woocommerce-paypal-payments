import Product from "./Product";

class BookingProduct extends Product {

    constructor(id, quantity, booking, extra) {
        super(id, quantity, null, extra);
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
