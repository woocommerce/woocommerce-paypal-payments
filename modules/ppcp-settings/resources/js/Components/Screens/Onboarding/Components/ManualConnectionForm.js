import { useCallback, useMemo, useRef, useState } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import classNames from 'classnames';

import SettingsToggleBlock from '@ppcp-settings/Components/ReusableComponents/SettingsToggleBlock';
import DataStoreControl from '@ppcp-settings/Components/ReusableComponents/DataStoreControl';
import BusyStateWrapper from '@ppcp-settings/Components/ReusableComponents/BusyStateWrapper';
import {
	useDirectAuthentication,
	useSandboxConnection,
} from '@ppcp-settings/hooks/useHandleConnections';
import { OnboardingHooks } from '@ppcp-settings/data';
import { useManualConnection } from '@ppcp-settings/data/common/hooks';

const FORM_ERRORS = {
	noClientId: __(
		'Please enter your Client ID',
		'woocommerce-paypal-payments'
	),
	noClientSecret: __(
		'Please enter your Secret Key',
		'woocommerce-paypal-payments'
	),
};

const ManualConnectionForm = () => {
	const [ clientIdError, setClientIdError ] = useState( '' );
	const [ clientSecretError, setClientSecretError ] = useState( '' );
	const { isSandboxMode } = useSandboxConnection();
	const {
		manualClientId,
		setManualClientId,
		manualClientSecret,
		setManualClientSecret,
	} = OnboardingHooks.useManualConnectionForm();

	const { handleDirectAuthentication } = useDirectAuthentication();

	const { isManualConnectionMode, setManualConnectionMode } =
		useManualConnection();

	const refClientId = useRef( null );
	const refClientSecret = useRef( null );

	const handleToggle = ( isEnabled ) => {
		setManualConnectionMode( isEnabled, 'user' );
	};

	// Form data validation and sanitation.
	const getManualConnectionDetails = useCallback( () => {
		setClientIdError( '' );
		setClientSecretError( '' );

		const checks = [
			{
				ref: refClientId,
				valid: () => manualClientId,
				setError: setClientIdError,
				errorMessage: FORM_ERRORS.noClientId,
			},
			{
				ref: refClientSecret,
				valid: () => manualClientSecret,
				setError: setClientSecretError,
				errorMessage: FORM_ERRORS.noClientSecret,
			},
		];

		for ( const { ref, valid, setError, errorMessage } of checks ) {
			if ( valid() ) {
				continue;
			}

			setError( errorMessage );
			ref?.current?.focus();
			throw new Error( errorMessage );
		}

		return {
			clientId: manualClientId,
			clientSecret: manualClientSecret,
			isSandbox: isSandboxMode,
		};
	}, [ manualClientId, manualClientSecret, isSandboxMode ] );

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
				setToggled={ handleToggle }
			>
				<DataStoreControl
					__nextHasNoMarginBottom
					control={ TextControl }
					ref={ refClientId }
					label={ clientIdLabel }
					value={ manualClientId }
					onChange={ ( value ) => {
						setManualClientId( value );
						if ( clientIdError ) {
							setClientIdError( '' );
						}
					} }
					onConfirm={ handleManualConnect }
					className={ classNames( {
						'ppcp--has-error': !! clientIdError,
					} ) }
				/>
				{ clientIdError && (
					<p className="manual-credentials-error">
						{ clientIdError }
					</p>
				) }
				<DataStoreControl
					__nextHasNoMarginBottom
					control={ TextControl }
					ref={ refClientSecret }
					label={ secretKeyLabel }
					value={ manualClientSecret }
					onChange={ ( value ) => {
						setManualClientSecret( value );
						if ( clientSecretError ) {
							setClientSecretError( '' );
						}
					} }
					onConfirm={ handleManualConnect }
					type="password"
					className={ classNames( {
						'ppcp--has-error': !! clientSecretError,
					} ) }
				/>
				{ clientSecretError && (
					<p className="manual-credentials-error">
						{ clientSecretError }
					</p>
				) }
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
