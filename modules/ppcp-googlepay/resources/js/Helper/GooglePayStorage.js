import { LocalStorage } from '../../../../ppcp-button/resources/js/modules/Helper/LocalStorage';

export class GooglePayStorage extends LocalStorage {
	static PAYER = 'payer';
	static PAYER_TTL = 900; // 15 minutes in seconds

	constructor() {
		super( 'ppcp-googlepay' );
	}

	getPayer() {
		return this.get( GooglePayStorage.PAYER );
	}

	setPayer( data ) {
		/*
		 * The payer details are deleted on successful checkout, or after the TTL is reached.
		 * This helps to remove stale data from the browser, in case the customer chooses to
		 * use a different method to complete the purchase.
		 */
		this.set( GooglePayStorage.PAYER, data, GooglePayStorage.PAYER_TTL );
	}

	clearPayer() {
		this.clear( GooglePayStorage.PAYER );
	}
}

const moduleStorage = new GooglePayStorage();

export default moduleStorage;
