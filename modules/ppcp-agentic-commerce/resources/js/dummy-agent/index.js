/**
 * Dummy Agent Entry Point
 *
 * Phase A: Manual API testing UI
 * Phase B: Chat UI with Qwen 3 0.6B (to be added later)
 */

import { createRoot } from '@wordpress/element';
import ApiTestPanel from './components/ApiTestPanel';
import '../../css/dummy-agent.scss';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('ppcp-dummy-agent-root');

	if (container) {
		// Get configuration from localized script
		const config = window.ppcpDummyAgent || {
			agenticUrl: '/wp-json/wc/v3/agentic',
			productsUrl: '/wp-json/wc/v3/products',
			currency: 'USD',
			isSandbox: true
		};

		// Mount React app
		const root = createRoot(container);
		root.render(<ApiTestPanel config={config} />);
	}
});
