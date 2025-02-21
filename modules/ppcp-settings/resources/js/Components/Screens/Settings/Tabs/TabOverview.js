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
import {
	Content,
	ContentWrapper,
	CardActions,
} from '../../../ReusableComponents/Elements';
import SettingsCard from '../../../ReusableComponents/SettingsCard';
import { TITLE_BADGE_POSITIVE } from '../../../ReusableComponents/TitleBadge';
import {
	CommonStoreName,
	TodosStoreName,
	CommonHooks,
	TodosHooks,
} from '../../../../data';

import { getFeatures } from '../Components/Overview/features-config';

import {
	NOTIFICATION_ERROR,
	NOTIFICATION_SUCCESS,
} from '../../../ReusableComponents/Icons';
import SpinnerOverlay from '../../../ReusableComponents/SpinnerOverlay';

const TabOverview = () => {
	const { isReady: areTodosReady } = TodosHooks.useStore();
	const { isReady: merchantIsReady } = CommonHooks.useStore();

	if ( ! areTodosReady || ! merchantIsReady ) {
		return <SpinnerOverlay asModal={ true } />;
	}

	return (
		<div className="ppcp-r-tab-overview">
			<OverviewTodos />
			<OverviewFeatures />
			<OverviewHelp />
		</div>
	);
};

export default TabOverview;

const OverviewTodos = () => {
	const [ isResetting, setIsResetting ] = useState( false );
	const { todos, dismissTodo } = TodosHooks.useTodos();
	const { isReady: areTodosReady } = TodosHooks.useStore();
	const { setActiveModal, setActiveHighlight } =
		useDispatch( CommonStoreName );
	const { resetDismissedTodos, setDismissedTodos } =
		useDispatch( TodosStoreName );
	const { createSuccessNotice } = useDispatch( noticesStore );

	const showTodos = areTodosReady && todos.length > 0;

	const resetHandler = async () => {
		setIsResetting( true );
		try {
			await setDismissedTodos( [] );
			await resetDismissedTodos();

			createSuccessNotice(
				__(
					'Dismissed items restored successfully.',
					'woocommerce-paypal-payments'
				),
				{ icon: NOTIFICATION_SUCCESS }
			);
		} finally {
			setIsResetting( false );
		}
	};

	if ( ! showTodos ) {
		return null;
	}

	return (
		<SettingsCard
			className="ppcp-r-tab-overview-todo"
			title={ __( 'Things to do next', 'woocommerce-paypal-payments' ) }
			description={
				<>
					<p>
						{ __(
							'Complete these tasks to keep your store updated with the latest products and services.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<CardActions>
						<Button
							variant="tertiary"
							onClick={ resetHandler }
							disabled={ isResetting }
						>
							<Icon icon={ reusableBlock } size={ 18 } />
							{ isResetting
								? __(
										'Restoring…',
										'woocommerce-paypal-payments'
								  )
								: __(
										'Restore dismissed Things To Do',
										'woocommerce-paypal-payments'
								  ) }
						</Button>
					</CardActions>
				</>
			}
		>
			<TodoSettingsBlock
				todosData={ todos }
				setActiveModal={ setActiveModal }
				setActiveHighlight={ setActiveHighlight }
				onDismissTodo={ dismissTodo }
			/>
		</SettingsCard>
	);
};

const OverviewFeatures = () => {
	const [ isRefreshing, setIsRefreshing ] = useState( false );
	const { merchant, features: merchantFeatures } =
		CommonHooks.useMerchantInfo();
	const { refreshFeatureStatuses, setActiveModal } =
		useDispatch( CommonStoreName );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	// Get the features data with access to setActiveModal
	const featuresData = useMemo(
		() => getFeatures( setActiveModal ),
		[ setActiveModal ]
	);

	// Map merchant features status to the config
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
			<CardActions>
				<Button
					variant="tertiary"
					onClick={ refreshHandler }
					disabled={ isRefreshing }
				>
					<Icon icon={ reusableBlock } size={ 18 } />
					{ buttonLabel }
				</Button>
			</CardActions>
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
									url: 'https://woocommerce.com/document/woocommerce-paypal-payments/',
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
