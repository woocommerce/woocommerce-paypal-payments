export default class TransactionInfo {
	#country = '';
	#currency = '';
	#isFinal = false;
	#amount = 0;

	constructor( amount, currency, country, isFinal ) {
		this.#country = country;
		this.#currency = currency;
		this.#isFinal = isFinal;

		this.amount = amount;
	}

	set amount( newAmount ) {
		this.#amount = Number( newAmount ) || 0;
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
		return this.#amount.toFixed( 2 );
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
