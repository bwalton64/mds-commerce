/**
 * Saves WooCommerce network settings through the REST API.
 *
 * @package MDS_WooCommerce_Integration
 */

(function ($) {
	'use strict';

	$(
		function () {
			$( '#mds-network-settings-form' ).on(
				'submit',
				function (event) {
					event.preventDefault();

					$.ajax(
						{
							url: window.mdsWooNetworkSettings.url,
							method: 'POST',
							beforeSend: function (xhr) {
								xhr.setRequestHeader( 'X-WP-Nonce', window.mdsWooNetworkSettings.nonce );
							},
							data: $( this ).serialize()
						}
					).done(
						function () {
							window.alert( window.mdsWooNetworkSettings.messages.saved );
						}
					).fail(
						function () {
							window.alert( window.mdsWooNetworkSettings.messages.saveError );
						}
					);
				}
			);
		}
	);
})( jQuery );
