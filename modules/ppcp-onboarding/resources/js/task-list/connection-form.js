/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { Form, TextControl } from '@woocommerce/components';
import { isEmail } from '@wordpress/url';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';
import { useDispatch, useSelect } from '@wordpress/data';

const WC_PAYPAL_NAMESPACE = '/wc-paypal/v1';

export const ConnectionForm = ( { markConfigured, paymentGateway } ) => {
	const { createNotice } = useDispatch( 'core/notices' );
	const { updateOptions } = useDispatch( OPTIONS_STORE_NAME );
	const isOptionsUpdating = useSelect( ( select ) => {
		return select( OPTIONS_STORE_NAME ).isOptionsUpdating();
	} );
	const { setup_help_text: setupHelpText } = paymentGateway;

	const updateSettingsManually = ( values ) => {
		const productionValues = Object.keys( values ).reduce(
			( vals, key ) => {
				const prodKey = key + '_production';
				return {
					...vals,
					[ prodKey ]: values[ key ],
				};
			},
			{}
		);

		/**
		 * merchant data can be the same across sandbox and production, that's why we set it as
		 * standalone as well.
		 */
		const optionValues = {
			enabled: true,
			sandbox_on: false,
			merchant_email: values.merchant_email,
			merchant_id: values.merchant_id,
			...productionValues,
		};

		updateOptions( {
			'woocommerce-ppcp-settings': optionValues,
		} )
			.then( () => {
				createNotice(
					'success',
					__(
						'PayPal connected successfully.',
						'woocommerce-paypal-payments'
					)
				);
				markConfigured();
			} )
			.catch( () => {
				createNotice(
					'error',
					__(
						'There was a problem saving your payment settings.',
						'woocommerce-paypal-payments'
					)
				);
			} );
	};

	const setCredentials = ( values ) => {
		apiFetch( {
			path: WC_PAYPAL_NAMESPACE + '/onboarding/set-credentials',
			method: 'POST',
			data: {
				environment: 'production',
				...values,
			},
		} )
			.then( ( result ) => {
				if ( result && result.data ) {
					throw new Error();
				}
				markConfigured();
			} )
			.catch( () => {
				updateSettingsManually( values, markConfigured );
				createNotice(
					'error',
					__(
						'There was a problem updating the credentials.',
						'woocommerce-paypal-payments'
					)
				);
			} );
	};

	const validate = ( values ) => {
		const errors = {};

		if ( ! values.merchant_email ) {
			errors.merchant_email = __(
				'Please enter your Merchant email',
				'woocommerce-paypal-payments'
			);
		}
		if ( ! isEmail( values.merchant_email ) ) {
			errors.merchant_email = __(
				'Please enter a valid email address',
				'woocommerce-paypal-payments'
			);
		}
		if ( ! values.merchant_id ) {
			errors.merchant_id = __(
				'Please enter your Merchant Id',
				'woocommerce-paypal-payments'
			);
		}
		if ( ! values.client_id ) {
			errors.client_id = __(
				'Please enter your Client Id',
				'woocommerce-paypal-payments'
			);
		}
		if ( ! values.client_secret ) {
			errors.client_secret = __(
				'Please enter your Client Secret',
				'woocommerce-paypal-payments'
			);
		}

		return errors;
	};

	// @todo Preload options here.
	const options = {};

	const getInitialFormValues = () => {
		return [
			'merchant_email',
			'merchant_id',
			'client_id',
			'client_secret',
		].reduce( ( initialVals, key ) => {
			return {
				...initialVals,
				[ key ]: options[ key + '_production' ] || '',
			};
		}, {} );
	};

	return (
		<Form
			initialValues={ getInitialFormValues() }
			onSubmit={ ( values ) => setCredentials( values, markConfigured ) }
			validate={ validate }
		>
			{ ( { getInputProps, handleSubmit } ) => {
				return (
					<>
						<TextControl
							label={ __(
								'Email address',
								'woocommerce-paypal-payments'
							) }
							required
							{ ...getInputProps( 'merchant_email' ) }
						/>
						<TextControl
							label={ __(
								'Merchant Id',
								'woocommerce-paypal-payments'
							) }
							required
							{ ...getInputProps( 'merchant_id' ) }
						/>
						<TextControl
							label={ __(
								'Client Id',
								'woocommerce-paypal-payments'
							) }
							required
							{ ...getInputProps( 'client_id' ) }
						/>
						<TextControl
							label={ __(
								'Secret Key',
								'woocommerce-paypal-payments'
							) }
							required
							{ ...getInputProps( 'client_secret' ) }
						/>
						<Button
							isPrimary
							isBusy={ isOptionsUpdating }
							onClick={ handleSubmit }
						>
							{ __( 'Proceed', 'woocommerce-paypal-payments' ) }
						</Button>

						<p>{ setupHelpText }</p>
					</>
				);
			} }
		</Form>
	);
};
