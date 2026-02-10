/**
 * Request Log Component
 *
 * Shows API request/response history for debugging.
 */

import { useState } from '@wordpress/element';

export default function RequestLog({ logs, onClearLogs }) {
	const [expandedIndex, setExpandedIndex] = useState(null);

	if (logs.length === 0) {
		return (
			<div className="ppcp-request-log">
				<h3>Request/Response Log</h3>
				<p className="ppcp-no-logs">No requests yet. Start by searching for products.</p>
			</div>
		);
	}

	const toggleExpand = (index) => {
		setExpandedIndex(expandedIndex === index ? null : index);
	};

	const formatTime = (date) => {
		return date.toLocaleTimeString();
	};

	const formatJson = (obj) => {
		return JSON.stringify(obj, null, 2);
	};

	return (
		<div className="ppcp-request-log">
			<h3>
				Request/Response Log ({logs.length})
				{onClearLogs && (
					<button
						className="button button-secondary button-small"
						onClick={onClearLogs}
					>
						Clear Logs
					</button>
				)}
			</h3>

			<div className="ppcp-log-entries">
				{logs.map((log, index) => (
					<div
						key={index}
						className={`ppcp-log-entry ${log.isError ? 'error' : ''} ${expandedIndex === index ? 'expanded' : ''}`}
					>
						<div
							className="log-header"
							onClick={() => toggleExpand(index)}
						>
							<span className={`log-method log-method-${log.method.toLowerCase()}`}>{log.method}</span>
							<span className="log-endpoint">{log.path}</span>
							<span className="log-timestamp">{formatTime(log.time)}</span>
							{expandedIndex === index ? '▼' : '▶'}
						</div>

						{expandedIndex === index && (
							<div className="log-details">
								{log.request && (
									<>
										<strong>Request</strong>
										<pre>{formatJson(log.request)}</pre>
									</>
								)}

								<strong>Response</strong>
								<pre className={log.isError ? 'response-error' : 'response-success'}>
									{formatJson(log.response)}
								</pre>
							</div>
						)}
					</div>
				))}
			</div>
		</div>
	);
}
