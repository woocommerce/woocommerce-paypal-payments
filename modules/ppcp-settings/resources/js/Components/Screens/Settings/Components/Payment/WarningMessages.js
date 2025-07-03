import { Icon } from '@wordpress/components';
import { warning } from '@wordpress/icons';

/**
 * Component to display warning messages for payment methods
 *
 * @param {Object} props                 - Component props
 * @param {Object} props.warningMessages - The warning messages to display
 * @return {JSX.Element|null} The formatted warning messages or null
 */
const WarningMessages = ( { warningMessages } ) => {
	const messages = Object.values( warningMessages || {} );

	if ( messages.length === 0 ) {
		return null;
	}

	return (
		<span className="ppcp--method-warning">
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
