/**
 * Settings → Instagram. The site's own connection for everyone with the
 * capability; outside network mode developers also get the Meta-app tab.
 */
import { ExternalLink, Spinner, TabPanel } from '@wordpress/components';
import {
	createRoot,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

import { getSite } from './api';
import AppSettings from './components/AppSettings';
import ConnectionCard from './components/ConnectionCard';
import { Notices, errorText, useReturnNotice } from './components/common';

const boot = window.ownInstagram || {};

function Connection( { setNotice } ) {
	const [ status, setStatus ] = useState( null );

	useEffect( () => {
		getSite()
			.then( setStatus )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: errorText( error ) } )
			);
	}, [ setNotice ] );

	return status ? (
		<ConnectionCard
			status={ status }
			onChange={ setStatus }
			onNotice={ setNotice }
		/>
	) : (
		<Spinner />
	);
}

function App() {
	const [ notice, setNotice ] = useReturnNotice();
	const onNotice = useCallback(
		( value ) => setNotice( value ),
		[ setNotice ]
	);
	const showAppTab = boot.isDeveloper && ! boot.networkMode;

	return (
		<>
			<h1>{ __( 'Instagram', 'own-instagram-feed' ) }</h1>
			<p className="own-instagram-lead">
				{ __(
					'Koppel het Instagram-account van deze website om de nieuwste posts te tonen met het blok "Instagram-feed".',
					'own-instagram-feed'
				) }
			</p>
			<Notices notice={ notice } onRemove={ () => setNotice( null ) } />

			{ showAppTab ? (
				<TabPanel
					className="own-instagram-tabs"
					tabs={ [
						{
							name: 'connection',
							title: __( 'Koppeling', 'own-instagram-feed' ),
						},
						{
							name: 'app',
							title: __( 'Meta-app', 'own-instagram-feed' ),
						},
					] }
				>
					{ ( tab ) =>
						'app' === tab.name ? (
							<AppSettings onNotice={ onNotice } />
						) : (
							<Connection setNotice={ onNotice } />
						)
					}
				</TabPanel>
			) : (
				<Connection setNotice={ onNotice } />
			) }

			{ boot.isDeveloper && boot.networkMode && boot.networkUrl && (
				<p className="own-instagram-help">
					<ExternalLink href={ boot.networkUrl }>
						{ __(
							'Meta-app en alle websites (Netwerkbeheer)',
							'own-instagram-feed'
						) }
					</ExternalLink>
				</p>
			) }
		</>
	);
}

domReady( () => {
	const root = document.getElementById( 'own-instagram-site' );
	if ( root ) {
		createRoot( root ).render( <App /> );
	}
} );
