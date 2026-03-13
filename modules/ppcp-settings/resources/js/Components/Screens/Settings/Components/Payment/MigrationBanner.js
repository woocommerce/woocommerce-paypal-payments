import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import {
	Content,
	ContentWrapper,
	Header,
	Action,
	Description,
} from '../../../../ReusableComponents/Elements';
import { PPIcon } from '../../../../ReusableComponents/Icons';
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';
import TitleBadge, {
	TITLE_BADGE_INFO,
} from '@ppcp-settings/Components/ReusableComponents/TitleBadge';

let isBannerDismissed = false;

const MigrationBanner = ( {
	id,
	className,
	title,
	description,
	actionProps,
} ) => {
	const [ isDismissed, setIsDismissed ] = useState( () => isBannerDismissed );

	const dismiss = () => {
		isBannerDismissed = true;
		setIsDismissed( true );
	};

	if ( isDismissed ) {
		return null;
	}

	const migrationBannerClassNames = classNames(
		'ppcp-r-settings-card',
		className
	);
	const props = {
		className: migrationBannerClassNames,
		id,
	};

	const titleId = id ? `${ id }-title` : undefined;

	return (
		<div { ...props } role="region" aria-labelledby={ titleId }>
			<ContentWrapper>
				<Content asCard={ false }>
					<Header>
						<div className="ppcp--title-wrapper">
							<h2
								id={ titleId }
								className="ppcp-r-settings-card__title"
							>
								{ title }
							</h2>
							<TitleBadge
								type={ TITLE_BADGE_INFO }
								text={ __(
									"You're eligible",
									'woocommerce-paypal-payments'
								) }
							/>
						</div>
						<Description>{ description }</Description>
					</Header>
					<Action>
						<div className="ppcp--action-buttons">
							{ actionProps?.buttons.map( ( buttonData ) => {
								const { type, text, onClick } = buttonData;
								const isDismiss = type === 'tertiary';

								return (
									<Button
										className="small-button"
										isBusy={ false }
										variant={ type }
										onClick={
											isDismiss ? dismiss : onClick
										}
									>
										{ text }
									</Button>
								);
							} ) }
						</div>
					</Action>
				</Content>
				<Content asCard={ false } className={ `${ className }__icon` }>
					<PPIcon imageName="icon-button-payment-method-advanced-cards-large.svg" />
					<button
						className={ `${ className }__icon-close` }
						aria-label={ __(
							'Dismiss',
							'woocommerce-paypal-payments'
						) }
						onClick={ dismiss }
					>
						<PPIcon imageName="icon-close.svg" />
					</button>
				</Content>
			</ContentWrapper>
		</div>
	);
};

export default MigrationBanner;
