/**
 * Instagram-feed block, editor side. A dynamic block: the PHP template renders
 * both the editor preview (ServerSideRender) and the site, so there is one
 * source of markup. The block saves no HTML (save returns null).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from '../../block/block.json';

const COUNTS = [ 3, 4, 6, 8 ];

function Edit( { attributes, setAttributes } ) {
	const { layout, count, rounded, showEmpty } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Feedinstellingen', 'own-instagram-feed' ) }
				>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Weergave', 'own-instagram-feed' ) }
						value={ layout }
						options={ [
							{
								label: __(
									'Kaarten (met naam en bijschrift)',
									'own-instagram-feed'
								),
								value: 'cards',
							},
							{
								label: __(
									"Alleen foto's",
									'own-instagram-feed'
								),
								value: 'grid',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Aantal posts', 'own-instagram-feed' ) }
						help={ __(
							'Hoeveel posts naast elkaar passen, hangt af van de breedte van het blok.',
							'own-instagram-feed'
						) }
						value={ String( count ) }
						options={ COUNTS.map( ( n ) => ( {
							label: String( n ),
							value: String( n ),
						} ) ) }
						onChange={ ( value ) =>
							setAttributes( {
								count: parseInt( value, 10 ) || 4,
							} )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Lege melding tonen op de website',
							'own-instagram-feed'
						) }
						help={ __(
							'Zonder posts toont de website "Binnenkort zie je hier onze nieuwste posts." in plaats van niets. Handig als er een titel of knop boven de feed staat.',
							'own-instagram-feed'
						) }
						checked={ showEmpty }
						onChange={ ( value ) =>
							setAttributes( { showEmpty: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Afgeronde hoeken', 'own-instagram-feed' ) }
						checked={ rounded }
						onChange={ ( value ) =>
							setAttributes( { rounded: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			{ /* The preview is real markup with real links: without this, a
			     click meant to select the block opens Instagram instead. */ }
			<div style={ { pointerEvents: 'none' } }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ { layout, count, rounded, showEmpty } }
					skipBlockSupportAttributes
				/>
			</div>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
