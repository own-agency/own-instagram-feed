/**
 * Network Admin → Instagram: the Meta app and every site (network mode).
 */
import { createRoot, useCallback } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

import AppSettings from './components/AppSettings';
import SitesTable from './components/SitesTable';
import { Notices, useReturnNotice } from './components/common';

function App() {
	const [ notice, setNotice ] = useReturnNotice();
	const onNotice = useCallback(
		( value ) => setNotice( value ),
		[ setNotice ]
	);

	return (
		<>
			<h1>{ __( 'Instagram', 'own-instagram-feed' ) }</h1>
			<p className="own-instagram-lead">
				{ __(
					'Eén Meta-app voor alle websites; elke website koppelt zijn eigen Instagram-account. Websites ververst elk uur zelf en bewaren de afbeeldingen lokaal.',
					'own-instagram-feed'
				) }
			</p>
			<Notices notice={ notice } onRemove={ () => setNotice( null ) } />
			<div className="own-instagram-stack">
				<AppSettings onNotice={ onNotice } />
				<SitesTable onNotice={ onNotice } />
			</div>
		</>
	);
}

domReady( () => {
	const root = document.getElementById( 'own-instagram-network' );
	if ( root ) {
		createRoot( root ).render( <App /> );
	}
} );
