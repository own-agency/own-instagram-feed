/**
 * Small shared pieces of both admin apps. Only stable @wordpress/components:
 * no __experimental* imports, so a WordPress update can't break the screens.
 */
import { Button, Flex, Modal, Notice } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { dateI18n, getSettings, humanTimeDiff } from '@wordpress/date';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export const formatDate = ( timestamp ) =>
	timestamp
		? dateI18n(
				'j F Y',
				timestamp * 1000,
				getSettings().timezone?.string || undefined
		  )
		: '';

export const ago = ( timestamp ) =>
	timestamp ? humanTimeDiff( timestamp * 1000 ) : '';

const BADGES = {
	connected: [ __( 'Gekoppeld', 'own-instagram-feed' ), 'is-success' ],
	error: [
		__( 'Laatste keer ophalen mislukt', 'own-instagram-feed' ),
		'is-warning',
	],
	reconnect: [ __( 'Opnieuw koppelen', 'own-instagram-feed' ), 'is-warning' ],
	removed: [
		__( 'Automatisch ontkoppeld', 'own-instagram-feed' ),
		'is-muted',
	],
	disconnected: [ __( 'Niet gekoppeld', 'own-instagram-feed' ), 'is-muted' ],
};

export function StatusBadge( { state } ) {
	const [ label, className ] = BADGES[ state ] || BADGES.disconnected;
	return (
		<span className={ `own-instagram-badge ${ className }` }>
			{ label }
		</span>
	);
}

/**
 * A button that asks first, in a native Modal.
 *
 * @param {Object}   root0
 * @param {string}   root0.label         Button text.
 * @param {string}   root0.title         Modal title.
 * @param {string}   root0.message       Modal text.
 * @param {string}   root0.confirmLabel  Confirm button text.
 * @param {Function} root0.onConfirm     Called after confirming.
 * @param {boolean}  root0.isBusy        Show the busy state.
 * @param {boolean}  root0.isDestructive Red buttons.
 * @param {string}   root0.size          Button size (optional, e.g. "small").
 */
export function ConfirmButton( {
	label,
	title,
	message,
	confirmLabel,
	onConfirm,
	isBusy,
	isDestructive,
	size,
} ) {
	const [ open, setOpen ] = useState( false );
	return (
		<>
			<Button
				variant="secondary"
				size={ size }
				isDestructive={ isDestructive }
				isBusy={ isBusy }
				disabled={ isBusy }
				onClick={ () => setOpen( true ) }
			>
				{ label }
			</Button>
			{ open && (
				<Modal
					title={ title }
					onRequestClose={ () => setOpen( false ) }
					size="small"
				>
					<p>{ message }</p>
					<Flex justify="flex-end">
						<Button
							variant="tertiary"
							onClick={ () => setOpen( false ) }
						>
							{ __( 'Annuleren', 'own-instagram-feed' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive={ isDestructive }
							onClick={ () => {
								setOpen( false );
								onConfirm();
							} }
						>
							{ confirmLabel }
						</Button>
					</Flex>
				</Modal>
			) }
		</>
	);
}

/**
 * Read-only value with a copy button.
 *
 * @param {Object} root0
 * @param {string} root0.label Label above the value (optional).
 * @param {string} root0.value The value to show and copy.
 * @param {string} root0.help  Help text below (optional).
 */
export function CopyField( { label, value, help } ) {
	const [ copied, setCopied ] = useState( false );
	const ref = useCopyToClipboard( value, () => {
		setCopied( true );
		setTimeout( () => setCopied( false ), 2000 );
	} );
	return (
		<div className="own-instagram-copy">
			{ label && (
				<div className="own-instagram-copy__label">{ label }</div>
			) }
			<Flex align="center" gap={ 2 }>
				<code className="own-instagram-copy__value">{ value }</code>
				<Button variant="secondary" size="small" ref={ ref }>
					{ copied
						? __( 'Gekopieerd', 'own-instagram-feed' )
						: __( 'Kopiëren', 'own-instagram-feed' ) }
				</Button>
			</Flex>
			{ help && <p className="own-instagram-help">{ help }</p> }
		</div>
	);
}

/**
 * The result of a login, which Instagram's round trip brings back in the URL
 * (?own_instagram=connected|error&own_instagram_error=<code>). Shown once, then
 * removed from the address bar so a reload doesn't repeat it.
 */
export function useReturnNotice() {
	const [ notice, setNotice ] = useState( null );
	useEffect( () => {
		const url = new URL( window.location.href );
		const result = url.searchParams.get( 'own_instagram' );
		if ( ! result ) {
			return;
		}
		if ( 'connected' === result ) {
			setNotice( {
				status: 'success',
				text: __(
					'Instagram is gekoppeld en de nieuwste posts zijn opgehaald.',
					'own-instagram-feed'
				),
			} );
		} else {
			// Only our own texts: the URL carries a code, never a message,
			// so a crafted link can't put words on this page. Meta's own
			// wording is shown with the site's status (connectError).
			const texts = {
				denied: __(
					'Er is geen toestemming gegeven op Instagram. Probeer het opnieuw.',
					'own-instagram-feed'
				),
				site: __(
					'De website bestaat niet meer.',
					'own-instagram-feed'
				),
				invite: __(
					'Deze koppellink is ingetrokken of vervangen. Maak een nieuwe link.',
					'own-instagram-feed'
				),
				exchange: __(
					'Instagram heeft de koppeling geweigerd. De reden staat bij de website hieronder.',
					'own-instagram-feed'
				),
			};
			setNotice( {
				status: 'error',
				text:
					texts[ url.searchParams.get( 'own_instagram_error' ) ] ||
					__(
						'Koppelen is mislukt. Probeer het opnieuw.',
						'own-instagram-feed'
					),
			} );
		}
		url.searchParams.delete( 'own_instagram' );
		url.searchParams.delete( 'own_instagram_error' );
		url.searchParams.delete( 'own_instagram_message' );
		window.history.replaceState( null, '', url.toString() );
	}, [] );
	return [ notice, setNotice ];
}

export function Notices( { notice, onRemove } ) {
	if ( ! notice ) {
		return null;
	}
	return (
		<Notice
			status={ notice.status }
			onRemove={ onRemove }
			className="own-instagram-notice"
		>
			{ notice.text }
		</Notice>
	);
}

/**
 * apiFetch errors carry a message; anything else gets a generic one.
 *
 * @param {Object} error Rejected apiFetch value.
 * @return {string} Message to show.
 */
export const errorText = ( error ) =>
	error?.message ||
	__( 'Er ging iets mis. Probeer het opnieuw.', 'own-instagram-feed' );
