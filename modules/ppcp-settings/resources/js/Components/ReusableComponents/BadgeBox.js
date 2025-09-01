import { LearnMore } from './Elements';
import { PPIcon } from './Icons';

const ImageBadge = ( { images } ) => {
	if ( ! images || ! images.length ) {
		return null;
	}

	return (
		<BadgeContent>
			<span className="ppcp-r-badge-box__title-image-badge">
				{ images.map( ( badge, index ) => (
					<PPIcon
						key={ `badge-${ index }` }
						imageName={ badge }
						className="ppcp-r-badge-box__image"
					/>
				) ) }
			</span>
		</BadgeContent>
	);
};

// If `children` is not empty, the `children` prop is output and wrapped in spaces.
const BadgeContent = ( { children } ) => {
	if ( ! children ) {
		return null;
	}
	return <> { children } </>;
};

const BadgeDescription = ( { description, learnMoreLink } ) => {
	if ( ! description && ! learnMoreLink ) {
		return null;
	}

	return (
		<div className="ppcp-r-badge-box__description">
			<p className="ppcp-r-badge-box__description">
				{ description }
				<LearnMore url={ learnMoreLink } />
			</p>
		</div>
	);
};

const BadgeBox = ( {
	title,
	textBadge,
	imageBadge = [],
	description = '',
	learnMoreLink = '',
} ) => {
	const titleTextClassName = 'ppcp-r-badge-box__title-text';
	const titleBaseClassName = 'ppcp-r-badge-box__title';
	const titleClassName = imageBadge.length
		? `${ titleBaseClassName } ppcp-r-badge-box__title--has-image-badge`
		: titleBaseClassName;

	return (
		<div className="ppcp-r-badge-box">
			<span className={ titleClassName }>
				<span className={ titleTextClassName }>{ title }</span>

				<ImageBadge images={ imageBadge } />
				<BadgeContent>{ textBadge }</BadgeContent>
			</span>

			<BadgeDescription
				description={ description }
				learnMoreLink={ learnMoreLink }
			/>
		</div>
	);
};

export default BadgeBox;
