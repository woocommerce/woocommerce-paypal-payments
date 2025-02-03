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

// If `children` is not empty, it's output and wrapped in spaces.
const BadgeContent = ( { children } ) => {
	if ( ! children ) {
		return null;
	}
	return <> { children } </>;
};

const BadgeBox = ( {
	title,
	textBadge,
	imageBadge = [],
	titleType = BADGE_BOX_TITLE_BIG,
	description = '',
} ) => {
	let titleSize = BADGE_BOX_TITLE_SMALL;
	if ( BADGE_BOX_TITLE_BIG === titleType ) {
		titleSize = BADGE_BOX_TITLE_BIG;
	}

	const titleTextClassName =
		'ppcp-r-badge-box__title-text ' +
		`ppcp-r-badge-box__title-text--${ titleSize }`;

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
			<div className="ppcp-r-badge-box__description">
				{ description && (
					<p
						className="ppcp-r-badge-box__description"
						dangerouslySetInnerHTML={ {
							__html: description,
						} }
					></p>
				) }
			</div>
		</div>
	);
};

export const BADGE_BOX_TITLE_BIG = 'big';
export const BADGE_BOX_TITLE_SMALL = 'small';
export default BadgeBox;
