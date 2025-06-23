import { Button } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';
import { OpenSignup } from '../../../ReusableComponents/Icons';
import { useHandleOnboardingButton } from '../../../../hooks/useHandleConnections';
import { OnboardingHooks } from '../../../../data/onboarding/hooks';
import BusyStateWrapper from '../../../ReusableComponents/BusyStateWrapper';
import { Notice } from '../../../ReusableComponents/Elements';

const useIsFirefox = () => {
	if ( typeof window === 'undefined' ) {
		return false;
	}
	return window.navigator.userAgent.toLowerCase().indexOf( 'firefox' ) > -1;
};

/**
 * Button component that outputs a placeholder button when no onboardingUrl is present yet - the
 * placeholder button looks identical to the working button, but has no href, target, or
 * custom connection attributes.
 *
 * @param {Object}   props
 * @param {string}   props.className
 * @param {string}   props.variant
 * @param {boolean}  props.showIcon
 * @param {?string}  props.href
 * @param {Element}  props.children
 * @param {Function} props.onClick
 */
const ButtonOrPlaceholder = ( {
	className,
	variant,
	showIcon,
	href,
	children,
	onClick,
} ) => {
	const isFirefox = useIsFirefox();

	const buttonProps = {
		className,
		variant,
		icon: showIcon ? OpenSignup : null,
		onClick,
	};

	if ( href ) {
		buttonProps.href = href;
		buttonProps[ 'data-paypal-button' ] = 'true';
		buttonProps[ 'data-paypal-onboard-button' ] = 'true';
	}

	if ( isFirefox ) {
		return (
			<>
				<Button { ...buttonProps }>{ children }</Button>
				<Notice type={ 'error' }>
					{ __(
						'This button may not work in Firefox. Please use another browser, like Chrome, to complete this step.',
						'woocommerce-paypal-payments'
					) }
				</Notice>
			</>
		);
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

	const { connectionButtonClicked, setConnectionButtonClicked } =
		OnboardingHooks.useConnectionButton();

	const buttonClassName = classNames( 'ppcp-r-connection-button', className, {
		'ppcp--mode-sandbox': isSandbox,
		'ppcp--mode-live': ! isSandbox,
		'ppcp--button-clicked': connectionButtonClicked,
	} );
	const environment = isSandbox ? 'sandbox' : 'production';

	const handleButtonClick = useCallback( () => {
		setConnectionButtonClicked( true );
	}, [ setConnectionButtonClicked ] );

	// Reset button clicked state when onboardingUrl becomes available.
	useEffect( () => {
		if ( onboardingUrl && connectionButtonClicked ) {
			setConnectionButtonClicked( false );
		}
	}, [ onboardingUrl, connectionButtonClicked, setConnectionButtonClicked ] );

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
				onClick={ handleButtonClick }
			>
				<span className="button-title">{ title }</span>
			</ButtonOrPlaceholder>
		</BusyStateWrapper>
	);
};

export default ConnectionButton;
