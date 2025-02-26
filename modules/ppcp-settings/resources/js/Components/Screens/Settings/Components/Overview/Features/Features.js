import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import FeatureItem from './FeatureItem';
import FeatureDescription from './FeatureDescription';
import { ContentWrapper } from '../../../../../ReusableComponents/Elements';
import SettingsCard from '../../../../../ReusableComponents/SettingsCard';
import { useMerchantInfo } from '../../../../../../data/common/hooks';
import { STORE_NAME as COMMON_STORE_NAME } from '../../../../../../data/common';
import {
	NOTIFICATION_ERROR,
	NOTIFICATION_SUCCESS,
} from '../../../../../ReusableComponents/Icons';
import { useFeatures } from '../../../../../../data/features/hooks';

const Features = () => {
	const [ isRefreshing, setIsRefreshing ] = useState( false );
	const { merchant } = useMerchantInfo();
	const { features, fetchFeatures } = useFeatures();
	const { refreshFeatureStatuses } = useDispatch( COMMON_STORE_NAME );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	if ( ! features || features.length === 0 ) {
		return null;
	}

	const refreshHandler = async () => {
		setIsRefreshing( true );
		try {
			const statusResult = await refreshFeatureStatuses();
			if ( ! statusResult?.success ) {
				throw new Error(
					statusResult?.message || 'Failed to refresh status'
				);
			}

			const featuresResult = await fetchFeatures();
			if ( featuresResult.success ) {
				createSuccessNotice(
					__(
						'Features refreshed successfully.',
						'woocommerce-paypal-payments'
					),
					{ icon: NOTIFICATION_SUCCESS }
				);
			} else {
				throw new Error(
					featuresResult?.message || 'Failed to fetch features'
				);
			}
		} catch ( error ) {
			createErrorNotice(
				sprintf(
					/* translators: %s: error message */
					__( 'Operation failed: %s', 'woocommerce-paypal-payments' ),
					error.message ||
						__( 'Unknown error', 'woocommerce-paypal-payments' )
				),
				{ icon: NOTIFICATION_ERROR }
			);
		} finally {
			setIsRefreshing( false );
		}
	};

	return (
		<SettingsCard
			className="ppcp-r-tab-overview-features"
			title={ __( 'Features', 'woocommerce-paypal-payments' ) }
			description={
				<FeatureDescription
					refreshHandler={ refreshHandler }
					isRefreshing={ isRefreshing }
				/>
			}
			contentContainer={ false }
		>
			<ContentWrapper>
				{ features.map( ( { id, enabled, ...feature } ) => (
					<FeatureItem
						key={ id }
						isBusy={ isRefreshing }
						isSandbox={ merchant.isSandbox }
						enabled={ enabled }
						{ ...feature }
					/>
				) ) }
			</ContentWrapper>
		</SettingsCard>
	);
};

export default Features;
