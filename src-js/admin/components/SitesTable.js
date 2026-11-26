/**
 * Every site of the network with its status and actions (network mode).
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	Spinner,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { currentUrl, getSites, sitesAction } from '../api';
import {
	ConfirmButton,
	CopyField,
	StatusBadge,
	ago,
	errorText,
	formatDate,
} from './common';

export default function SitesTable( { onNotice } ) {
	const [ sites, setSites ] = useState( null );
	const [ busy, setBusy ] = useState( '' );

	const load = useCallback( () => {
		getSites()
			.then( setSites )
			.catch( ( error ) =>
				onNotice( { status: 'error', text: errorText( error ) } )
			);
	}, [ onNotice ] );

	useEffect( load, [ load ] );

	const run = async ( site, action ) => {
		setBusy( `${ site.blogId }:${ action }` );
		try {
			const result = await sitesAction(
				site.blogId,
				action,
				currentUrl()
			);
			if ( 'connect' === action ) {
				window.location.assign( result.url );
				return;
			}
			setSites( ( list ) =>
				list.map( ( item ) =>
					item.blogId === site.blogId ? result : item
				)
			);
		} catch ( error ) {
			onNotice( {
				status: 'error',
				text: `${ site.siteName }: ${ errorText( error ) }`,
			} );
		}
		setBusy( '' );
	};

	if ( ! sites ) {
		return <Spinner />;
	}

	return (
		<Card className="own-instagram-card">
			<CardHeader>
				<strong>{ __( 'Websites', 'own-instagram-feed' ) }</strong>
			</CardHeader>
			<CardBody className="own-instagram-sites">
				<table className="own-instagram-table">
					<thead>
						<tr>
							<th scope="col">
								{ __( 'Website', 'own-instagram-feed' ) }
							</th>
							<th scope="col">
								{ __( 'Account', 'own-instagram-feed' ) }
							</th>
							<th scope="col">
								{ __( 'Acties', 'own-instagram-feed' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ sites.map( ( site ) => {
							const isBusy = ( action ) =>
								busy === `${ site.blogId }:${ action }`;
							const needsLogin =
								! site.connected || 'reconnect' === site.state;
							return (
								<tr key={ site.blogId }>
									<th scope="row">
										<a href={ site.adminUrl }>
											{ site.siteName || site.siteUrl }
										</a>
										<span className="own-instagram-sub">
											{ site.siteUrl
												.replace( /^https?:\/\//, '' )
												.replace( /\/$/, '' ) }
										</span>
									</th>
									<td>
										<StatusBadge state={ site.state } />
										{ site.connected && (
											<strong className="own-instagram-account">
												@{ site.username }
											</strong>
										) }
										{ site.connected && (
											<span className="own-instagram-sub">
												{ sprintf(
													/* translators: 1: number of posts, 2: relative time, 3: date */
													__(
														'%1$d posts, ververst %2$s. Token geldig tot %3$s.',
														'own-instagram-feed'
													),
													site.postCount,
													ago( site.syncedAt ) || '–',
													formatDate(
														site.expiresAt
													) || '?'
												) }
											</span>
										) }
										{ site.error && (
											<span className="own-instagram-sub is-error">
												{ site.error }
											</span>
										) }
										{ 'reconnect' === site.state &&
											site.removeAt > 0 && (
												<span className="own-instagram-sub is-error">
													{ sprintf(
														/* translators: %s: date */
														__(
															'Zonder nieuwe koppeling verwijderd op %s.',
															'own-instagram-feed'
														),
														formatDate(
															site.removeAt
														)
													) }
												</span>
											) }
										{ needsLogin && site.invite && (
											<CopyField
												value={ site.invite.url }
											/>
										) }
									</td>
									<td>
										<Flex
											justify="flex-start"
											gap={ 1 }
											wrap
										>
											{ needsLogin && (
												<>
													<Button
														variant="secondary"
														size="small"
														isBusy={ isBusy(
															'invite'
														) }
														disabled={ !! busy }
														onClick={ () =>
															run(
																site,
																'invite'
															)
														}
													>
														{ site.invite
															? __(
																	'Nieuwe koppellink',
																	'own-instagram-feed'
															  )
															: __(
																	'Koppellink maken',
																	'own-instagram-feed'
															  ) }
													</Button>
													<Button
														variant="secondary"
														size="small"
														isBusy={ isBusy(
															'connect'
														) }
														disabled={ !! busy }
														onClick={ () =>
															run(
																site,
																'connect'
															)
														}
													>
														{ __(
															'Zelf koppelen',
															'own-instagram-feed'
														) }
													</Button>
												</>
											) }
											{ site.connected &&
												'reconnect' !== site.state && (
													<Button
														variant="secondary"
														size="small"
														isBusy={ isBusy(
															'sync'
														) }
														disabled={ !! busy }
														onClick={ () =>
															run( site, 'sync' )
														}
													>
														{ __(
															'Nu verversen',
															'own-instagram-feed'
														) }
													</Button>
												) }
											{ site.connected && (
												<ConfirmButton
													label={ __(
														'Ontkoppelen',
														'own-instagram-feed'
													) }
													title={ sprintf(
														/* translators: %s: site name */
														__(
															'Instagram ontkoppelen van %s?',
															'own-instagram-feed'
														),
														site.siteName
													) }
													message={ __(
														'De feed verdwijnt van de website en de opgeslagen posts en afbeeldingen worden verwijderd.',
														'own-instagram-feed'
													) }
													confirmLabel={ __(
														'Ontkoppelen',
														'own-instagram-feed'
													) }
													isDestructive
													size="small"
													isBusy={ isBusy(
														'disconnect'
													) }
													onConfirm={ () =>
														run(
															site,
															'disconnect'
														)
													}
												/>
											) }
										</Flex>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</CardBody>
		</Card>
	);
}
