class FormFieldGroup {
	#stored;
	#data = {};
	#active = false;
	#baseSelector;
	#contentSelector;
	#fields = {};
	#template;

	constructor( config ) {
		this.#baseSelector = config.baseSelector;
		this.#contentSelector = config.contentSelector;
		this.#fields = config.fields || {};
		this.#template = config.template;
		this.#stored = new Map();

	}

	setData( data ) {
		this.#data = data;
		this.refresh();
	}

	dataValue( fieldKey ) {
		if ( ! fieldKey || ! this.#fields[ fieldKey ] ) {
			return '';
		}

		if ( typeof this.#fields[ fieldKey ].valueCallback === 'function' ) {
			return this.#fields[ fieldKey ].valueCallback( this.#data );
		}

		const path = this.#fields[ fieldKey ].valuePath;

		if ( ! path ) {
			return '';
		}

		const value = path
			.split( '.' )
			.reduce(
				( acc, key ) =>
					acc && acc[ key ] !== undefined ? acc[ key ] : undefined,
				this.#data
			);
		return value ? value : '';
	}

	/**
	 * Activate form group: Render a custom Fastlane UI to replace the WooCommerce form.
	 *
	 * Indicates: Ryan flow.
	 */
	activate() {
		this.#active = true;
		this.storeFormData();
		this.refresh();
	}

	/**
	 * Deactivate form group: Remove the custom Fastlane UI - either display the default
	 * WooCommerce checkout form or no form at all (when no email was provided yet).
	 *
	 * Indicates: Gary flow / no email provided / not using Fastlane.
	 */
	deactivate() {
		this.#active = false;
		this.restoreFormData();
		this.refresh();
	}

	toggle() {
		if ( this.#active ) {
			this.deactivate();
		} else {
			this.activate();
		}
	}

	refresh() {
		const content = document.querySelector( this.#contentSelector );

		if ( ! content ) {
			return;
		}

		content.innerHTML = '';

		if ( ! this.#active ) {
			this.hideField( this.#contentSelector );
		} else {
			this.showField( this.#contentSelector );
		}

		this.loopFields( ( { selector } ) => {
			if ( this.#active /* && ! field.showInput */ ) {
				this.hideField( selector );
			} else {
				this.showField( selector );
			}
		} );

		if ( typeof this.#template === 'function' ) {
			content.innerHTML = this.#template( {
				value: ( fieldKey ) => {
					return this.dataValue( fieldKey );
				},
				isEmpty: () => {
					let isEmpty = true;

					this.loopFields( ( field, fieldKey ) => {
						if ( this.dataValue( fieldKey ) ) {
							isEmpty = false;
							return false;
						}
					} );

					return isEmpty;
				},
			} );
		}
	}

	/**
	 * Invoke a callback on every field in the current group.
	 *
	 * @param {(field: object, key: string) => void} callback
	 */
	loopFields( callback ) {
		Object.keys( this.#fields ).forEach( ( key ) => {
			const field = this.#fields[ key ];
			const fieldSelector = `${ this.#baseSelector } ${ field.selector }`;

			callback(
				{
					inputSelector: field.inputName
						? `${ fieldSelector } [name="${ field.inputName }"]`
						: '',
					...field,
				},
				key
			);
		} );
	}

	/**
	 * Stores the current form data in an internal storage.
	 * This allows the original form to be restored later.
	 */
	storeFormData() {
		const storeValue = ( field, name ) => {
			if ( 'checkbox' === field.type || 'radio' === field.type ) {
				this.#stored.set( name, field.checked );
			} else {
				this.#stored.set( name, field.value );
			}
		};

		this.loopFields( ( { inputSelector }, fieldKey ) => {
			if ( inputSelector && ! this.#stored.has( fieldKey ) ) {
				const elInput = document.querySelector( inputSelector );

				if ( elInput ) {
					storeValue( elInput, fieldKey );
				}
			}
		} );
	}

	/**
	 * Restores the form data to its initial state before the form group was activated.
	 * This function iterates through the stored form fields and resets their values or states.
	 */
	restoreFormData() {
		const restoreValue = ( field, name ) => {
			if ( 'checkbox' === field.type || 'radio' === field.type ) {
				field.checked = this.#stored.get( name );
			} else {
				field.value = this.#stored.get( name );
			}
		};

		this.loopFields( ( { inputSelector }, fieldKey ) => {
			if ( inputSelector && this.#stored.has( fieldKey ) ) {
				const elInput = document.querySelector( inputSelector );

				if ( elInput ) {
					restoreValue( elInput, fieldKey );
				}

				this.#stored.delete( fieldKey );
			}
		} );
	}

	showField( selector ) {
		const field = document.querySelector(
			this.#baseSelector + ' ' + selector
		);
		if ( field ) {
			field.classList.remove( 'ppcp-axo-field-hidden' );
		}
	}

	hideField( selector ) {
		const field = document.querySelector(
			this.#baseSelector + ' ' + selector
		);
		if ( field ) {
			field.classList.add( 'ppcp-axo-field-hidden' );
		}
	}

	inputElement( name ) {
		const baseSelector = this.#fields[ name ].selector;

		const select = document.querySelector( baseSelector + ' select' );
		if ( select ) {
			return select;
		}

		const input = document.querySelector( baseSelector + ' input' );
		if ( input ) {
			return input;
		}

		return null;
	}

	inputValue( name ) {
		const el = this.inputElement( name );
		return el ? el.value : '';
	}

	toSubmitData( data ) {
		this.loopFields( ( field, fieldKey ) => {
			if ( ! field.valuePath || ! field.selector ) {
				return true;
			}

			const inputElement = this.inputElement( fieldKey );

			if ( ! inputElement ) {
				return true;
			}

			data[ inputElement.name ] = this.dataValue( fieldKey );
		} );
	}
}

export default FormFieldGroup;
