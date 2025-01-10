import { __ } from '@wordpress/i18n';
import { useState, useMemo } from '@wordpress/element';
import { Button, Icon } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { reusableBlock } from '@wordpress/icons';

import SettingsCard from '../../ReusableComponents/SettingsCard';
import TodoSettingsBlock from '../../ReusableComponents/SettingsBlocks/TodoSettingsBlock';
import FeatureSettingsBlock from '../../ReusableComponents/SettingsBlocks/FeatureSettingsBlock';
import { TITLE_BADGE_POSITIVE } from '../../ReusableComponents/TitleBadge';
import { useMerchantInfo } from '../../../data/common/hooks';
import { STORE_NAME } from '../../../data/common';
import Features from './TabSettingsElements/Blocks/Features';
import { todosData } from '../../../data/settings/tab-overview-todos-data';

const TabOverview = () => {
	const [ isRefreshing, setIsRefreshing ] = useState( false );

	const { merchant, merchantFeatures } = useMerchantInfo();
	const { refreshFeatureStatuses, setActiveModal } =
		useDispatch( STORE_NAME );

	// Get the features data with access to setActiveModal
	const featuresData = useMemo(
		() => Features.getFeatures( setActiveModal ),
		[ setActiveModal ]
	);

	// Map merchant features status to our config
	const features = useMemo( () => {
		return featuresData.map( ( feature ) => {
			const merchantFeature = merchantFeatures?.[ feature.id ];
			return {
				...feature,
				enabled: merchantFeature?.enabled ?? false,
			};
		} );
	}, [ featuresData, merchantFeatures ] );

	const refreshHandler = async () => {
		setIsRefreshing( true );
		try {
			const result = await refreshFeatureStatuses();
			if ( result && ! result.success ) {
				console.error(
					'Failed to refresh features:',
					result.message || 'Unknown error'
				);
			}
		} finally {
			setIsRefreshing( false );
		}
	};

	return (
		<div className="ppcp-r-tab-overview">
			{ todosData.length > 0 && (
				<SettingsCard
					className="ppcp-r-tab-overview-todo"
					title={ __(
						'Things to do next',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'Complete these tasks to keep your store updated with the latest products and services.',
						'woocommerce-paypal-payments'
					) }
				>
					<TodoSettingsBlock todosData={ todosData } />
				</SettingsCard>
			) }

			<SettingsCard
				className="ppcp-r-tab-overview-features"
				title={ __( 'Features', 'woocommerce-paypal-payments' ) }
				description={
					<>
						<p>
							{ __(
								'Enable additional features and capabilities on your WooCommerce store.',
								'woocommerce-paypal-payments'
							) }
						</p>
						<p>
							{ __(
								'Click Refresh to update your current features after making changes.',
								'woocommerce-paypal-payments'
							) }
						</p>
						<Button
							variant="tertiary"
							onClick={ refreshHandler }
							disabled={ isRefreshing }
						>
							<Icon icon={ reusableBlock } size={ 18 } />
							{ isRefreshing
								? __(
										'Refreshing…',
										'woocommerce-paypal-payments'
								  )
								: __(
										'Refresh',
										'woocommerce-paypal-payments'
								  ) }
						</Button>
					</>
				}
				contentItems={ features.map( ( feature ) => {
					return (
						<FeatureSettingsBlock
							key={ feature.id }
							title={ feature.title }
							description={ feature.description }
							actionProps={ {
								buttons: feature.buttons
									.filter(
										( button ) =>
											! button.showWhen || // Learn more buttons
											( feature.enabled &&
												button.showWhen ===
													'enabled' ) ||
											( ! feature.enabled &&
												button.showWhen === 'disabled' )
									)
									.map( ( button ) => ( {
										...button,
										url: button.urls
											? merchant?.isSandbox
												? button.urls.sandbox
												: button.urls.live
											: button.url,
									} ) ),
								enabled: feature.enabled,
								notes: feature.notes,
								badge: feature.enabled
									? {
											text: __(
												'Active',
												'woocommerce-paypal-payments'
											),
											type: TITLE_BADGE_POSITIVE,
									  }
									: undefined,
							} }
						/>
					);
				} ) }
			/>

			<SettingsCard
				className="ppcp-r-tab-overview-help"
				title={ __( 'Help Center', 'woocommerce-paypal-payments' ) }
				description={ __(
					'Access detailed guides and responsive support to streamline setup and enhance your experience.',
					'woocommerce-paypal-payments'
				) }
				contentItems={ [
					<FeatureSettingsBlock
						key="documentation"
						title={ __(
							'Documentation',
							'woocommerce-paypal-payments'
						) }
						description={ __(
							'Find detailed guides and resources to help you set up, manage, and optimize your PayPal integration.',
							'woocommerce-paypal-payments'
						) }
						actionProps={ {
							buttons: [
								{
									type: 'tertiary',
									text: __(
										'View full documentation',
										'woocommerce-paypal-payments'
									),
									url: '#',
								},
							],
						} }
					/>,
					<FeatureSettingsBlock
						key="support"
						title={ __( 'Support', 'woocommerce-paypal-payments' ) }
						description={ __(
							'Need help? Access troubleshooting tips or contact our support team for personalized assistance.',
							'woocommerce-paypal-payments'
						) }
						actionProps={ {
							buttons: [
								{
									type: 'tertiary',
									text: __(
										'View support options',
										'woocommerce-paypal-payments'
									),
									url: '#',
								},
							],
						} }
					/>,
				] }
			/>
		</div>
	);
};

export default TabOverview;
