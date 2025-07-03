const TitleExtra = ( { children } ) => {
	if ( ! children ) {
		return null;
	}

	return <span className="ppcp--title-extra">{ children }</span>;
};

export default TitleExtra;
