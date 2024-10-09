import {
	hide,
	show,
} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

document.addEventListener( 'DOMContentLoaded', function () {
	const refundButton = document.querySelector( 'button.refund-items' );
	if ( ! refundButton ) {
		return;
	}

	refundButton.insertAdjacentHTML(
		'afterend',
		`<button class="button" type="button" id="pcpVoid">${ PcpVoidButton.button_text }</button>`
	);

	hide( refundButton );

	const voidButton = document.querySelector( '#pcpVoid' );

	voidButton.addEventListener( 'click', async () => {
		if ( ! window.confirm( PcpVoidButton.popup_text ) ) {
			return;
		}

		voidButton.setAttribute( 'disabled', 'disabled' );

		const res = await fetch( PcpVoidButton.ajax.void.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify( {
				nonce: PcpVoidButton.ajax.void.nonce,
				wc_order_id: PcpVoidButton.wc_order_id,
			} ),
		} );

		const data = await res.json();

		if ( ! data.success ) {
            hide( voidButton );
			show( refundButton );

            alert( PcpVoidButton.error_text );

			throw Error( data.data.message );
		}

		location.reload();
	} );
} );
