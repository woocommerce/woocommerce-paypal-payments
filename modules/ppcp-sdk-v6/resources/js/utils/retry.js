/**
 * Whether a failed request is worth sending again, and how long to wait first.
 *
 * @package
 */

/**
 * A 4xx answer is about the request itself, such as a firewall block (403) or
 * a missing endpoint (404), so sending it again likely fails the same way. A
 * timeout or a rate limit (408, 429), a server error and a failure without an
 * answer (the network, the script load, the SDK) can pass on a later attempt.
 *
 * @param {Error} error - The failure; postJson() sets its HTTP status.
 * @return {boolean} Whether a later attempt can succeed.
 */
export function isRetryable( error ) {
	const status = error?.status;
	if ( ! status || status < 400 || status >= 500 ) {
		return true;
	}

	return status === 408 || status === 429;
}

/**
 * @param {number} failures - Consecutive failures so far, at least 1.
 * @param {number} base     - The wait after the first failure.
 * @param {number} max      - The longest wait.
 * @return {number} The wait before the next attempt, doubled per failure, in
 *                  the unit of base and max.
 */
export function backoffDelay( failures, base, max ) {
	return Math.min( base * 2 ** ( failures - 1 ), max );
}
