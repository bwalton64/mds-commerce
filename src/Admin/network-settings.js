/**
 * Saves WooCommerce network settings through the REST API.
 *
 * @since 1.0.1
 */

( function ( $ ) {
	'use strict';

	/**
	 * Render an accessible inline notice and announce it to screen readers.
	 *
	 * @since 1.0.1
	 *
	 * @param {jQuery} $form   Settings form element.
	 * @param {string} message Status message text.
	 * @param {string} type    Notice type ('success' or 'error').
	 */
	function showNotice( $form, message, type ) {
		const $container = $form.find( '.mds-wc-network-settings-notice' );
		const noticeClass =
			'error' === type ? 'notice notice-error' : 'notice notice-success';

		if ( $container.length ) {
			$container
				.empty()
				.append(
					$( '<div />', { class: noticeClass } ).append(
						$( '<p />', { text: message } )
					)
				);
		}

		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak(
				message,
				'error' === type ? 'assertive' : 'polite'
			);
		}
	}

	$( function () {
		const config = window.mdsWcNetworkSettings;
		if ( ! config ) {
			return;
		}

		let $form = $( '#mds-wc-network-settings-form' );
		if ( ! $form.length ) {
			$form = $( '#mds-network-settings-form' );
		}

		$form.on( 'submit', function ( event ) {
			event.preventDefault();

			$.ajax( {
				url: config.url,
				method: 'POST',
				beforeSend( xhr ) {
					xhr.setRequestHeader( 'X-WP-Nonce', config.nonce );
				},
				data: $( this ).serialize(),
			} )
				.done( function () {
					showNotice( $form, config.messages.saved, 'success' );
				} )
				.fail( function () {
					showNotice( $form, config.messages.saveError, 'error' );
				} );
		} );
	} );
} )( jQuery );
