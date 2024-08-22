import BaseHandler from './BaseHandler';

class PreviewHandler extends BaseHandler {
	constructor( buttonConfig, ppcpConfig, externalHandler ) {
		super( buttonConfig, ppcpConfig, externalHandler );
	}

	transactionInfo() {
		throw new Error( 'Transaction info fail. This is just a preview.' );
	}

	createOrder() {
		throw new Error( 'Create order fail. This is just a preview.' );
	}

	approveOrder( data, actions ) {
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
