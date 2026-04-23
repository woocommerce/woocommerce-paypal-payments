import { log } from './Helper/Debug';

/**
 * Manages the state and UI of the email submit button.
 * Handles processing states, validation, and duplicate submission prevention.
 */
class ButtonStateManager {
	constructor( submitButtonSelector, spinnerSelector ) {
		this.submitButtonSelector = submitButtonSelector;
		this.spinnerSelector = spinnerSelector;

		this.state = {
			isProcessing: false,
			canSubmit: false,
			lastProcessedEmail: null,
		};
	}

	/**
	 * Get current button state (read-only).
	 */
	getState() {
		return { ...this.state };
	}

	/**
	 * Centralized button UI management based on current state.
	 */
	updateButtonUI() {
		const submitButton = document.querySelector(
			this.submitButtonSelector
		);
		if ( ! submitButton ) {
			log( 'Submit button not found, skipping UI update', 'warn' );
			return;
		}

		const spinner = document.querySelector( this.spinnerSelector );

		if ( this.state.isProcessing ) {
			// Processing state - disabled with spinner
			submitButton.setAttribute( 'disabled', 'disabled' );
			spinner?.classList.add( 'loader', 'ppcp-axo-overlay' );
			submitButton.classList.add( 'processing' );
			log( 'Button set to processing state' );
		} else if ( this.state.canSubmit ) {
			// Ready state - enabled
			submitButton.removeAttribute( 'disabled' );
			spinner?.classList.remove( 'loader', 'ppcp-axo-overlay' );
			submitButton.classList.remove( 'processing' );
			log( 'Button set to ready state' );
		} else {
			// Default/disabled state
			submitButton.setAttribute( 'disabled', 'disabled' );
			spinner?.classList.remove( 'loader', 'ppcp-axo-overlay' );
			submitButton.classList.remove( 'processing' );
			log( 'Button set to disabled state' );
		}
	}

	/**
	 * Set button to processing state (disabled with spinner).
	 */
	setProcessing() {
		this.state.isProcessing = true;
		this.state.canSubmit = false;
		this.updateButtonUI();
		log( 'Button state changed to: processing' );
	}

	/**
	 * Set button to ready state (enabled and clickable).
	 */
	setReady() {
		this.state.isProcessing = false;
		this.state.canSubmit = true;
		this.updateButtonUI();
		log( 'Button state changed to: ready' );
	}

	/**
	 * Set button to disabled state (disabled, no spinner).
	 */
	setDisabled() {
		this.state.isProcessing = false;
		this.state.canSubmit = false;
		this.updateButtonUI();
		log( 'Button state changed to: disabled' );
	}

	/**
	 * Check if we should process the email (prevents duplicate processing).
	 * @param {string}   email               - Email to check.
	 * @param {Function} validateEmailFormat - Email validation function.
	 * @return {boolean} True if email should be processed.
	 */
	shouldProcessEmail( email, validateEmailFormat ) {
		const shouldProcess =
			! this.state.isProcessing &&
			this.state.lastProcessedEmail !== email &&
			email &&
			validateEmailFormat( email );

		log(
			`shouldProcessEmail: ${ shouldProcess } (processing: ${ this.state.isProcessing }, lastEmail: ${ this.state.lastProcessedEmail }, currentEmail: ${ email })`
		);
		return shouldProcess;
	}

	/**
	 * Check if we should allow retry after failure/cancellation.
	 * @param {string}   email               - Email to check.
	 * @param {Function} validateEmailFormat - Email validation function.
	 * @return {boolean} True if retry should be allowed.
	 */
	shouldAllowRetry( email, validateEmailFormat ) {
		const shouldRetry =
			! this.state.isProcessing && email && validateEmailFormat( email );

		log(
			`shouldAllowRetry: ${ shouldRetry } (processing: ${ this.state.isProcessing }, email: ${ email })`
		);
		return shouldRetry;
	}

	/**
	 * Mark email as being processed to prevent duplicates.
	 * @param {string} email - Email being processed.
	 */
	markEmailAsProcessing( email ) {
		this.state.lastProcessedEmail = email;
		this.setProcessing();
		log( `Email marked as processing: ${ email }` );
	}

	/**
	 * Clear the last processed email (allows retry).
	 */
	clearLastProcessedEmail() {
		const previousEmail = this.state.lastProcessedEmail;
		this.state.lastProcessedEmail = null;
		log( `Cleared last processed email: ${ previousEmail }` );
	}

	/**
	 * Handle authentication failure or cancellation - allows retry.
	 */
	handleAuthFailureOrCancellation() {
		log( 'Handling auth failure/cancellation - allowing retry' );
		this.clearLastProcessedEmail();
		this.setReady();

		// Force UI update to ensure button is actually enabled.
		setTimeout( () => {
			this.updateButtonUI();
			log( 'Forced button UI update after cancellation' );
		}, 100 );
	}

	/**
	 * Handle email lookup failure - disables button.
	 */
	handleEmailLookupFailure() {
		log( 'Handling email lookup failure - disabling button' );
		this.clearLastProcessedEmail();
		this.setDisabled();
	}

	/**
	 * Handle successful processing - enables button for next action.
	 */
	handleSuccess() {
		log( 'Handling successful processing' );
		this.setReady();
	}

	/**
	 * Reset to initial state.
	 */
	reset() {
		this.state = {
			isProcessing: false,
			canSubmit: false,
			lastProcessedEmail: null,
		};
		this.updateButtonUI();
		log( 'Button state reset to initial state' );
	}

	/**
	 * Check if currently processing.
	 * @return {boolean} True if the button is currently in the processing state.
	 */
	isProcessing() {
		return this.state.isProcessing;
	}

	/**
	 * Check if button can be submitted.
	 * @return {boolean} True if the button can be submitted and is not in the processing state.
	 */
	canSubmit() {
		return this.state.canSubmit && ! this.state.isProcessing;
	}
}

export default ButtonStateManager;
