import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import classNames from 'classnames';

import SettingsToggleBlock from '../../../ReusableComponents/SettingsToggleBlock';
import DataStoreControl from '../../../ReusableComponents/DataStoreControl';
import BusyStateWrapper from '../../../ReusableComponents/BusyStateWrapper';
import {
	useDirectAuthentication,
	useSandboxConnection,
} from '../../../../hooks/useHandleConnections';
import { OnboardingHooks } from '../../../../data';

const FORM_ERRORS = {
	noClientId: __(
		'Please enter your Client ID',
		'woocommerce-paypal-payments'
	),
	noClientSecret: __(
		'Please enter your Secret Key',
		'woocommerce-paypal-payments'
	),
	invalidClientId: __(
		'Please enter a valid Client ID',
		'woocommerce-paypal-payments'
	),
};

const ManualConnectionForm = () => {
	const [ clientValid, setClientValid ] = useState( false );
	const [ secretValid, setSecretValid ] = useState( false );
	const { isSandboxMode } = useSandboxConnection();
	const {
		manualClientId,
		setManualClientId,
		manualClientSecret,
		setManualClientSecret,
	} = OnboardingHooks.useManualConnectionForm();
	const {
		handleDirectAuthentication,
		isManualConnectionMode,
		setManualConnectionMode,
	} = useDirectAuthentication();
	const refClientId = useRef( null );
	const refClientSecret = useRef( null );

	// Form data validation and sanitation.
	const getManualConnectionDetails = useCallback( () => {
		const checks = [
			{
				ref: refClientId,
				valid: () => manualClientId,
				errorMessage: FORM_ERRORS.noClientId,
			},
			{
				ref: refClientId,
				valid: () => clientValid,
				errorMessage: FORM_ERRORS.invalidClientId,
			},
			{
				ref: refClientSecret,
				valid: () => manualClientSecret && secretValid,
				errorMessage: FORM_ERRORS.noClientSecret,
			},
		];

		for ( const { ref, valid, errorMessage } of checks ) {
			if ( valid() ) {
				continue;
			}

			ref?.current?.focus();
			throw new Error( errorMessage );
		}

		return {
			clientId: manualClientId,
			clientSecret: manualClientSecret,
			isSandbox: isSandboxMode,
		};
	}, [
		manualClientId,
		manualClientSecret,
		isSandboxMode,
		clientValid,
		secretValid,
	] );

	// On-the-fly form validation.
	useEffect( () => {
		setClientValid(
			! manualClientId || /^A[\w-]{79}$/.test( manualClientId )
		);
		setSecretValid( manualClientSecret && manualClientSecret.length > 0 );
	}, [ manualClientId, manualClientSecret ] );

	// Environment-specific field labels.
	const clientIdLabel = useMemo(
		() =>
			isSandboxMode
				? __( 'Sandbox Client ID', 'woocommerce-paypal-payments' )
				: __( 'Live Client ID', 'woocommerce-paypal-payments' ),
		[ isSandboxMode ]
	);

	const secretKeyLabel = useMemo(
		() =>
			isSandboxMode
				? __( 'Sandbox Secret Key', 'woocommerce-paypal-payments' )
				: __( 'Live Secret Key', 'woocommerce-paypal-payments' ),
		[ isSandboxMode ]
	);

	// Translations with placeholders.
	const advancedUsersDescription = sprintf(
		// translators: %s: Link to PayPal REST application guide
		__(
			'For advanced users: Connect a custom PayPal REST app for full control over your integration. For more information on creating a PayPal REST application, <a target="_blank" href="%s">click here</a>.',
			'woocommerce-paypal-payments'
		),
		'https://woocommerce.com/document/woocommerce-paypal-payments/#manual-credential-input'
	);

	// Button click handler.
	const handleManualConnect = useCallback(
		() => handleDirectAuthentication( getManualConnectionDetails ),
		[ handleDirectAuthentication, getManualConnectionDetails ]
	);

	return (
		<BusyStateWrapper
			onBusy={ ( props ) => ( {
				disabled: true,
				label: props.label + ' ...',
			} ) }
		>
			<SettingsToggleBlock
				label={ __(
					'Manually Connect',
					'woocommerce-paypal-payments'
				) }
				description={ advancedUsersDescription }
				isToggled={ !! isManualConnectionMode }
				setToggled={ setManualConnectionMode }
			>
				<DataStoreControl
					control={ TextControl }
					ref={ refClientId }
					label={ clientIdLabel }
					value={ manualClientId }
					onChange={ setManualClientId }
					className={ classNames( {
						'ppcp--has-error': ! clientValid,
					} ) }
				/>
				{ clientValid || (
					<p className="client-id-error">
						{ FORM_ERRORS.invalidClientId }
					</p>
				) }
				<DataStoreControl
					control={ TextControl }
					ref={ refClientSecret }
					label={ secretKeyLabel }
					value={ manualClientSecret }
					onChange={ setManualClientSecret }
					type="password"
				/>
				<Button
					variant="secondary"
					className="small-button"
					onClick={ handleManualConnect }
				>
					{ __( 'Connect Account', 'woocommerce-paypal-payments' ) }
				</Button>
			</SettingsToggleBlock>
		</BusyStateWrapper>
	);
};

export default ManualConnectionForm;
