/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */
import { useDispatch } from '@wordpress/data';

import { STORE_NAME } from './constants';
import { createHooksForStore } from '../utils';

const useHooks = () => {
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );
	const { persist } = useDispatch( STORE_NAME );

	// Read-only flags and derived state.
	const [ isReady ] = useTransient( 'isReady' );

	// Persistent accessors.
	const [ invoicePrefix, setInvoicePrefix ] =
		usePersistent( 'invoicePrefix' );
	const [ brandName, setBrandName ] = usePersistent( 'brandName' );
	const [ softDescriptor, setSoftDescriptor ] =
		usePersistent( 'softDescriptor' );

	const [ subtotalAdjustment, setSubtotalAdjustment ] =
		usePersistent( 'subtotalAdjustment' );
	const [ landingPage, setLandingPage ] = usePersistent( 'landingPage' );
	const [ buttonLanguage, setButtonLanguage ] =
		usePersistent( 'buttonLanguage' );

	const [ authorizeOnly, setAuthorizeOnly ] =
		usePersistent( 'authorizeOnly' );
	const [ captureVirtualOnlyOrders, setCaptureVirtualOnlyOrders ] =
		usePersistent( 'captureVirtualOrders' );
	const [ savePaypalAndVenmo, setSavePaypalAndVenmo ] =
		usePersistent( 'savePaypalAndVenmo' );
	const [ saveCardDetails, setSaveCardDetails ] =
		usePersistent( 'saveCardDetails' );
	const [ payNowExperience, setPayNowExperience ] =
		usePersistent( 'enablePayNow' );
	const [ logging, setLogging ] = usePersistent( 'enableLogging' );

	const [ disabledCards, setDisabledCards ] =
		usePersistent( 'disabledCards' );

	return {
		persist,
		isReady,
		invoicePrefix,
		setInvoicePrefix,
		authorizeOnly,
		setAuthorizeOnly,
		captureVirtualOnlyOrders,
		setCaptureVirtualOnlyOrders,
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		saveCardDetails,
		setSaveCardDetails,
		payNowExperience,
		setPayNowExperience,
		logging,
		setLogging,
		subtotalAdjustment,
		setSubtotalAdjustment,
		brandName,
		setBrandName,
		softDescriptor,
		setSoftDescriptor,
		landingPage,
		setLandingPage,
		buttonLanguage,
		setButtonLanguage,
		disabledCards,
		setDisabledCards,
	};
};

export const useStore = () => {
	const { persist, isReady } = useHooks();
	return { persist, isReady };
};

export const useSettings = () => {
	const {
		invoicePrefix,
		setInvoicePrefix,
		authorizeOnly,
		setAuthorizeOnly,
		captureVirtualOnlyOrders,
		setCaptureVirtualOnlyOrders,
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		saveCardDetails,
		setSaveCardDetails,
		payNowExperience,
		setPayNowExperience,
		logging,
		setLogging,
		subtotalAdjustment,
		setSubtotalAdjustment,
		brandName,
		setBrandName,
		softDescriptor,
		setSoftDescriptor,
		landingPage,
		setLandingPage,
		buttonLanguage,
		setButtonLanguage,
		disabledCards,
		setDisabledCards,
	} = useHooks();

	return {
		invoicePrefix,
		setInvoicePrefix,
		authorizeOnly,
		setAuthorizeOnly,
		captureVirtualOnlyOrders,
		setCaptureVirtualOnlyOrders,
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		saveCardDetails,
		setSaveCardDetails,
		payNowExperience,
		setPayNowExperience,
		logging,
		setLogging,
		subtotalAdjustment,
		setSubtotalAdjustment,
		brandName,
		setBrandName,
		softDescriptor,
		setSoftDescriptor,
		landingPage,
		setLandingPage,
		buttonLanguage,
		setButtonLanguage,
		disabledCards,
		setDisabledCards,
	};
};
