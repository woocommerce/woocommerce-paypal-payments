import { __, sprintf } from '@wordpress/i18n';
import { useState, useMemo } from '@wordpress/element';
import { Button, Icon } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { reusableBlock } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

import {
	TodoSettingsBlock,
	FeatureSettingsBlock,
} from '../../../ReusableComponents/SettingsBlocks';
import { Content, ContentWrapper } from '../../../ReusableComponents/Elements';
import SettingsCard from '../../../ReusableComponents/SettingsCard';
import { TITLE_BADGE_POSITIVE } from '../../../ReusableComponents/TitleBadge';
import { useTodos } from '../../../../data/todos/hooks';
import { useMerchantInfo } from '../../../../data/common/hooks';
import { STORE_NAME } from '../../../../data/common';
import { getFeatures } from '../Components/Overview/features-config';

import {
	NOTIFICATION_ERROR,
	NOTIFICATION_SUCCESS,
} from '../../../ReusableComponents/Icons';

const TabOverview = () => {
	const { todos, isReady: areTodosReady } = useTodos();

	// Don't render todos section until data is ready
	const showTodos = areTodosReady && todos.length > 0;

	return (
		<div className="ppcp-r-tab-overview">
			{ showTodos && (
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
					<TodoSettingsBlock todosData={ todos } />
				</SettingsCard>
			) }

			<OverviewFeatures />
			<OverviewHelp />
		</div>
	);
};

export default TabOverview;

const OverviewFeatures = () => {
	const [ isRefreshing, setIsRefreshing ] = useState( false );
	const { merchant, features: merchantFeatures } = useMerchantInfo();
	const { refreshFeatureStatuses, setActiveModal } =
		useDispatch( STORE_NAME );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	// Get the features data with access to setActiveModal
	const featuresData = useMemo(
		() => getFeatures( setActiveModal ),
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
				const errorMessage = sprintf(
					/* translators: %s: error message */
					__(
						'Operation failed: %s Check WooCommerce logs for more details.',
						'woocommerce-paypal-payments'
					),
					result.message ||
						__( 'Unknown error', 'woocommerce-paypal-payments' )
				);

				createErrorNotice( errorMessage, {
					icon: NOTIFICATION_ERROR,
				} );
				console.error(
					'Failed to refresh features:',
					result.message || 'Unknown error'
				);
			} else {
				createSuccessNotice(
					__(
						'Features refreshed successfully.',
						'woocommerce-paypal-payments'
					),
					{
						icon: NOTIFICATION_SUCCESS,
					}
				);
			}
		} finally {
			setIsRefreshing( false );
		}
	};

	return (
		<SettingsCard
			className="ppcp-r-tab-overview-features"
			title={ __( 'Features', 'woocommerce-paypal-payments' ) }
			description={
				<OverviewFeatureDescription
					refreshHandler={ refreshHandler }
					isRefreshing={ isRefreshing }
				/>
			}
			contentContainer={ false }
		>
			<ContentWrapper>
				{ features.map( ( { id, ...feature } ) => (
					<OverviewFeatureItem
						key={ id }
						isBusy={ isRefreshing }
						isSandbox={ merchant.isSandbox }
						title={ feature.title }
						description={ feature.description }
						buttons={ feature.buttons }
						enabled={ feature.enabled }
						notes={ feature.notes }
					/>
				) ) }
			</ContentWrapper>
		</SettingsCard>
	);
};

const OverviewFeatureItem = ( {
	isBusy,
	isSandbox,
	title,
	description,
	buttons,
	enabled,
	notes,
} ) => {
	const getButtonUrl = ( button ) => {
		if ( button.urls ) {
			return isSandbox ? button.urls.sandbox : button.urls.live;
		}

		return button.url;
	};

	const visibleButtons = buttons.filter(
		( button ) =>
			! button.showWhen || // Learn more buttons
			( enabled && button.showWhen === 'enabled' ) ||
			( ! enabled && button.showWhen === 'disabled' )
	);

	const actionProps = {
		isBusy,
		enabled,
		notes,
		buttons: visibleButtons.map( ( button ) => ( {
			...button,
			url: getButtonUrl( button ),
		} ) ),
	};

	if ( enabled ) {
		actionProps.badge = {
			text: __( 'Active', 'woocommerce-paypal-payments' ),
			type: TITLE_BADGE_POSITIVE,
		};
	}

	return (
		<Content>
			<FeatureSettingsBlock
				title={ title }
				description={ description }
				actionProps={ actionProps }
			/>
		</Content>
	);
};

const OverviewFeatureDescription = ( { refreshHandler, isRefreshing } ) => {
	const buttonLabel = isRefreshing
		? __( 'Refreshing…', 'woocommerce-paypal-payments' )
		: __( 'Refresh', 'woocommerce-paypal-payments' );

	return (
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
				{ buttonLabel }
			</Button>
		</>
	);
};

const OverviewHelp = () => {
	return (
		<SettingsCard
			className="ppcp-r-tab-overview-help"
			title={ __( 'Help Center', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Access detailed guides and responsive support to streamline setup and enhance your experience.',
				'woocommerce-paypal-payments'
			) }
			contentContainer={ false }
		>
			<ContentWrapper>
				<Content>
					<FeatureSettingsBlock
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
									url: 'https://woocommerce.com/document/woocommerce-paypal-payments/ ',
								},
							],
						} }
					/>
				</Content>

				<Content>
					<FeatureSettingsBlock
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
									url: 'https://woocommerce.com/document/woocommerce-paypal-payments/#get-help ',
								},
							],
						} }
					/>
				</Content>
			</ContentWrapper>
		</SettingsCard>
	);
};
