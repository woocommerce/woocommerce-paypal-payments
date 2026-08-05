import { Button } from '@wordpress/components';
import { useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';
import { OpenSignup } from '@ppcp-settings/Components/ReusableComponents/Icons';
import {
	useHandleOnboardingButton,
	setClickedEnvironment,
} from '@ppcp-settings/hooks/useHandleConnections';
import { OnboardingHooks } from '@ppcp-settings/data/onboarding/hooks';
import BusyStateWrapper from '@ppcp-settings/Components/ReusableComponents/BusyStateWrapper';
import { Notice } from '@ppcp-settings/Components/ReusableComponents/Elements';

const isFirefox =
	typeof window !== 'undefined' &&
	window.navigator.userAgent.toLowerCase().includes( 'firefox' );
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
		isActiveEnvironment,
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

	const handleButtonClick = useCallback(
		( event ) => {
			// Only the button matching the active environment is a real PayPal
			// button; the other one is inert.
			if ( ! isActiveEnvironment ) {
				event.preventDefault();
				return;
			}

			/**
			 * partner.js wires the anchor to the minibrowser asynchronously and marks
			 * it as ready by adding `data-secureWindowMsg` / `data-secureButtonMsg`.
			 * Until then, a click would just follow the href and open the onboarding
			 * page in a new browser tab - with no minibrowser and no
			 * `onOnboardComplete` callback, so the connection is never saved. We
			 * neutralize the click until the button is bound; the next click works.
			 */
			const anchor = event.currentTarget;
			const isBoundToMiniBrowser =
				!! anchor?.hasAttribute?.( 'data-securewindowmsg' ) ||
				!! anchor?.hasAttribute?.( 'data-securebuttonmsg' );

			if ( ! isBoundToMiniBrowser ) {
				event.preventDefault();
				return;
			}

			setConnectionButtonClicked( true );

			// Record which environment the merchant clicked so the shared
			// onOnboardComplete handler authenticates against the right account.
			setClickedEnvironment( environment );
		},
		[ isActiveEnvironment, setConnectionButtonClicked, environment ]
	);

	// Reset button clicked state when onboardingUrl becomes available.
	useEffect( () => {
		if ( onboardingUrl && connectionButtonClicked ) {
			setConnectionButtonClicked( false );
		}
	}, [ onboardingUrl, connectionButtonClicked, setConnectionButtonClicked ] );

	useEffect( () => {
		if ( scriptLoaded && onboardingUrl ) {
			window.PAYPAL.apps.Signup.render();
			setCompleteHandler();
		}

		return () => {
			removeCompleteHandler();
		};
	}, [
		scriptLoaded,
		onboardingUrl,
		setCompleteHandler,
		removeCompleteHandler,
	] );

	return (
		<BusyStateWrapper isBusy={ isActiveEnvironment && ! onboardingUrl }>
			<ButtonOrPlaceholder
				className={ buttonClassName }
				variant={ variant }
				showIcon={ showIcon }
				href={ isActiveEnvironment ? onboardingUrl : undefined }
				onClick={ handleButtonClick }
			>
				<span className="button-title">{ title }</span>
			</ButtonOrPlaceholder>
		</BusyStateWrapper>
	);
};

export default ConnectionButton;
