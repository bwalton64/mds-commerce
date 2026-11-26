/**
 * Frontend interactions for the MDS Commerce Store Chooser block.
 *
 * Handles accessible 1-click coupon code copying with polite screen-reader status announcements.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.mds-cm-copy-coupon' );
		if ( ! button ) {
			return;
		}

		const coupon = button.getAttribute( 'data-coupon' );
		if ( ! coupon ) {
			return;
		}

		const chooser = button.closest( '.mds-cm-store-chooser' );
		const statusEl = chooser
			? chooser.querySelector( '.mds-cm-sr-status' )
			: null;
		const originalText = button.textContent;

		const announceCopied = function () {
			button.textContent = 'Copied!';
			if ( statusEl ) {
				statusEl.textContent = 'Coupon code ' + coupon + ' copied.';
			}
			window.setTimeout( function () {
				button.textContent = originalText;
			}, 2000 );
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard
				.writeText( coupon )
				.then( announceCopied )
				.catch( function () {} );
		}
	} );
} )();
