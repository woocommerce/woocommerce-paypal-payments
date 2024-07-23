import * as $ from 'jquery';
import DomElement from '../Components/DomElement';
import FormFieldGroup from '../Components/FormFieldGroup';

global.$ = global.jQuery = $;

test( 'get dom element selector', () => {
	const element = new DomElement( { selector: '.foo' } );

	expect( element.selector ).toBe( '.foo' );
} );

test( 'form field group activate', () => {
	const formFieldGroup = new FormFieldGroup( {} );

	expect( formFieldGroup.active ).toBe( false );

	formFieldGroup.activate();
	expect( formFieldGroup.active ).toBe( true );
} );
