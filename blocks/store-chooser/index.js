import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'mds-commerce/store-chooser', {
	edit( { attributes, setAttributes } ) {
		const { releaseId, groupBy, layout, prioritizePromos, heading } =
			attributes;
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Store Chooser Settings', 'mds-commerce' ) }
					>
						<TextControl
							label={ __( 'Heading (optional)', 'mds-commerce' ) }
							value={ heading }
							onChange={ ( value ) =>
								setAttributes( { heading: value } )
							}
						/>
						<SelectControl
							label={ __( 'Group Offers By', 'mds-commerce' ) }
							value={ groupBy }
							options={ [
								{
									label: __(
										'Media Format (Vinyl, CD, Digital)',
										'mds-commerce'
									),
									value: 'format',
								},
								{
									label: __( 'Storefront', 'mds-commerce' ),
									value: 'store',
								},
								{
									label: __(
										'No Grouping (Flat)',
										'mds-commerce'
									),
									value: 'none',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { groupBy: value } )
							}
						/>
						<SelectControl
							label={ __( 'Layout Style', 'mds-commerce' ) }
							value={ layout }
							options={ [
								{
									label: __( 'Cards', 'mds-commerce' ),
									value: 'cards',
								},
								{
									label: __( 'Compact List', 'mds-commerce' ),
									value: 'list',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { layout: value } )
							}
						/>
						<ToggleControl
							label={ __(
								'Prioritize Stores with Coupons / Special Offers',
								'mds-commerce'
							) }
							checked={ !! prioritizePromos }
							onChange={ ( value ) =>
								setAttributes( { prioritizePromos: value } )
							}
						/>
						<TextControl
							label={ __(
								'Release ID (0 = Current Release)',
								'mds-commerce'
							) }
							type="number"
							value={ String( releaseId || 0 ) }
							onChange={ ( value ) =>
								setAttributes( {
									releaseId: parseInt( value, 10 ) || 0,
								} )
							}
						/>
					</PanelBody>
				</InspectorControls>

				<ServerSideRender
					block="mds-commerce/store-chooser"
					attributes={ attributes }
				/>
			</div>
		);
	},
	save() {
		return null;
	},
} );
