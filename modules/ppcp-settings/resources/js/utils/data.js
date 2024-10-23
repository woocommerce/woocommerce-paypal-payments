const data = () => {
	return {
		...global.ppcpSettings,
		getImage( imageName, className = '' ) {
			const pathToImages = global.ppcpSettings.assets.imagesUrl;

			return (
				<img
					className={ className }
					alt=""
					src={ pathToImages + imageName }
				/>
			);
		},
	};
};

export default data;
