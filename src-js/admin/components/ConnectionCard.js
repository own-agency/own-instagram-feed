/**
 * The site's own connection: account, status, and connect / refresh /
 * disconnect / connect link. Used by Settings → Instagram.
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	Notice,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { currentUrl, siteAction } from '../api';
import {
	CopyField,
	ConfirmButton,
	StatusBadge,
	ago,
	errorText,
	formatDate,
} from './common';

function connectLabel( connected, state ) {
	if ( ! connected ) {
		return __( 'Instagram koppelen', 'own-instagram-feed' );
	}
	return 'reconnect' === state
		? __( 'Opnieuw koppelen', 'own-instagram-feed' )
		: __( 'Ander account koppelen', 'own-instagram-feed' );
}

export default function ConnectionCard( { status, onChange, onNotice } ) {
	const [ busy, setBusy ] = useState( '' );

	const run = async ( action ) => {
		setBusy( action );
		try {
			const result = await siteAction( action, currentUrl() );
			if ( 'connect' === action ) {
				window.location.assign( result.url );
				return;
			}
			onChange( result );
			onNotice( {
				status: 'success',
				text: {
					sync: __( 'De posts zijn ververst.', 'own-instagram-feed' ),
					disconnect: __(
						'Instagram is ontkoppeld. De posts en afbeeldingen zijn van de website verwijderd.',
						'own-instagram-feed'
					),
					invite: __(
						'De koppellink staat klaar. Stuur hem naar de beheerder van het Instagram-account.',
						'own-instagram-feed'
					),
				}[ action ],
			} );
		} catch ( error ) {
			onNotice( { status: 'error', text: errorText( error ) } );
		}
		setBusy( '' );
	};

	if ( ! status.appConfigured ) {
		return (
			<Card>
				<CardBody>
					<Notice status="info" isDismissible={ false }>
						{ __(
							'De Instagram-koppeling is voor deze website nog niet ingesteld. Zodra dat gebeurd is, kun je hier je account koppelen.',
							'own-instagram-feed'
						) }
					</Notice>
				</CardBody>
			</Card>
		);
	}

	const { connected, state } = status;
	const needsLogin = ! connected || 'reconnect' === state;

	return (
		<Card className="own-instagram-card">
			<CardHeader>
				<Flex align="center" justify="space-between" wrap>
					<strong className="own-instagram-account">
						{ connected
							? `@${ status.username }`
							: __(
									'Geen account gekoppeld',
									'own-instagram-feed'
							  ) }
					</strong>
					<StatusBadge state={ state } />
				</Flex>
			</CardHeader>
			<CardBody>
				{ status.connectError && (
					<Notice status="error" isDismissible={ false }>
						<strong>
							{ __(
								'De laatste koppelpoging is mislukt.',
								'own-instagram-feed'
							) }
						</strong>{ ' ' }
						{ status.connectError }
					</Notice>
				) }
				{ 'reconnect' === state && (
					<Notice status="warning" isDismissible={ false }>
						<strong>
							{ __(
								'De koppeling werkt niet meer.',
								'own-instagram-feed'
							) }
						</strong>{ ' ' }
						{ status.error }{ ' ' }
						{ __(
							'De website toont voorlopig de laatst opgehaalde posts.',
							'own-instagram-feed'
						) }{ ' ' }
						{ status.removeAt > 0 &&
							sprintf(
								/* translators: %s: date */
								__(
									'Koppel opnieuw vóór %s, anders worden ze verwijderd.',
									'own-instagram-feed'
								),
								formatDate( status.removeAt )
							) }
					</Notice>
				) }
				{ 'error' === state && (
					<Notice status="warning" isDismissible={ false }>
						<strong>
							{ __(
								'De laatste keer ophalen is mislukt.',
								'own-instagram-feed'
							) }
						</strong>{ ' ' }
						{ status.error }{ ' ' }
						{ __(
							'De website probeert het elk uur opnieuw.',
							'own-instagram-feed'
						) }
					</Notice>
				) }
				{ 'removed' === state && status.removed && (
					<Notice status="info" isDismissible={ false }>
						{ sprintf(
							/* translators: 1: username, 2: date */
							__(
								'@%1$s was 30 dagen niet bereikbaar. De posts en afbeeldingen zijn op %2$s verwijderd. Koppel het account opnieuw om de feed terug te zetten.',
								'own-instagram-feed'
							),
							status.removed.username,
							formatDate( status.removed.at )
						) }
					</Notice>
				) }

				{ connected && (
					<p className="own-instagram-meta">
						{ sprintf(
							/* translators: %d: number of posts */
							_n(
								'%d post opgehaald',
								'%d posts opgehaald',
								status.postCount,
								'own-instagram-feed'
							),
							status.postCount
						) }
						{ status.syncedAt > 0 &&
							', ' +
								sprintf(
									/* translators: %s: relative time, e.g. "5 minuten geleden" */
									__( 'laatst %s', 'own-instagram-feed' ),
									ago( status.syncedAt )
								) }
						{ '. ' }
						{ __(
							'De website haalt elk uur zelf nieuwe posts op.',
							'own-instagram-feed'
						) }
					</p>
				) }

				<Flex justify="flex-start" gap={ 2 } wrap>
					<Button
						variant={ needsLogin ? 'primary' : 'secondary' }
						isBusy={ 'connect' === busy }
						disabled={ !! busy }
						onClick={ () => run( 'connect' ) }
					>
						{ connectLabel( connected, state ) }
					</Button>
					{ connected && 'reconnect' !== state && (
						<Button
							variant="secondary"
							isBusy={ 'sync' === busy }
							disabled={ !! busy }
							onClick={ () => run( 'sync' ) }
						>
							{ __( 'Nu verversen', 'own-instagram-feed' ) }
						</Button>
					) }
					{ connected && (
						<ConfirmButton
							label={ __( 'Ontkoppelen', 'own-instagram-feed' ) }
							title={ __(
								'Instagram ontkoppelen?',
								'own-instagram-feed'
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
							isBusy={ 'disconnect' === busy }
							onConfirm={ () => run( 'disconnect' ) }
						/>
					) }
				</Flex>
				<p className="own-instagram-help">
					{ __(
						'Je logt in bij Instagram met het account dat je op de website wilt tonen. Alleen zakelijke en creator-accounts kunnen gekoppeld worden.',
						'own-instagram-feed'
					) }
				</p>
			</CardBody>

			{ needsLogin && (
				<CardBody className="own-instagram-invite">
					<h3>
						{ __(
							'Beheert iemand anders het account?',
							'own-instagram-feed'
						) }
					</h3>
					{ status.invite ? (
						<CopyField
							value={ status.invite.url }
							help={ sprintf(
								/* translators: %s: date */
								__(
									'Stuur deze link naar de beheerder van het Instagram-account. Hij werkt één keer en is geldig tot %s.',
									'own-instagram-feed'
								),
								formatDate( status.invite.expires )
							) }
						/>
					) : (
						<p className="own-instagram-help">
							{ __(
								'Maak een link waarmee de beheerder van het account zelf inlogt. Je hoeft geen wachtwoorden te delen.',
								'own-instagram-feed'
							) }
						</p>
					) }
					<Button
						variant="secondary"
						isBusy={ 'invite' === busy }
						disabled={ !! busy }
						onClick={ () => run( 'invite' ) }
					>
						{ status.invite
							? __( 'Nieuwe link maken', 'own-instagram-feed' )
							: __( 'Koppellink maken', 'own-instagram-feed' ) }
					</Button>
				</CardBody>
			) }
		</Card>
	);
}
