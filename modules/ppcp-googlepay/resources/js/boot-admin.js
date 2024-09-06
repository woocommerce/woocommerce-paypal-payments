import GooglePayPreviewButtonManager from './Preview/GooglePayPreviewButtonManager';

/**
 * Accessor that creates and returns a single PreviewButtonManager instance.
 */
const buttonManager = () => {
	if ( ! GooglePayPreviewButtonManager.instance ) {
		GooglePayPreviewButtonManager.instance =
			new GooglePayPreviewButtonManager();
	}

	return GooglePayPreviewButtonManager.instance;
};

// Initialize the preview button manager.
buttonManager();
