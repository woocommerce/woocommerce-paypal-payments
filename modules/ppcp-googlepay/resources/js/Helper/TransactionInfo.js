export default class TransactionInfo {
	#country = '';
	#currency = '';
	#isFinal = false;
	#amount = 0;
	#shippingFee = 0;

	constructor( total, shippingFee, currency, country, isFinal ) {
		this.#country = country;
		this.#currency = currency;
		this.#isFinal = isFinal;

		this.shippingFee = shippingFee;
		this.amount = total - shippingFee;
	}

	set amount( newAmount ) {
		this.#amount = Number( newAmount ) || 0;
	}

	set shippingFee( newCost ) {
		this.#shippingFee = Number( newCost ) || 0;
	}

	set total( newTotal ) {
		newTotal = Number( newTotal ) || 0;

		if ( ! newTotal ) {
			return;
		}

		this.#amount = newTotal - this.#shippingFee;
	}

	get currencyCode() {
		return this.#currency;
	}

	get countryCode() {
		return this.#country;
	}

	get totalPriceStatus() {
		return this.#isFinal ? 'FINAL' : 'DRAFT';
	}

	get totalPrice() {
		const total = this.#amount + this.#shippingFee;

		return total.toFixed( 2 );
	}

	get dataObject() {
		return {
			countryCode: this.countryCode,
			currencyCode: this.currencyCode,
			totalPriceStatus: this.totalPriceStatus,
			totalPrice: this.totalPrice,
		};
	}
}
