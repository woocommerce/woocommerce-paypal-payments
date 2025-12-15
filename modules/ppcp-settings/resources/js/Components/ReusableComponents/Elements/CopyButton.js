import { __ } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { Tooltip } from '@wordpress/components';
import { SVG, Path } from '@wordpress/primitives';
import classNames from 'classnames';
import { useCopyToClipboard } from '@ppcp-settings/hooks/useCopyToClipboard';

const COPY_CONFIRMATION_DURATION = 1000;

/**
 * Copy button component with tooltip and icon transition
 * @param {Object} props             - Component props
 * @param {string} props.value       - The text value to copy to clipboard
 * @param {string} [props.className] - Additional CSS class names
 * @param {string} [props.ariaLabel] - Custom aria-label for the button
 */
const CopyButton = ( { value, className, ariaLabel, ...props } ) => {
	const { copy, copied, error } = useCopyToClipboard( {
		successDuration: COPY_CONFIRMATION_DURATION,
	} );

	const buttonClass = classNames( 'ppcp-copy-button', className );

	const getTooltipText = () => {
		if ( copied ) {
			return __( 'Copied!', 'woocommerce-paypal-payments' );
		}
		if ( error ) {
			return __( 'Failed to copy', 'woocommerce-paypal-payments' );
		}
		return __( 'Copy to clipboard', 'woocommerce-paypal-payments' );
	};

	const handleCopy = async () => {
		if ( ! value ) {
			return;
		}
		await copy( value );

		if ( copied ) {
			speak(
				__( 'Copied to clipboard', 'woocommerce-paypal-payments' ),
				'assertive'
			);
			return;
		}

		if ( error ) {
			speak(
				__(
					'Failed to copy to clipboard',
					'woocommerce-paypal-payments'
				),
				'assertive'
			);
		}
	};

	return (
		<Tooltip
			text={ getTooltipText() }
			placement="top"
			delay={ 100 }
			hideOnClick={ false }
		>
			<button
				type="button"
				onClick={ handleCopy }
				className={ buttonClass }
				disabled={ ! value }
				aria-label={ ariaLabel || getTooltipText() }
				{ ...props }
			>
				{ copied ? <CheckIcon /> : <CopyIcon /> }
			</button>
		</Tooltip>
	);
};

const CopyIcon = () => (
	<SVG
		width="20"
		height="20"
		viewBox="0 0 24 24"
		xmlns="http://www.w3.org/2000/svg"
	>
		<Path
			fillRule="evenodd"
			d="M16 16v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1h3V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-3zm2.5-10.5v9H16V9a1 1 0 0 0-1-1H9.5V5.5h9z"
			clipRule="evenodd"
		/>
	</SVG>
);

const CheckIcon = () => (
	<SVG
		width="20"
		height="20"
		viewBox="0 0 24 24"
		xmlns="http://www.w3.org/2000/svg"
	>
		<Path d="M9 16.17L4.83 12L3.41 13.41L9 19L21 7L19.59 5.59L9 16.17Z" />
	</SVG>
);

export default CopyButton;
