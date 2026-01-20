import { SVG, Path } from '@wordpress/primitives';

export { default as PPIcon } from './GenericIcon';
export { default as OpenSignup } from './OpenSignup';
export { default as LogoPayPal } from './LogoPayPal';

/**
 * Success checkmark icon
 */
export const SuccessIcon = (
	<SVG
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
		fill="#1ed15a"
		aria-hidden="true"
		focusable="false"
	>
		<Path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z" />
	</SVG>
);

/**
 * Error icon for error notifications
 */
export const ErrorIcon = (
	<SVG
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
		fill="#d63638"
		aria-hidden="true"
		focusable="false"
	>
		<Path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
	</SVG>
);

