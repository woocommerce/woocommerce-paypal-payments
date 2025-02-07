import { setVisible } from '../Helper/Hiding';
import MessageRenderer from '../Renderer/MessageRenderer';

class MessagesBootstrap {
	constructor( gateway, messageRenderer ) {
		this.gateway = gateway;
		this.renderers = [];
		this.lastAmount = this.gateway.messages.amount;
		if ( messageRenderer ) {
			this.renderers.push( messageRenderer );
		}
	}

	async init() {
		if ( this.gateway.messages?.block?.enabled ) {
			await this.attemptDiscoverBlocks( 3 ); // Try up to 3 times
		}
		jQuery( document.body ).on(
			'ppcp_cart_rendered ppcp_checkout_rendered',
			() => {
				this.render();
			}
		);
		jQuery( document.body ).on( 'ppcp_script_data_changed', ( e, data ) => {
			this.gateway = data;
			this.render();
		} );
		jQuery( document.body ).on(
			'ppcp_cart_total_updated ppcp_checkout_total_updated ppcp_product_total_updated ppcp_block_cart_total_updated',
			( e, amount ) => {
				if ( this.lastAmount !== amount ) {
					this.lastAmount = amount;
					this.render();
				}
			}
		);

		this.render();
	}

	attemptDiscoverBlocks( retries ) {
		return new Promise( ( resolve, reject ) => {
			this.discoverBlocks().then( ( found ) => {
				if ( ! found && retries > 0 ) {
					setTimeout( () => {
						this.attemptDiscoverBlocks( retries - 1 ).then(
							resolve
						);
					}, 2000 ); // Wait 2 seconds before retrying
				} else {
					resolve();
				}
			} );
		} );
	}

	discoverBlocks() {
		return new Promise( ( resolve ) => {
			const elements = document.querySelectorAll( '.ppcp-messages' );
			if ( elements.length === 0 ) {
				resolve( false );
				return;
			}

			Array.from( elements ).forEach( ( blockElement ) => {
				if ( ! blockElement.id ) {
					blockElement.id = `ppcp-message-${ Math.random()
						.toString( 36 )
						.substr( 2, 9 ) }`; // Ensure each block has a unique ID
				}
				const config = { wrapper: '#' + blockElement.id };
				if ( ! blockElement.getAttribute( 'data-pp-placement' ) ) {
					config.placement = this.gateway.messages.placement;
				}
				this.renderers.push( new MessageRenderer( config ) );
			} );
			resolve( true );
		} );
	}

	shouldShow( renderer ) {
		if ( this.gateway.messages.is_hidden === true ) {
			return false;
		}

		const eventData = { result: true };
		jQuery( document.body ).trigger( 'ppcp_should_show_messages', [
			eventData,
			renderer.config.wrapper,
		] );
		return eventData.result;
	}

	render() {
		this.renderers.forEach( ( renderer ) => {
			const shouldShow = this.shouldShow( renderer );
			setVisible( renderer.config.wrapper, shouldShow );
			if ( ! shouldShow ) {
				return;
			}

			if ( ! renderer.shouldRender() ) {
				return;
			}

			renderer.renderWithAmount( this.lastAmount );
		} );
	}
}

export default MessagesBootstrap;
