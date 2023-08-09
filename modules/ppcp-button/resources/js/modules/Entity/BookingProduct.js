import Product from "./Product";

class BookingProduct extends Product {

    constructor(id, quantity, booking, extra) {
        super(id, quantity, null);
        this.booking = booking;
        this.extra = extra;
    }

    data() {
        return {
            ...super.data(),
            booking: this.booking,
            extra: this.extra
        }
    }
}

export default BookingProduct;
