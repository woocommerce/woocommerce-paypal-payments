import classNames from 'classnames';

import { Content } from './Elements';

/**
 * Renders a settings card.
 *
 * @param {Object}  props                         Component properties
 * @param {string}  [props.id]                    Unique identifier for the card
 * @param {string}  [props.className]             Additional CSS classes
 * @param {string}  props.title                   Card title
 * @param {*}       props.description             Card description content
 * @param {*}       props.children                Card content
 * @param {boolean} [props.contentContainer=true] Whether to wrap content in a container
 * @return {JSX.Element} The settings card component
 */
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

	const titleId = id ? `${ id }-title` : undefined;
	const descriptionId = id ? `${ id }-description` : undefined;

	return (
		<div { ...cardProps } role="region" aria-labelledby={ titleId }>
			<div className="ppcp-r-settings-card__header">
				<div className="ppcp-r-settings-card__content-inner">
					<h2 id={ titleId } className="ppcp-r-settings-card__title">
						{ title }
					</h2>
					<div
						id={ descriptionId }
						className="ppcp-r-settings-card__description"
					>
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
