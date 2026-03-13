import { Button } from '@wordpress/components';
import {
	Content,
	ContentWrapper,
	Header,
	Action,
	Description,
} from '../../../../ReusableComponents/Elements';
import { PPIcon } from '../../../../ReusableComponents/Icons';
import classNames from 'classnames';

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
								const { type, text, onClick } = buttonData;

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
