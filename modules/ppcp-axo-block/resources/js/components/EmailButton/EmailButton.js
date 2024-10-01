import { STORE_NAME } from '../../stores/axoStore';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const EmailButton = ( { handleSubmit } ) => {
	const { isGuest, isAxoActive, isEmailSubmitted } = useSelect(
		( select ) => ( {
			isGuest: select( STORE_NAME ).getIsGuest(),
			isAxoActive: select( STORE_NAME ).getIsAxoActive(),
			isEmailSubmitted: select( STORE_NAME ).getIsEmailSubmitted(),
		} )
	);

	if ( ! isGuest || ! isAxoActive ) {
		return null;
	}

	return (
		<button
			type="button"
			onClick={ handleSubmit }
			className={ `wc-block-components-button wp-element-button ${
				isEmailSubmitted ? 'is-loading' : ''
			}` }
			disabled={ isEmailSubmitted }
		>
			<span
				className="wc-block-components-button__text"
				style={ {
					visibility: isEmailSubmitted ? 'hidden' : 'visible',
				} }
			>
				{ __( 'Continue', 'woocommerce-paypal-payments' ) }
			</span>
			{ isEmailSubmitted && (
				<span
					className="wc-block-components-spinner"
					aria-hidden="true"
					style={ {
						position: 'absolute',
						top: '50%',
						left: '50%',
						transform: 'translate(-50%, -50%)',
					} }
				/>
			) }
		</button>
	);
};

export default EmailButton;
