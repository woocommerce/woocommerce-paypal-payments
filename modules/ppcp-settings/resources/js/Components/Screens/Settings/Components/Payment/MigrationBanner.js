import { __ } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	ToggleControl,
	RadioControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import {
	Content,
	ContentWrapper,
	Header,
	Action,
	Description,
} from '../../../../ReusableComponents/Elements';
import { PPIcon } from '../../../../ReusableComponents/Icons';

import PaymentMethodModal from '@ppcp-settings/Components/ReusableComponents/PaymentMethodModal';
import { PaymentHooks } from '@ppcp-settings/data';
import classNames from 'classnames';
import { ControlButton } from '@ppcp-settings/Components/ReusableComponents/Controls';

const MigrationBanner = ( {
	id,
	className,
	title,
	description,
	actionProps,
} ) => {
	const migrationBannerClassNames = classNames(
		'ppcp-r-settings-card',
		className
	);
	const props = {
		className: migrationBannerClassNames,
		id,
	};

	const titleId = id ? `${ id }-title` : undefined;
	const descriptionId = id ? `${ id }-description` : undefined;
	return (
		<div { ...props } role="region" aria-labelledby={ titleId }>
			<ContentWrapper>
				<Content asCard={ false }>
					<Header>
						<h2
							id={ titleId }
							className="ppcp-r-settings-card__title"
						>
							{ title }
						</h2>
						<Description>{ description }</Description>
					</Header>
					<Action>
						<div className="ppcp--action-buttons">
							{ actionProps?.buttons.map( ( buttonData ) => {
								const {
									class: className,
									type,
									text,
									onClick,
								} = buttonData;

								return (
									<Button
										className="small-button"
										isBusy={ false }
										variant={ type }
										onClick={ onClick }
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
					<span className={ `${ className }__icon-close` }>
						<PPIcon imageName="icon-close.svg" />
					</span>
				</Content>
			</ContentWrapper>
		</div>
	);
};

export default MigrationBanner;
