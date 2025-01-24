import classNames from 'classnames';

import { Content, ContentWrapper } from './Elements';

const SettingsCard = ( {
	id,
	className: extraClassName,
	title,
	description,
	children,
	contentItems,
	contentContainer = true,
} ) => {
	const className = classNames( 'ppcp-r-settings-card', extraClassName );

	const renderContent = () => {
		// If contentItems array is provided, wrap each item in Content component
		if ( contentItems ) {
			return (
				<ContentWrapper>
					{ contentItems.map( ( item ) => (
						<Content key={ item.key } id={ item.key }>
							{ item }
						</Content>
					) ) }
				</ContentWrapper>
			);
		}

		// Otherwise handle regular children with contentContainer prop
		if ( contentContainer ) {
			return <Content>{ children }</Content>;
		}

		return children;
	};

	return (
		<div id={ id } className={ className }>
			<div className="ppcp-r-settings-card__header">
				<div className="ppcp-r-settings-card__content-inner">
					<span className="ppcp-r-settings-card__title">
						{ title }
					</span>
					<p className="ppcp-r-settings-card__description">
						{ description }
					</p>
				</div>
			</div>
			{ renderContent() }
		</div>
	);
};

export default SettingsCard;
