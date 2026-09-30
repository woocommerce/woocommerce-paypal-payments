import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './Components/App';

const container = document.getElementById( 'ppcp-settings-container' );

// createRoot throws on a missing container, so a stray enqueue would take the
// whole admin page's JS down with it.
if ( container ) {
	createRoot( container ).render( <App /> );
}
