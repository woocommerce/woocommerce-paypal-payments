import { STORE_NAME } from '../../stores/axoStore';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Renders a submit button for email input in the AXO checkout process.
 *
 * @param {Object}   props
 * @param {Function} props.handleSubmit - Function to handle button click/submit.
 * @return {JSX.Element|null} The rendered button or null if conditions are not met.
 */
const EmailButton = ( { handleSubmit } ) => {
	// Select relevant states from the AXO store
	const { isGuest, isAxoActive, isEmailSubmitted } = useSelect(
		( select ) => ( {
			isGuest: select( STORE_NAME ).getIsGuest(),
			isAxoActive: select( STORE_NAME ).getIsAxoActive(),
			isEmailSubmitted: select( STORE_NAME ).getIsEmailSubmitted(),
		} )
	);

	// Only render the button for guests when AXO is active
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
			{ /* Button text */ }
			<span
				className="wc-block-components-button__text"
				style={ {
					visibility: isEmailSubmitted ? 'hidden' : 'visible',
				} }
			>
				{ __( 'Continue', 'woocommerce-paypal-payments' ) }
			</span>
			{ /* Loading spinner */ }
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
