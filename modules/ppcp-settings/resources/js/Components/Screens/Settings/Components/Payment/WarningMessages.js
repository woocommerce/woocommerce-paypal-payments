import classNames from 'classnames';
import { Icon } from '@wordpress/components';
import { warning } from '@wordpress/icons';

/**
 * Component to display warning messages for payment methods
 *
 * @param {Object} props                 - Component props
 * @param {Object} props.warningMessages - The warning messages to display
 * @param {string} [props.severity]      - The severity level: 'warning' (yellow) or 'error' (red)
 * @return {JSX.Element|null} The formatted warning messages or null
 */
const WarningMessages = ( { warningMessages, severity = 'warning' } ) => {
	const messages = Object.values( warningMessages || {} ).filter( Boolean );

	if ( messages.length === 0 ) {
		return null;
	}

	return (
		<span
			className={ classNames( 'ppcp--method-warning', {
				'ppcp--method-warning--error': severity === 'error',
			} ) }
		>
			<Icon icon={ warning } />
			<div className="ppcp--method-warning-message">
				{ messages.map( ( message, index ) => (
					<div
						key={ index }
						className="ppcp--method-warning__item"
						dangerouslySetInnerHTML={ { __html: message } }
					/>
				) ) }
			</div>
		</span>
	);
};

export default WarningMessages;
