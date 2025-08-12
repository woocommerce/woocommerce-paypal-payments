import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

import TopNavigation from '../../../ReusableComponents/TopNavigation';
import { useStoreManager } from '../../../../hooks/useStoreManager';
import { CommonHooks } from '../../../../data';
import TabBar from '../../../ReusableComponents/TabBar';
import classNames from 'classnames';

// How long the save confirmation stays visible, in milliseconds.
const SAVE_CONFIRMATION_DURATION = 2500;

// How long does the CSS transition last (match this with _navigation.scss values)
const NOTIFICATION_ANIMATION_DURATION = 300;

const SettingsNavigation = ( {
	canSave = true,
	tabs = [],
	activePanel = '',
	setActivePanel = () => {},
} ) => {
	const { persistAll } = useStoreManager();
	const title = __( 'PayPal Payments', 'woocommerce-paypal-payments' );
	const [ isSaving, setIsSaving ] = useState( false );

	const handleSave = () => {
		setIsSaving( true );
		speak(
			__( 'Saving settings…', 'woocommerce-paypal-payments' ),
			'assertive'
		);
		persistAll();
	};

	return (
		<TopNavigation
			title={ title }
			exitOnTitleClick={ true }
			subNavigation={
				<TabBar
					tabs={ tabs }
					activePanel={ activePanel }
					setActivePanel={ setActivePanel }
				/>
			}
		>
			{ canSave && (
				<>
					<Button
						variant="primary"
						onClick={ handleSave }
						aria-busy={ isSaving }
					>
						{ isSaving
							? __( 'Saving…', 'woocommerce-paypal-payments' )
							: __( 'Save', 'woocommerce-paypal-payments' ) }
					</Button>
					<SaveStateMessage
						setIsSaving={ setIsSaving }
						isSaving={ isSaving }
					/>
				</>
			) }
		</TopNavigation>
	);
};

export default SettingsNavigation;

const SaveStateMessage = ( { setIsSaving, isSaving } ) => {
	const [ isVisible, setIsVisible ] = useState( false );
	const [ isAnimating, setIsAnimating ] = useState( false );
	const { onStarted, onFinished } = CommonHooks.useActivityObserver();
	const timerRef = useRef( null );

	const handleActivityStart = useCallback(
		( started ) => {
			if ( started.startsWith( 'persist' ) ) {
				setIsSaving( true );
				setIsVisible( false );
				setIsAnimating( false );

				if ( timerRef.current ) {
					clearTimeout( timerRef.current );
				}
			}
		},
		[ setIsSaving ]
	);

	const handleActivityDone = useCallback(
		( done, remaining ) => {
			if ( isSaving && remaining.length === 0 ) {
				setIsSaving( false );
				setIsVisible( true );
				setTimeout( () => setIsAnimating( true ), 50 );

				speak(
					__(
						'Settings saved successfully.',
						'woocommerce-paypal-payments'
					),
					'assertive'
				);

				timerRef.current = setTimeout( () => {
					setIsAnimating( false );
					setTimeout(
						() => setIsVisible( false ),
						NOTIFICATION_ANIMATION_DURATION
					);
				}, SAVE_CONFIRMATION_DURATION );
			}
		},
		[ isSaving, setIsSaving ]
	);

	useEffect( () => {
		onStarted( handleActivityStart );
		onFinished( handleActivityDone );
	}, [ onStarted, onFinished, handleActivityStart, handleActivityDone ] );

	if ( ! isVisible ) {
		return null;
	}

	const className = classNames( 'ppcp-r-navbar-notice', 'ppcp--success', {
		'ppcp--animating': isAnimating,
	} );

	return (
		<span className={ className } role="status" aria-live="polite">
			<span className="ppcp--inner-text">
				{ __( 'Completed', 'woocommerce-paypal-payments' ) }
			</span>
		</span>
	);
};
