import MessagesBootstrap from '../../../../ppcp-button/resources/js/modules/ContextBootstrap/MessagesBootstap';
import { debounce } from '../Helper/debounce';

class BlockCheckoutMessagesBootstrap {
	constructor( scriptData ) {
		this.messagesBootstrap = new MessagesBootstrap( scriptData, null );
		this.lastCartTotal = null;
	}

	init() {
		this.messagesBootstrap.init();

		this._updateCartTotal();

		if ( wp.data?.subscribe ) {
			wp.data.subscribe(
				debounce( () => {
					this._updateCartTotal();
				}, 300 )
			);
		}
	}

	/**
	 * @private
	 */
	_getCartTotal() {
		if ( ! wp.data.select ) {
			return null;
		}

		const cart = wp.data.select( 'wc/store/cart' );
		if ( ! cart ) {
			return null;
		}

		const totals = cart.getCartTotals();
		return (
			parseInt( totals.total_price, 10 ) /
			10 ** totals.currency_minor_unit
		);
	}

	/**
	 * @private
	 */
	_updateCartTotal() {
		const currentTotal = this._getCartTotal();
		if ( currentTotal === null ) {
			return;
		}

		if ( currentTotal !== this.lastCartTotal ) {
			this.lastCartTotal = currentTotal;
			jQuery( document.body ).trigger( 'ppcp_block_cart_total_updated', [
				currentTotal,
			] );
		}
	}
}

export default BlockCheckoutMessagesBootstrap;
