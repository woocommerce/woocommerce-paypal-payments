// Block Elements
export const Title = ( { children, className = '' } ) => (
	<span className={ `ppcp-r-settings-block__title ${ className }`.trim() }>
		{ children }
	</span>
);
export const TitleWrapper = ( { children } ) => (
	<span className="ppcp-r-settings-block__title-wrapper">{ children }</span>
);

export const SupplementaryLabel = ( { children } ) => (
	<span className="ppcp-r-settings-block__supplementary-title-label">
		{ children }
	</span>
);

export const Description = ( { children, className = '' } ) => (
	<span
		className={ `ppcp-r-settings-block__description ${ className }`.trim() }
	>
		{ children }
	</span>
);

export const Action = ( { children } ) => (
	<div className="ppcp-r-settings-block__action">{ children }</div>
);

export const Header = ( { children, className = '' } ) => (
	<div className={ `ppcp-r-settings-block__header ${ className }`.trim() }>
		{ children }
	</div>
);

// Card Elements
export const Content = ( { children, id = '' } ) => (
	<div id={ id } className="ppcp-r-settings-card__content">
		{ children }
	</div>
);

export const ContentWrapper = ( { children } ) => (
	<div className="ppcp-r-settings-card__content-wrapper">{ children }</div>
);
