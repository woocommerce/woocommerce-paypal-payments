import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import {
	Content,
	ContentWrapper,
	Header,
	Action,
	Description,
} from '@ppcp-settings/Components/ReusableComponents/Elements';
import { PPIcon } from '../../../ReusableComponents/Icons';
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';

let isBannerDismissed = false;

const AgenticBetaBanner = ( {
	id,
	className,
	title,
	description,
	actionProps,
} ) => {
	const [ isDismissed, setIsDismissed ] = useState( () => isBannerDismissed );
	const [ isMigrating, setIsMigrating ] = useState( false );

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
						</div>
						<Description>{ description }</Description>
					</Header>
					<Action>
						<div className="ppcp--action-buttons">
							{ actionProps?.buttons.map(
								( buttonData, index ) => {
									const { type, text } = buttonData;
									const isDismiss = type === 'tertiary';

									return (
										<Button
											key={ index }
											className="small-button"
											isBusy={
												! isDismiss && isMigrating
											}
											variant={ type }
											disabled={ isMigrating }
											onClick={ isDismiss ? dismiss : '' }
										>
											{ text }
										</Button>
									);
								}
							) }
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

export default AgenticBetaBanner;
