/**
 * Phase A: Manual API Testing Panel
 *
 * Simple UI with buttons to test the agentic API directly.
 * No LLM involvement - just manual testing of API endpoints.
 */

import { useState, useEffect } from '@wordpress/element';
import AgenticClient from '../services/agenticClient';
import RequestLog from './RequestLog';
import CartStateDisplay from './CartStateDisplay';
import ValidationTestPanel from './ValidationTestPanel';

const STORAGE_KEY = 'ppcp_test_agent_logs';

export default function ApiTestPanel({ config }) {
	const [client] = useState(() => new AgenticClient(config.agenticUrl, config.productsUrl));
	const [cart, setCart] = useState(null);
	const [products, setProducts] = useState([]);
	const [logs, setLogs] = useState(() => {
		// Load logs from localStorage on mount
		try {
			const stored = localStorage.getItem(STORAGE_KEY);
			if (stored) {
				const parsed = JSON.parse(stored);
				// Convert time strings back to Date objects
				return parsed.map(log => ({
					...log,
					time: new Date(log.time)
				}));
			}
		} catch (e) {
		}
		return [];
	});
	const [searchQuery, setSearchQuery] = useState('');
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [selectedItems, setSelectedItems] = useState([]); // Items to add to cart
	const [productQuantities, setProductQuantities] = useState({}); // Qty picker per search result
	const [payerId, setPayerId] = useState(''); // PayerID from PayPal approval
	const [cartIdInput, setCartIdInput] = useState('');
	const [loadingMessage, setLoadingMessage] = useState(null);
	const [couponInput, setCouponInput] = useState('');
	const [coupons, setCoupons] = useState([]);

	// Persist logs to localStorage whenever they change
	useEffect(() => {
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify(logs));
		} catch (e) {
		}
	}, [logs]);

	/**
	 * Log an API request/response.
	 */
	const log = (method, path, request, response, isError = false) => {
		setLogs(prev => [...prev, {
			method,
			path,
			request,
			response,
			isError,
			time: new Date()
		}]);
	};

	/**
	 * Sync cart and applied-coupon state from an API response.
	 */
	const applyResponse = (result) => {
		setCart(result);
	};

	/**
	 * Search for products.
	 */
	const handleSearch = async () => {
		if (!searchQuery.trim()) {
			setError('Please enter a search query');
			return;
		}

		setLoading(true);
		setError(null);

		try {
			const result = await client.searchProducts(searchQuery);
			setProducts(result);
			log('GET', `/products?search=${searchQuery}`, null, result);
		} catch (e) {
			setError(e.message);
			log('GET', `/products?search=${searchQuery}`, null, { error: e.message }, true);
		}

		setLoading(false);
	};

	/**
	 * Add product to selected items (before cart creation).
	 */
	const handleAddToSelection = (product) => {
		// Get the quantity from state, defaulting to 1 if not set
		const qty = productQuantities[product.id] || 1;

		const existingItem = selectedItems.find(item => item.variant_id === String(product.id));

		if (existingItem) {
			setSelectedItems(selectedItems.map(item =>
				item.variant_id === String(product.id)
					? { ...item, quantity: item.quantity + qty }
					: item
			));
		} else {
			setSelectedItems([...selectedItems, {
				variant_id: String(product.id),
				quantity: qty,
				name: product.name,
				price: {
					currency_code: config.currency,
					value: product.price
				}
			}]);
		}

		// Reset picker back to 1 after adding.
		setProductQuantities(prev => ({ ...prev, [product.id]: 1 }));
	};

	/**
	 * Remove item from selection.
	 */
	const handleRemoveFromSelection = (variantId) => {
		setSelectedItems(selectedItems.filter(item => item.variant_id !== variantId));
	};

	/**
	 * Update quantity in selection.
	 */
	const handleUpdateQuantity = (variantId, quantity) => {
		if (quantity < 1) {
			handleRemoveFromSelection(variantId);
			return;
		}
		setSelectedItems(selectedItems.map(item =>
			item.variant_id === variantId
				? { ...item, quantity: parseInt(quantity, 10) }
				: item
		));
	};

	/**
	 * Create cart with selected items.
	 */
	const handleCreateCart = async () => {
		if (selectedItems.length === 0) {
			setError('Add at least one product to create a cart');
			return;
		}

		setLoadingMessage('Creating cart...');
		setLoading(true);
		setError(null);

		const request = {
			items: selectedItems,
			customer: {
				email_address: 'test@example.com'
			},
			payment_method: {
				type: 'paypal'
			}
		};

		try {
			const result = await client.createCart(request);
			applyResponse(result);
			setCartIdInput(client.cartId || '');
			log('POST', '/merchant-cart', request, result);
		} catch (e) {
			setError(e.message);
			log('POST', '/merchant-cart', request, { error: e.message }, true);
		}

		setLoading(false);
		setLoadingMessage(null);
	};

	/**
	 * Add more items to existing cart.
	 */
	const handleAddItemsToCart = async () => {
		if (!cart) {
			setError('Create a cart first');
			return;
		}

		if (selectedItems.length === 0) {
			setError('Select items to add');
			return;
		}

		setLoadingMessage('Updating cart...');
		setLoading(true);
		setError(null);

		// Merge existing cart items with new selected items (copy each item to avoid mutating state)
		const mergedItems = cart.items.map( item => ( { ...item } ) );

		selectedItems.forEach(newItem => {
			const existingItem = mergedItems.find(item => item.variant_id === newItem.variant_id);
			if (existingItem) {
				existingItem.quantity = existingItem.quantity + newItem.quantity;
			} else {
				mergedItems.push(newItem);
			}
		});

		const request = {
			items: mergedItems,
			customer: cart.customer,
			shipping_address: cart.shipping_address,
			billing_address: cart.billing_address,
			payment_method: { type: 'paypal' }
		};

		const shipping = getSelectedShipping();
		if ( shipping ) {
			request.selected_shipping_option = shipping;
		}

		if ( coupons.length > 0 ) {
			request.coupons = coupons.map( code => ( { code, action: 'APPLY' } ) );
		}

		try {
			const result = await client.updateCart(request);
			applyResponse(result);
			setSelectedItems([]); // Clear selection after adding
			log('PUT', `/merchant-cart/${client.cartId}`, request, result);
		} catch (e) {
			setError(e.message);
			log('PUT', `/merchant-cart/${client.cartId}`, request, { error: e.message }, true);
		}

		setLoading(false);
		setLoadingMessage(null);
	};

	/**
	 * Get current cart.
	 */
	const handleGetCart = async () => {
		const id = cartIdInput.trim();
		if (!id) {
			setError('Enter a Cart ID');
			return;
		}

		client.cartId = id;
		setLoadingMessage('Loading cart...');
		setLoading(true);
		setError(null);

		try {
			const result = await client.getCart();
			applyResponse(result);
			log('GET', `/merchant-cart/${client.cartId}`, null, result);
		} catch (e) {
			setError(e.message);
			log('GET', `/merchant-cart/${client.cartId}`, null, { error: e.message }, true);
		}

		setLoading(false);
		setLoadingMessage(null);
	};

	/**
	 * Update cart with shipping address.
	 */
	const handleUpdateCart = async () => {
		if (!cart) {
			setError('Create a cart first');
			return;
		}

		setLoadingMessage('Updating cart...');
		setLoading(true);
		setError(null);

		const request = {
			items: cart.items,
			customer: cart.customer,
			shipping_address: {
				address_line_1: '123 Main Street',
				admin_area_2: 'San Jose',
				admin_area_1: 'CA',
				postal_code: '95131',
				country_code: 'US'
			},
			payment_method: { type: 'paypal' }
		};

		const shipping = getSelectedShipping();
		if ( shipping ) {
			request.selected_shipping_option = shipping;
		}

		if ( coupons.length > 0 ) {
			request.coupons = coupons.map( code => ( { code, action: 'APPLY' } ) );
		}

		try {
			const result = await client.updateCart(request);
			applyResponse(result);
			log('PUT', `/merchant-cart/${client.cartId}`, request, result);
		} catch (e) {
			setError(e.message);
			log('PUT', `/merchant-cart/${client.cartId}`, request, { error: e.message }, true);
		}

		setLoading(false);
		setLoadingMessage(null);
	};

	/**
	 * Complete checkout.
	 */
	const handleCheckout = async () => {
		if (!cart) {
			setError('Create a cart first');
			return;
		}

		if (!client.ecToken) {
			setError('No PayPal token available. Cart may not have been created properly.');
			return;
		}

		if (!payerId || !payerId.trim()) {
			setError('Please enter the PayerID from the PayPal approval URL');
			return;
		}

		setLoadingMessage('Completing checkout...');
		setLoading(true);
		setError(null);

		const request = {
			items: cart.items,
			customer: cart.customer,
			payment_method: {
				type: 'paypal',
				token: client.ecToken,
				payer_id: payerId.trim()
			}
		};

		if ( cart.shipping_address ) {
			request.shipping_address = cart.shipping_address;
		}

		if ( cart.billing_address ) {
			request.billing_address = cart.billing_address;
		}

		const shipping = getSelectedShipping();
		if ( shipping ) {
			request.selected_shipping_option = shipping;
		}

		if ( coupons.length > 0 ) {
			request.coupons = coupons.map( code => ( { code, action: 'APPLY' } ) );
		}

		try {
			const result = await client.checkout(request);
			applyResponse(result);
			setPayerId(''); // Clear payer ID after successful checkout
			log('POST', `/merchant-cart/${client.cartId}/checkout`, request, result);
		} catch (e) {
			setError(e.message);
			log('POST', `/merchant-cart/${client.cartId}/checkout`, request, { error: e.message }, true);
		}

		setLoading(false);
		setLoadingMessage(null);
	};

	/**
	 * Create cart from validation test scenario.
	 */
	const handleCreateTestCart = async (scenario) => {
		setLoadingMessage('Creating cart...');
		setLoading(true);
		setError(null);

		const request = {
			items: scenario.items,
			customer: {
				email_address: 'test@example.com'
			},
			payment_method: {
				type: 'paypal'
			}
		};

		// Add shipping address if provided in scenario
		if (scenario.shipping_address) {
			request.shipping_address = scenario.shipping_address;
		}

		// Set or reset coupons based on scenario
		if (scenario.coupons) {
			request.coupons = scenario.coupons;
			setCoupons(scenario.coupons.map(c => c.code));
		} else {
			setCoupons([]);
		}

		try {
			const result = await client.createCart(request);
			applyResponse(result);
			setCartIdInput(client.cartId || '');
			log('POST', '/merchant-cart', request, result);
		} catch (e) {
			setError(e.message);
			log('POST', '/merchant-cart', request, { error: e.message }, true);
		}

		setLoading(false);
		setLoadingMessage(null);
	};

	/**
	 * Apply a resolution option to the current cart and PUT the updated state.
	 */
	const handleResolve = async (issue, option) => {
		if (!cart) return;

		setLoadingMessage('Updating cart...');
		setLoading(true);
		setError(null);

		let updatedItems = [ ...(cart.items || []) ];
		let updatedAddress = cart.shipping_address ? { ...cart.shipping_address } : undefined;
		let updatedCoupons = [ ...coupons ];

		// Parse item index from fields like "items[0]" or "items[1].price.currency_code"
		const itemIndexMatch = issue.field?.match( /^items\[(\d+)\]/ );
		const itemIndex = itemIndexMatch ? parseInt( itemIndexMatch[1], 10 ) : null;

		switch ( option.action ) {
			case 'REMOVE_ITEM':
			case 'WAIT_FOR_RESTOCK': {
				const idx = itemIndex ?? option.metadata?.item_index;
				if ( idx !== null && idx !== undefined ) {
					updatedItems = updatedItems.filter( ( _, i ) => i !== idx );
				}
				break;
			}
			case 'MODIFY_CART': {
				if ( option.metadata?.max_quantity ) {
					if ( itemIndex !== null && updatedItems[itemIndex] ) {
						updatedItems[itemIndex] = { ...updatedItems[itemIndex], quantity: option.metadata.max_quantity };
					} else {
						// No specific item index — clamp all items to max_quantity
						updatedItems = updatedItems.map( ( item ) => ( {
							...item,
							quantity: Math.min( item.quantity, option.metadata.max_quantity ),
						} ) );
					}
				}
				break;
			}
			case 'USE_DIFFERENT_CURRENCY': {
				const targetCurrency = option.metadata?.target_currency || option.metadata?.expected_currency;
				if ( targetCurrency ) {
					if ( itemIndex !== null && updatedItems[itemIndex] ) {
						updatedItems[itemIndex] = {
							...updatedItems[itemIndex],
							price: { ...updatedItems[itemIndex].price, currency_code: targetCurrency },
						};
					} else {
						updatedItems = updatedItems.map( ( item ) => ( {
							...item,
							price: { ...item.price, currency_code: targetCurrency },
						} ) );
					}
				}
				break;
			}
			case 'PROVIDE_MISSING_FIELD': {
				const defaults = {
					address_line_1: '123 Main St',
					admin_area_2: 'San Jose',
					admin_area_1: 'CA',
					postal_code: '95131',
					country_code: 'US',
				};
				const missingField = option.metadata?.field;
				if ( missingField && defaults[missingField] ) {
					updatedAddress = { ...( updatedAddress || {} ), [missingField]: defaults[missingField] };
				}
				break;
			}
			case 'UPDATE_ADDRESS': {
				updatedAddress = {
					address_line_1: '123 Main St',
					admin_area_2: 'San Jose',
					admin_area_1: 'CA',
					postal_code: '95131',
					country_code: 'US',
				};
				break;
			}
			case 'SUGGEST_ALTERNATIVE': {
				// Remove the invalid item — the only actionable resolution in a test UI.
				const altIdx = itemIndex ?? option.metadata?.item_index;
				if ( altIdx !== null && altIdx !== undefined ) {
					updatedItems = updatedItems.filter( ( _, i ) => i !== altIdx );
				}
				break;
			}
			case 'REMOVE_COUPON':
			case 'CONTINUE_WITHOUT_COUPON': {
				const couponCode = option.metadata?.coupon_code;
				if ( couponCode ) {
					updatedCoupons = updatedCoupons.filter( c => c !== couponCode );
				} else {
					updatedCoupons = [];
				}
				break;
			}
			case 'SUGGEST_ALTERNATIVE_COUPON': {
				const failedCode = option.metadata?.coupon_code;
				const selectedAlt = option.metadata?.selected_coupon;
				if ( failedCode ) {
					updatedCoupons = updatedCoupons.filter( c => c !== failedCode );
				}
				if ( selectedAlt && ! updatedCoupons.includes( selectedAlt ) ) {
					updatedCoupons.push( selectedAlt );
				}
				break;
			}
			case 'ACCEPT_NEW_PRICE':
			case 'UPDATE_SHIPPING_METHOD':
				break;
			default:
				break;
		}

		// SUGGEST_ALTERNATIVE on the last item: keep the cart alive so the user
		// can search for and add a replacement without starting over.
		if ( option.action === 'SUGGEST_ALTERNATIVE' && updatedItems.length === 0 ) {
			setCart( { ...cart, items: [], validation_issues: [] } );
			setLoading( false );
			setLoadingMessage( null );
			return;
		}

		// UPDATE_SHIPPING_METHOD with an explicit rate ID: delegate to the
		// shipping-selection handler so the rate is actually applied and totals
		// update from the server response.
		if ( option.action === 'UPDATE_SHIPPING_METHOD' && option.metadata?.shipping_rate_id ) {
			handleSelectShipping( option.metadata.shipping_rate_id );
			return;
		}

		// Acknowledgment-type resolutions — the cart payload does not change;
		// remove the issue locally so the agent can proceed.
		if ( option.action === 'ACCEPT_NEW_PRICE' || option.action === 'UPDATE_SHIPPING_METHOD' ) {
			const remaining = ( cart.validation_issues || [] ).filter( ( vi ) => vi !== issue );
			setCart( { ...cart, validation_issues: remaining, validation_status: remaining.length === 0 ? 'VALID' : 'INVALID' } );
			setLoading( false );
			setLoadingMessage( null );
			return;
		}

		const request = {
			items: updatedItems,
			customer: cart.customer || { email_address: 'test@example.com' },
			payment_method: { type: 'paypal' },
		};

		if ( updatedAddress ) {
			request.shipping_address = updatedAddress;
		}

		const shipping = getSelectedShipping();
		if ( shipping ) {
			request.selected_shipping_option = shipping;
		}

		if ( updatedCoupons.length > 0 ) {
			request.coupons = updatedCoupons.map( code => ( { code, action: 'APPLY' } ) );
		}

		try {
			const result = await client.updateCart( request );

			// If all items removed and API returns empty/null, gracefully reset
			if (updatedItems.length === 0 || !result || !result.items || result.items.length === 0) {
				setCart(null);
				setCoupons([]);
				client.reset();
			} else {
				applyResponse( result );
				setCoupons( updatedCoupons );
			}

			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, result );
		} catch ( e ) {
			setError( e.message );
			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, { error: e.message }, true );
		}

		setLoading( false );
		setLoadingMessage(null);
	};

	/**
	 * Update cart items directly (quantity change or removal).
	 */
	const handleUpdateItems = async ( updatedItems ) => {
		if ( !cart ) return;

		setLoadingMessage('Updating cart...');
		setLoading( true );
		setError( null );

		const request = {
			items: updatedItems,
			customer: cart.customer || { email_address: 'test@example.com' },
			payment_method: { type: 'paypal' },
		};

		if ( cart.shipping_address ) {
			request.shipping_address = cart.shipping_address;
		}

		const shipping = getSelectedShipping();
		if ( shipping ) {
			request.selected_shipping_option = shipping;
		}

		if ( coupons.length > 0 ) {
			request.coupons = coupons.map( code => ( { code, action: 'APPLY' } ) );
		}

		try {
			const result = await client.updateCart( request );

			// If all items removed and API returns empty/null, gracefully reset
			if (updatedItems.length === 0 || !result || !result.items || result.items.length === 0) {
				setCart(null);
				setCoupons([]);
				client.reset();
			} else {
				applyResponse( result );
			}

			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, result );
		} catch ( e ) {
			setError( e.message );
			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, { error: e.message }, true );
		}

		setLoading( false );
		setLoadingMessage(null);
	};

	/**
	 * Extract the currently-selected shipping option ID from the cart response.
	 */
	const getSelectedShipping = () => cart?.available_shipping_options?.find( o => o.is_selected )?.id || null;

	/**
	 * Select a shipping method — triggers PUT with the chosen rate ID.
	 */
	const handleSelectShipping = async ( optionId ) => {
		if ( !cart ) return;

		setLoadingMessage( 'Selecting shipping method...' );
		setLoading( true );
		setError( null );

		const request = {
			items: cart.items,
			customer: cart.customer || { email_address: 'test@example.com' },
			payment_method: { type: 'paypal' },
			selected_shipping_option: optionId,
		};

		if ( cart.shipping_address ) {
			request.shipping_address = cart.shipping_address;
		}
		if ( cart.billing_address ) {
			request.billing_address = cart.billing_address;
		}
		if ( coupons.length > 0 ) {
			request.coupons = coupons.map( code => ( { code, action: 'APPLY' } ) );
		}

		try {
			const result = await client.updateCart( request );
			applyResponse( result );
			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, result );
		} catch ( e ) {
			setError( e.message );
			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, { error: e.message }, true );
		}

		setLoading( false );
		setLoadingMessage( null );
	};

	/**
	 * Apply a coupon code to the cart.
	 */
	const handleApplyCoupon = async () => {
		const code = couponInput.trim().toUpperCase();
		if ( !code || coupons.includes( code ) ) return;

		const updatedCoupons = [ ...coupons, code ];
		setCoupons( updatedCoupons );
		setCouponInput( '' );

		if ( !cart ) return;

		setLoading( true );
		setLoadingMessage( 'Applying coupon...' );
		setError( null );

		const request = {
			items: cart.items,
			customer: cart.customer || { email_address: 'test@example.com' },
			payment_method: { type: 'paypal' },
			coupons: updatedCoupons.map( c => ( { code: c, action: 'APPLY' } ) ),
		};

		if ( cart.shipping_address ) request.shipping_address = cart.shipping_address;
		if ( cart.billing_address ) request.billing_address = cart.billing_address;
		const selectedShipping = getSelectedShipping();
		if ( selectedShipping ) request.selected_shipping_option = selectedShipping;

		try {
			const result = await client.updateCart( request );
			applyResponse( result );
			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, result );
		} catch ( e ) {
			setError( e.message );
			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, { error: e.message }, true );
		}

		setLoading( false );
		setLoadingMessage( null );
	};

	/**
	 * Remove a coupon code from the cart.
	 */
	const handleRemoveCoupon = async ( code ) => {
		const updatedCoupons = coupons.filter( c => c !== code );
		setCoupons( updatedCoupons );

		if ( !cart ) return;

		setLoading( true );
		setLoadingMessage( 'Removing coupon...' );
		setError( null );

		const request = {
			items: cart.items,
			customer: cart.customer || { email_address: 'test@example.com' },
			payment_method: { type: 'paypal' },
			coupons: updatedCoupons.map( c => ( { code: c, action: 'APPLY' } ) ),
		};

		if ( cart.shipping_address ) request.shipping_address = cart.shipping_address;
		if ( cart.billing_address ) request.billing_address = cart.billing_address;
		const selectedShipping = getSelectedShipping();
		if ( selectedShipping ) request.selected_shipping_option = selectedShipping;

		try {
			const result = await client.updateCart( request );
			applyResponse( result );
			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, result );
		} catch ( e ) {
			setError( e.message );
			log( 'PUT', `/merchant-cart/${ client.cartId }`, request, { error: e.message }, true );
		}

		setLoading( false );
		setLoadingMessage( null );
	};

	/**
	 * Clear logs only.
	 */
	const handleClearLogs = () => {
		setLogs([]);
		localStorage.removeItem(STORAGE_KEY);
	};

	/**
	 * Reset everything.
	 */
	const handleReset = () => {
		client.reset();
		setCart(null);
		setProducts([]);
		setLogs([]);
		setSearchQuery('');
		setError(null);
		setSelectedItems([]);
		setCartIdInput('');
		setCoupons([]);
		setCouponInput('');
		localStorage.removeItem(STORAGE_KEY);
	};

	return (
		<div className="ppcp-api-test-panel">
			{error && (
				<div className="ppcp-error">
					<p>{error}</p>
				</div>
			)}

			{/* Validation Test Scenarios */}
			<ValidationTestPanel
				onCreateTestCart={handleCreateTestCart}
				loading={loading}
				pluginScenarios={config.testScenarios || []}
			/>

			<div className="ppcp-test-agent-wrapper">
				{/* Left Column: Controls */}
				<div className="ppcp-test-actions">
					<h3>Controls</h3>

					{/* Product Search */}
					<div className="ppcp-search-section">
						<h3>Product Search</h3>
						<input
							type="text"
							value={searchQuery}
							onChange={(e) => setSearchQuery(e.target.value)}
							placeholder="Search products..."
							onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
							disabled={loading}
						/>
						<button
							className="button button-secondary"
							onClick={handleSearch}
							disabled={loading || !searchQuery.trim()}
						>
							Search
						</button>
					</div>

					{/* Product Results */}
					{products.length > 0 && (
						<div className="ppcp-product-results">
							<table className="wc_status_table widefat">
								<thead>
									<tr>
										<th>Product</th>
									<th>Price</th>
									<th>Qty</th>
									<th></th>
									</tr>
								</thead>
								<tbody>
									{products.slice(0, 5).map(product => (
										<tr key={product.id}>
											<td>{product.name}</td>
											<td className="ppcp-price">${product.price}</td>
											<td>
												<input
													type="number"
													min="1"
													value={productQuantities[product.id] || 1}
													onChange={(e) => {
														const val = Math.max(1, parseInt(e.target.value, 10) || 1);
														setProductQuantities(prev => ({ ...prev, [product.id]: val }));
													}}
													disabled={loading}
												/>
											</td>
											<td>
												<button
													className="button button-small"
													onClick={() => handleAddToSelection(product)}
													disabled={loading}
												>
													Add
												</button>
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</div>
					)}

					{/* Selected Items (before cart creation) */}
					{selectedItems.length > 0 && !cart && (
						<div className="ppcp-selected-items-section">
							<table className="wc_status_table widefat">
								<thead>
									<tr>
										<th>Product</th>
									<th>Price</th>
									<th>Qty</th>
									<th></th>
									</tr>
								</thead>
								<tbody>
									{selectedItems.map(item => (
										<tr key={item.variant_id}>
											<td>{item.name}</td>
											<td className="ppcp-price">${item.price.value}</td>
											<td>
												<div className="ppcp-item-controls">
													<span className="quantity-label">Qty:</span>
													<button className="button button-small" onClick={() => handleUpdateQuantity(item.variant_id, item.quantity - 1)} disabled={loading || item.quantity <= 1}>−</button>
													<span className="ppcp-item-qty">{item.quantity}</span>
													<button className="button button-small" onClick={() => handleUpdateQuantity(item.variant_id, item.quantity + 1)} disabled={loading}>+</button>
												</div>
											</td>
											<td>
												<button className="button button-small button-link-delete" onClick={() => handleRemoveFromSelection(item.variant_id)} disabled={loading}>×</button>
											</td>
										</tr>
									))}
								</tbody>
							</table>
							<div className="ppcp-create-cart-btn">
								<button
									className="button button-primary"
									onClick={handleCreateCart}
									disabled={loading}
								>
									Create Cart with {selectedItems.length} Item{selectedItems.length > 1 ? 's' : ''}
								</button>
							</div>
						</div>
					)}

					{/* Add Items to Existing Cart */}
					{selectedItems.length > 0 && cart && (
						<div className="ppcp-selected-items-section">
							<table className="wc_status_table widefat">
								<thead>
									<tr>
										<th>Product</th>
									<th>Price</th>
									<th>Qty</th>
									<th></th>
									</tr>
								</thead>
								<tbody>
									{selectedItems.map(item => (
										<tr key={item.variant_id}>
											<td>{item.name}</td>
											<td className="ppcp-price">${item.price.value}</td>
											<td>
												<div className="ppcp-item-controls">
													<span className="quantity-label">Qty:</span>
													<button className="button button-small" onClick={() => handleUpdateQuantity(item.variant_id, item.quantity - 1)} disabled={loading || item.quantity <= 1}>−</button>
													<span className="ppcp-item-qty">{item.quantity}</span>
													<button className="button button-small" onClick={() => handleUpdateQuantity(item.variant_id, item.quantity + 1)} disabled={loading}>+</button>
												</div>
											</td>
											<td>
												<button className="button button-small button-link-delete" onClick={() => handleRemoveFromSelection(item.variant_id)} disabled={loading}>×</button>
											</td>
										</tr>
									))}
								</tbody>
							</table>
							<div className="ppcp-create-cart-btn">
								<button
									className="button button-primary"
									onClick={handleAddItemsToCart}
									disabled={loading}
								>
									Add to Existing Cart
								</button>
							</div>
						</div>
					)}

					{/* Cart Actions */}
					<div className="ppcp-cart-actions">
						<h4>Cart Actions</h4>
						<div className="action-group">
							<label>Cart ID</label>
							<input
								type="text"
								value={cartIdInput}
								onChange={(e) => setCartIdInput(e.target.value)}
								placeholder="Paste a cart ID to load..."
								disabled={loading}
							/>
							<button
								className="button"
								onClick={handleGetCart}
								disabled={loading || !cartIdInput.trim()}
							>
								Get Cart
							</button>
							<button
								className="button"
								onClick={handleUpdateCart}
								disabled={loading || !cart}
							>
								Update Cart (Add Shipping)
							</button>
						</div>
					</div>

					{/* Coupons */}
					{cart && (
						<div className="ppcp-coupon-section">
							<h4>Coupons</h4>
							<div className="ppcp-coupon-input-row">
								<input
									type="text"
									value={couponInput}
									onChange={(e) => setCouponInput(e.target.value)}
									placeholder="Enter coupon code..."
									onKeyPress={(e) => e.key === 'Enter' && handleApplyCoupon()}
									disabled={loading}
								/>
								<button
									className="button button-secondary"
									onClick={handleApplyCoupon}
									disabled={loading || !couponInput.trim()}
								>
									Apply
								</button>
							</div>
							{coupons.length > 0 && (
								<div className="ppcp-coupon-tags">
									{coupons.map(code => (
										<span key={code} className="ppcp-coupon-tag">
											{code}
											<button className="ppcp-coupon-tag-remove" onClick={() => handleRemoveCoupon(code)} disabled={loading}>×</button>
										</span>
									))}
								</div>
							)}
						</div>
					)}

					{/* Checkout */}
					{cart && client.ecToken && (
						<div className="ppcp-checkout-section">
							<h4>Complete Checkout</h4>
							<div className="payer-id-input">
								<label>PayerID</label>
								<input
									type="text"
									value={payerId}
									onChange={(e) => setPayerId(e.target.value)}
									placeholder="PayerID (e.g., ABC123XYZ)"
									disabled={loading}
								/>
								<p className="description">
									After approving payment in PayPal, copy the PayerID from the redirect URL and paste it here.
								</p>
								<button
									className="button button-primary"
									onClick={handleCheckout}
									disabled={loading || !payerId.trim()}
								>
									Checkout
								</button>
							</div>
						</div>
					)}

					{/* Reset */}
					<div className="ppcp-reset-section">
						<button
							className="button button-secondary"
							onClick={handleReset}
							disabled={loading}
						>
							Reset
						</button>
					</div>
				</div>

				{/* Right Column: Cart State */}
				<div className="ppcp-test-state">
					<CartStateDisplay
						cart={cart}
						cartId={client.cartId}
						ecToken={client.ecToken}
						approvalUrl={client.getApprovalUrl()}
						coupons={coupons}
						onResolve={handleResolve}
						onUpdateItems={handleUpdateItems}
						onSelectShipping={handleSelectShipping}
						loading={loading}
						loadingMessage={loadingMessage}
					/>
				</div>
			</div>

			{/* Request/Response Log */}
			<RequestLog logs={logs} onClearLogs={handleClearLogs} />
		</div>
	);
}
