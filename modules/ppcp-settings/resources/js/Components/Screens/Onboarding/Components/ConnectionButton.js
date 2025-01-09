import { Button } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import classNames from 'classnames';
import { openSignup } from '../../../ReusableComponents/Icons';
import { useHandleOnboardingButton } from '../../../../hooks/useHandleConnections';
import BusyStateWrapper from '../../../ReusableComponents/BusyStateWrapper';

/**
 * Button component that outputs a placeholder button when no onboardingUrl is present yet - the
 * placeholder button looks identical to the working button, but has no href, target, or
 * custom connection attributes.
 *
 * @param {Object}  props
 * @param {string}  props.className
 * @param {string}  props.variant
 * @param {boolean} props.showIcon
 * @param {?string} props.href
 * @param {Element} props.children
 */
const ButtonOrPlaceholder = ( {
	className,
	variant,
	showIcon,
	href,
	children,
} ) => {
	const buttonProps = {
		className,
		variant,
		icon: showIcon ? openSignup : null,
	};

	if ( href ) {
		buttonProps.href = href;
		buttonProps.target = 'PPFrame';
		buttonProps[ 'data-paypal-button' ] = 'true';
		buttonProps[ 'data-paypal-onboard-button' ] = 'true';
	}

	return <Button { ...buttonProps }>{ children }</Button>;
};

const ConnectionButton = ( {
	title,
	isSandbox = false,
	variant = 'primary',
	showIcon = true,
	className = '',
} ) => {
	const {
		onboardingUrl,
		scriptLoaded,
		setCompleteHandler,
		removeCompleteHandler,
	} = useHandleOnboardingButton( isSandbox );
	const buttonClassName = classNames( 'ppcp-r-connection-button', className, {
		'sandbox-mode': isSandbox,
		'live-mode': ! isSandbox,
	} );
	const environment = isSandbox ? 'sandbox' : 'production';

	useEffect( () => {
		if ( scriptLoaded && onboardingUrl ) {
			window.PAYPAL.apps.Signup.render();
			setCompleteHandler( environment );
		}

		return () => {
			removeCompleteHandler();
		};
	}, [
		scriptLoaded,
		onboardingUrl,
		environment,
		setCompleteHandler,
		removeCompleteHandler,
	] );

	return (
		<BusyStateWrapper isBusy={ ! onboardingUrl }>
			<ButtonOrPlaceholder
				className={ buttonClassName }
				variant={ variant }
				showIcon={ showIcon }
				href={ onboardingUrl }
			>
				<span className="button-title">{ title }</span>
			</ButtonOrPlaceholder>
		</BusyStateWrapper>
	);
};

export default ConnectionButton;
