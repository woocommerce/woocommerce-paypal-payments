import ApplePayPreviewButtonManager from './Preview/ApplePayPreviewButtonManager';

/**
 * Accessor that creates and returns a single PreviewButtonManager instance.
 */
const buttonManager = () => {
	if ( ! ApplePayPreviewButtonManager.instance ) {
		ApplePayPreviewButtonManager.instance =
			new ApplePayPreviewButtonManager();
	}

	return ApplePayPreviewButtonManager.instance;
};

// Initialize the preview button manager.
buttonManager();
