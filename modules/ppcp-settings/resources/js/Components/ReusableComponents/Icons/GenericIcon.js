import React from 'react';

const GenericIcon = ( { imageName, className = '', alt = '' } ) => {
	const pathToImages = global.ppcpSettings.assets.imagesUrl;

	return (
		<img
			className={ className }
			alt={ alt }
			src={ `${ pathToImages }${ imageName }` }
		/>
	);
};

export default GenericIcon;
