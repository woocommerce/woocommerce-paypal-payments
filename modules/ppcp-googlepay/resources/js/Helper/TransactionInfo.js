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
		this.#amount = this.toAmount( newAmount );
	}

	get amount() {
		return this.#amount;
	}

	set shippingFee( newCost ) {
		this.#shippingFee = this.toAmount( newCost );
	}

	get shippingFee() {
		return this.#shippingFee;
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

	/**
	 * Converts the value to a number and rounds to a precision of 2 digits.
	 *
	 * @param {any} value - The value to sanitize.
	 * @return {number} Numeric value.
	 */
	toAmount( value ) {
		value = Number( value ) || 0;
		return Math.round( value * 100 ) / 100;
	}

	setTotal( totalPrice, shippingFee ) {
		totalPrice = this.toAmount( totalPrice );

		if ( totalPrice ) {
			this.shippingFee = shippingFee;
			this.amount = totalPrice - this.shippingFee;

			console.log(
				'New Total Price:',
				totalPrice,
				'=',
				this.amount,
				'+',
				this.shippingFee
			);
		}
	}
}
