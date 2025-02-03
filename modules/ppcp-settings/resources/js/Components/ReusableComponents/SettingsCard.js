import classNames from 'classnames';

import { Content } from './Elements';

const SettingsCard = ( {
	id,
	className,
	title,
	description,
	children,
	contentContainer = true,
} ) => {
	const cardClassNames = classNames( 'ppcp-r-settings-card', className );
	const cardProps = {
		className: cardClassNames,
		id,
	};

	return (
		<div { ...cardProps }>
			<div className="ppcp-r-settings-card__header">
				<div className="ppcp-r-settings-card__content-inner">
					<span className="ppcp-r-settings-card__title">
						{ title }
					</span>
					<div className="ppcp-r-settings-card__description">
						{ description }
					</div>
				</div>
			</div>

			<InnerContent showCards={ contentContainer }>
				{ children }
			</InnerContent>
		</div>
	);
};

export default SettingsCard;

const InnerContent = ( { showCards, children } ) => {
	if ( showCards ) {
		return <Content>{ children }</Content>;
	}

	return children;
};
