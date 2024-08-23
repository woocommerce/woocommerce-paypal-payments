import BaseHandler from './BaseHandler';

class PreviewHandler extends BaseHandler {
	transactionInfo() {
		// We need to return something as ApplePay button initialization expects valid data.
		return {
			countryCode: 'US',
			currencyCode: 'USD',
			totalPrice: '10.00',
			totalPriceStatus: 'FINAL',
		};
	}

	createOrder() {
		throw new Error( 'Create order fail. This is just a preview.' );
	}

	approveOrder() {
		throw new Error( 'Approve order fail. This is just a preview.' );
	}

	actionHandler() {
		throw new Error( 'Action handler fail. This is just a preview.' );
	}

	errorHandler() {
		throw new Error( 'Error handler fail. This is just a preview.' );
	}
}

export default PreviewHandler;
