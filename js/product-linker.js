/**
 * Product search and generation for release editor screens.
 *
 * @package MDS_WooCommerce_Integration
 */

(function ($) {
	'use strict';

	$(
		function () {
			const configuration = window.mdsWooProductLinker;
			const nonce         = configuration.nonce;

			if ($.fn.selectWoo || $.fn.select2) {
				const selectMethod = $.fn.selectWoo ? 'selectWoo' : 'select2';

				$( '.mds-wc-select2' ).each(
					function () {
						const $element = $( this );
						const storeId  = $element.data( 'store-id' );

						$element[selectMethod](
							{
								ajax: {
									url: configuration.searchUrl,
									dataType: 'json',
									delay: 250,
									beforeSend: function (xhr) {
										xhr.setRequestHeader( 'X-WP-Nonce', nonce );
									},
									data: function (params) {
										return {
											q: params.term,
											store_id: storeId,
											release_id: $element.data( 'release-id' )
										};
									},
									processResults: function (response) {
										return {
											results: response.results || []
										};
									}
								},
								minimumInputLength: 3
							}
						);
					}
				);
			}

			$( '.mds-wc-generate-btn' ).on(
				'click',
				function (event) {
					event.preventDefault();

					const $button = $( this );
					$button.text( configuration.messages.generating ).prop( 'disabled', true );

					$.ajax(
						{
							url: configuration.generateUrl,
							method: 'POST',
							beforeSend: function (xhr) {
								xhr.setRequestHeader( 'X-WP-Nonce', nonce );
							},
							data: {
								store_id: $button.data( 'store-id' ),
								release_id: $button.data( 'post-id' )
							}
						}
					).done(
						function (response) {
							if (response && response.success === true) {
								$button.hide();
								const option = new window.Option(
									response.product_title,
									response.product_id,
									true,
									true
								);
								$( option ).attr( 'data-permalink', response.permalink );
								$button.siblings( 'select' ).append( option ).trigger( 'change' );
								return;
							}

							window.alert( configuration.messages.generateError );
							$button.text( configuration.messages.generate ).prop( 'disabled', false );
						}
					).fail(
						function (xhr) {
							const message = xhr.responseJSON && xhr.responseJSON.message
							? xhr.responseJSON.message
							: configuration.messages.generateError;

							window.alert( message );
							$button.text( configuration.messages.generate ).prop( 'disabled', false );
						}
					);
				}
			);
		}
	);
})( jQuery );
