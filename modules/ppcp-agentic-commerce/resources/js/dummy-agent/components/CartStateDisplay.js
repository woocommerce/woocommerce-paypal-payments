/**
 * Cart State Display Component
 *
 * Shows current cart state with items, totals, validation issues, and approval link.
 */



export default function CartStateDisplay({ cart, cartId, ecToken, approvalUrl, coupons, onResolve, onUpdateItems, onSelectShipping, loading, loadingMessage }) {

	// Show loading spinner while creating/updating cart
	if (loadingMessage) {
		return (
			<div className="ppcp-cart-state">
				<h3>Cart State</h3>
				<div className="ppcp-cart-loading">
					<span className="spinner is-active"></span>
					<p>{loadingMessage}</p>
				</div>
			</div>
		);
	}

	// Show empty state when no cart exists and not loading
	if (!cart && !cartId) {
		return (
			<div className="ppcp-cart-state">
				<h3>Cart State</h3>
				<p className="ppcp-no-cart">No cart created yet. Search for a product and create a cart.</p>
			</div>
		);
	}

	const formatPrice = (price) => {
		if (typeof price === 'string') return price;
		if (price?.value) return price.value;
		return '0.00';
	};

	const formatCurrency = (amount, currency = 'USD') => {
		return `${amount} ${currency}`;
	};


	const formatAddress = (addr) => {
		if (!addr) return null;
		const parts = [];
		if (addr.address_line_1) parts.push(addr.address_line_1);
		if (addr.address_line_2) parts.push(addr.address_line_2);
		const stateZip = [addr.admin_area_1, addr.postal_code].filter(Boolean).join(' ');
		const cityStateZip = [addr.admin_area_2, stateZip].filter(Boolean).join(', ');
		if (cityStateZip) parts.push(cityStateZip);
		if (addr.country_code) parts.push(addr.country_code);
		return parts.join(', ');
	};
	// Merge client-requested codes with server-confirmed applied_coupons.
	// Each entry is { code, applied, issue } where applied comes from the
	// server response and issue from validation_issues (matched via context.coupon_code).
	const couponRows = (() => {
		const codes = new Set(coupons || []);
		(cart?.applied_coupons || []).forEach(c => codes.add(c.code));
		if (codes.size === 0) return [];

		const appliedMap = {};
		(cart?.applied_coupons || []).forEach(c => { appliedMap[c.code] = c; });

		const issueMap = {};
		(cart?.validation_issues || []).forEach(issue => {
			if (issue.context?.coupon_code) {
				issueMap[issue.context.coupon_code] = issue;
			}
		});

		return [...codes].map(code => ({
			code,
			applied: appliedMap[code] || null,
			issue: issueMap[code] || null,
		}));
	})();

	return (
		<div className="ppcp-cart-state">
			<h3>Cart State</h3>

			{/* Cart Info */}
			<table className="wc_status_table widefat">
				<tbody>
					<tr>
						<th>Cart ID</th>
						<td><code>{cartId}</code></td>
					</tr>
					{ecToken && (
						<tr>
							<th>EC Token</th>
							<td><code>{ecToken}</code></td>
						</tr>
					)}
					{cart?.status && (
						<tr>
							<th>Status</th>
							<td><span className={`ppcp-status-badge status-${cart.status.toLowerCase()}`}>{cart.status}</span></td>
						</tr>
					)}
					{cart?.validation_status && (
						<tr>
							<th>Validation</th>
							<td><span className={`ppcp-status-badge validation-${cart.validation_status.toLowerCase()}`}>{cart.validation_status}</span></td>
						</tr>
					)}
					{cart?.customer?.email_address && (
						<tr>
							<th>Customer</th>
							<td>{cart.customer.email_address}</td>
						</tr>
					)}
					{cart?.shipping_address && (
						<tr>
							<th>Shipping</th>
							<td>{formatAddress(cart.shipping_address)}</td>
						</tr>
					)}
					{cart?.billing_address && (
						<tr>
							<th>Billing</th>
							<td>{formatAddress(cart.billing_address)}</td>
						</tr>
					)}
				</tbody>
			</table>

			{/* Cart Items */}
			{cart?.items && cart.items.length > 0 && (
				<table className="wc_status_table widefat">
					<thead>
						<tr>
							<th>Product</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{cart.items.map((item, index) => (
							<tr key={index}>
								<td>
									<strong>{item.name}</strong>
									<div className="item-details">
										<span>Price: {formatCurrency(formatPrice(item.price), item.price?.currency_code)}</span>
										<span>Subtotal: {formatCurrency((parseFloat(formatPrice(item.price)) * item.quantity).toFixed(2), item.price?.currency_code)}</span>
									</div>
								</td>
								<td className="ppcp-controls-col">
									<div className="ppcp-item-controls">
										<span className="quantity-label">Qty:</span>
										<div className="ppcp-item-qty-controls">
											<button className="button button-small" onClick={() => onUpdateItems && onUpdateItems( cart.items.map( ( it, i ) => i === index ? { ...it, quantity: it.quantity - 1 } : it ).filter( it => it.quantity > 0 ) )} disabled={loading}>−</button>
											<span className="ppcp-item-qty">{item.quantity}</span>
											<button className="button button-small" onClick={() => onUpdateItems && onUpdateItems( cart.items.map( ( it, i ) => i === index ? { ...it, quantity: it.quantity + 1 } : it ) )} disabled={loading}>+</button>
											<button className="button button-small button-link-delete" onClick={() => onUpdateItems && onUpdateItems( cart.items.filter( ( _, i ) => i !== index ) )} disabled={loading}>×</button>
										</div>
									</div>
								</td>
							</tr>
						))}
					</tbody>
				</table>
			)}

			{/* Coupons — valid (with discount) and invalid (with reason from validation issue) */}
			{couponRows.length > 0 && (
				<table className="wc_status_table widefat">
					<thead>
						<tr>
							<th>Coupon</th>
							<th>Status</th>
							<th>Details</th>
						</tr>
					</thead>
					<tbody>
						{couponRows.map(({ code, applied, issue }) => (
							<tr key={code}>
								<td><code>{code}</code></td>
								<td>
									{applied
										? <span className="ppcp-status-badge validation-valid">Valid</span>
										: <span className="ppcp-status-badge validation-invalid">Invalid</span>
									}
								</td>
								<td>
									{applied
										? <span className="ppcp-price">{formatCurrency(formatPrice(applied.discount_amount), applied.discount_amount?.currency_code)}</span>
										: (issue?.user_message || '—')
									}
								</td>
							</tr>
						))}
					</tbody>
				</table>
			)}

			{/* Available Shipping Options */}
			{cart?.available_shipping_options && cart.available_shipping_options.length > 0 && (
				<table className="wc_status_table widefat">
					<thead>
						<tr>
							<th colSpan="2">Shipping Method</th>
						</tr>
					</thead>
					<tbody>
						{cart.available_shipping_options.map((option) => (
							<tr key={option.id} style={option.is_selected ? { background: '#f0f7ff' } : {}}>
								<td>
									<button
										className={`button ${option.is_selected ? 'button-primary' : 'button-secondary'}`}
										onClick={() => onSelectShipping && onSelectShipping(option.id)}
										disabled={loading || option.is_selected}
									>
										{option.is_selected ? '✓ ' : ''}{option.name}
									</button>
								</td>
								<td className="ppcp-price">{formatCurrency(option.price?.value || '0.00', option.price?.currency_code)}</td>
							</tr>
						))}
					</tbody>
				</table>
			)}

			{/* Cart Totals */}
			{cart?.totals && (
				<table className="wc_status_table widefat">
					<tbody>
						<tr>
							<th>Subtotal</th>
							<td>{formatCurrency(formatPrice(cart.totals.item_total), cart.totals.currency_code)}</td>
						</tr>
						{cart.totals.discount && (
							<tr>
								<th>Discount</th>
								<td className="ppcp-price ppcp-discount">−{formatCurrency(formatPrice(cart.totals.discount), cart.totals.currency_code)}</td>
							</tr>
						)}
						<tr>
							<th>Shipping</th>
							<td>{formatCurrency(formatPrice(cart.totals.shipping), cart.totals.currency_code)}</td>
						</tr>
						<tr>
							<th>Tax</th>
							<td>{formatCurrency(formatPrice(cart.totals.tax_total), cart.totals.currency_code)}</td>
						</tr>
						<tr>
							<th><strong>Total</strong></th>
							<td><strong>{formatCurrency(formatPrice(cart.totals.amount), cart.totals.currency_code)}</strong></td>
						</tr>
					</tbody>
				</table>
			)}

			{/* Validation Issues */}
			{cart?.validation_issues && cart.validation_issues.length > 0 && (
				<div className="ppcp-validation-issues">
					{cart.validation_issues.map((issue, index) => (
						<div key={index} className="ppcp-validation-issue">
							<div className="issue-header">
								<span className="issue-code">{issue.code}</span>
								{issue.field && /^items\[(\d+)\]/.test(issue.field) && cart.items?.[parseInt(issue.field.match(/^items\[(\d+)\]/)[1], 10)]?.name && (
									<span className="affected-item">Item: {cart.items[parseInt(issue.field.match(/^items\[(\d+)\]/)[1], 10)].name}</span>
								)}
							</div>
							<p className="issue-description">{issue.user_message || issue.message}</p>

							{/* Resolution Options — direct action buttons; SUGGEST_ALTERNATIVE_COUPON
							     expands into a per-coupon picker instead of a single button */}
							{issue.resolution_options && issue.resolution_options.length > 0 && (
								<div className="resolution-options">
									{issue.resolution_options.map((option, optIndex) =>
										option.action === 'SUGGEST_ALTERNATIVE_COUPON' && option.metadata?.suggestions?.length > 0
											? (
												<div key={optIndex} className="ppcp-alternative-coupons">
													<span className="alternative-label">Or try:</span>
													{option.metadata.suggestions.map(code => (
														<button
															key={code}
															className="button button-small"
															onClick={() => onResolve && onResolve(issue, {
																...option,
																metadata: { ...option.metadata, coupon_code: issue.context?.coupon_code, selected_coupon: code },
															})}
															disabled={loading}
														>
															{code}
														</button>
													))}
												</div>
											)
											: (
												<button
													key={optIndex}
													className={`button ppcp-resolution-btn ${option.metadata?.priority === 'HIGH' ? 'button-primary' : 'button-secondary'}`}
													onClick={() => onResolve && onResolve(issue, option)}
													disabled={loading}
												>
													{option.label}
												</button>
											)
									)}
								</div>
							)}
						</div>
					))}
				</div>
			)}

			{/* Approval Link - hide when checkout is completed */}
		{approvalUrl && cart?.status !== 'COMPLETED' && (
				<div className="ppcp-approval-section">
					<h4>PayPal Approval</h4>
					<a
						href={approvalUrl}
						target="_blank"
						rel="noopener noreferrer"
						className="button button-primary"
					>
						Approve Payment in PayPal
					</a>
					<p className="description">
						Click to open PayPal and approve the payment. After approval, copy the PayerID and complete checkout.
					</p>
				</div>
			)}
		</div>
	);
}
