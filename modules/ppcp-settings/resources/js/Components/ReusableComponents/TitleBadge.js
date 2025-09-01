const TitleBadge = ( { text, type } ) => {
	const className = 'ppcp-r-title-badge ' + `ppcp-r-title-badge--${ type }`;
	return (
		<span
			className={ className }
			dangerouslySetInnerHTML={ {
				__html: text,
			} }
		></span>
	);
};

export const TITLE_BADGE_POSITIVE = 'positive';
export const TITLE_BADGE_NEGATIVE = 'negative';
export const TITLE_BADGE_INFO = 'info';
export default TitleBadge;
