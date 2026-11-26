/**
 * The Meta app (Instagram app ID + secret) and the relay. Developers only.
 * The secret is write-only: the API only says whether it is set and its last
 * four characters.
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	ExternalLink,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { getApp, saveApp } from '../api';
import { CopyField, errorText } from './common';

function secretHelp( app ) {
	if ( app.secretSet ) {
		return sprintf(
			/* translators: %s: last four characters */
			__(
				'Opgeslagen, eindigt op …%s. Laat leeg om te behouden; vul alleen in om te vervangen.',
				'own-instagram-feed'
			),
			app.secretHint
		);
	}
	return app.constants.secret
		? __( 'Staat in wp-config.php.', 'own-instagram-feed' )
		: __(
				'Nog niet ingevuld. Klik in de Meta-app op "Show" naast Instagram app secret.',
				'own-instagram-feed'
		  );
}

export default function AppSettings( { onNotice } ) {
	const [ app, setApp ] = useState( null );
	const [ form, setForm ] = useState( {
		id: '',
		secret: '',
		relay: '',
		clearSecret: false,
	} );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		getApp()
			.then( ( data ) => {
				setApp( data );
				setForm( {
					id: data.id,
					secret: '',
					relay: data.relay,
					clearSecret: false,
				} );
			} )
			.catch( ( error ) =>
				onNotice( { status: 'error', text: errorText( error ) } )
			);
	}, [ onNotice ] );

	if ( ! app ) {
		return <Spinner />;
	}

	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );

	const save = async () => {
		setSaving( true );
		try {
			const data = await saveApp( form );
			setApp( data );
			setForm( {
				id: data.id,
				secret: '',
				relay: data.relay,
				clearSecret: false,
			} );
			onNotice( {
				status: 'success',
				text: __(
					'De instellingen zijn opgeslagen.',
					'own-instagram-feed'
				),
			} );
		} catch ( error ) {
			onNotice( { status: 'error', text: errorText( error ) } );
		}
		setSaving( false );
	};

	const fromConfig = __( 'Staat in wp-config.php.', 'own-instagram-feed' );

	return (
		<Card className="own-instagram-card">
			<CardHeader>
				<strong>{ __( 'Meta-app', 'own-instagram-feed' ) }</strong>
			</CardHeader>
			<CardBody>
				{ ! app.configured && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Nog niet ingesteld: vul het Instagram app ID en het Instagram app secret in.',
							'own-instagram-feed'
						) }
					</Notice>
				) }

				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Instagram app ID', 'own-instagram-feed' ) }
					help={
						app.constants.id && ! form.id
							? fromConfig
							: __(
									'Onder Instagram → "API setup with Instagram login" in de Meta-app. Niet het app-ID bovenaan het dashboard.',
									'own-instagram-feed'
							  )
					}
					value={ form.id }
					onChange={ set( 'id' ) }
					inputMode="numeric"
					autoComplete="off"
				/>

				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="password"
					label={ __( 'Instagram app secret', 'own-instagram-feed' ) }
					help={ secretHelp( app ) }
					value={ form.secret }
					onChange={ set( 'secret' ) }
					autoComplete="new-password"
				/>
				{ app.secretSet && (
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __(
							'Opgeslagen secret wissen',
							'own-instagram-feed'
						) }
						checked={ form.clearSecret }
						onChange={ set( 'clearSecret' ) }
					/>
				) }

				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="url"
					label={ __( 'Relay-URL', 'own-instagram-feed' ) }
					help={
						app.constants.relay && ! form.relay
							? fromConfig
							: __(
									'De Cloudflare Worker van OWN. Met een relay hoeft deze website niet in de Meta-app te staan. Leeg = de eigen callback hieronder.',
									'own-instagram-feed'
							  )
					}
					value={ form.relay }
					onChange={ set( 'relay' ) }
					placeholder="https://own-instagram-relay.….workers.dev/"
				/>

				<CopyField
					label={ __( 'OAuth redirect URI', 'own-instagram-feed' ) }
					value={ app.redirectUri }
					help={
						app.usesRelay
							? __(
									'De relay. Staat die al in de Meta-app, dan hoeft er voor deze website niets meer bij.',
									'own-instagram-feed'
							  )
							: __(
									'Moet letterlijk in de Meta-app staan, bij Business login settings → OAuth redirect URIs.',
									'own-instagram-feed'
							  )
					}
				/>

				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving }
					onClick={ save }
				>
					{ __( 'Opslaan', 'own-instagram-feed' ) }
				</Button>
			</CardBody>
			<CardBody className="own-instagram-checklist">
				<h3>{ __( 'Checklist Meta-app', 'own-instagram-feed' ) }</h3>
				<ul>
					<li>
						{ __(
							'App van het type Business, met het product Instagram ("API setup with Instagram login").',
							'own-instagram-feed'
						) }
					</li>
					<li>
						{ __(
							'Live, met advanced access voor instagram_business_basic. In ontwikkelmodus werken alleen accounts met de rol Instagram Tester.',
							'own-instagram-feed'
						) }
					</li>
					<li>
						{ __(
							'Deauthorize en data deletion URL wijzen naar de relay (…/?deauthorize en …/?data-deletion).',
							'own-instagram-feed'
						) }
					</li>
					<li>
						{ __(
							"Rond Meta's jaarlijkse data access renewal af, anders stoppen alle feeds tegelijk.",
							'own-instagram-feed'
						) }
					</li>
				</ul>
				<ExternalLink href="https://developers.facebook.com/apps/">
					{ __( 'Naar de Meta-app', 'own-instagram-feed' ) }
				</ExternalLink>
			</CardBody>
		</Card>
	);
}
