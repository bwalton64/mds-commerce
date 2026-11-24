/**
 * Artist CPT WooCommerce Target Stores meta box interactivity:
 * - Refresh auto-detected WooCommerce store list via REST.
 * - Batched cursor sync of Artist + Releases to assigned stores via REST.
 *
 * @since 1.1.0
 */

( function () {
	'use strict';

	const config = window.mdsWcArtistStores;
	if ( ! config ) {
		return;
	}

	const messages = config.messages || {};

	function speak( text, priority = 'polite' ) {
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( text, priority );
		}
	}

	function showNotice( container, text, type ) {
		if ( container ) {
			container.innerHTML = '';
			const div = document.createElement( 'div' );
			div.className =
				'error' === type
					? 'notice notice-error inline'
					: 'notice notice-success inline';
			const p = document.createElement( 'p' );
			p.textContent = text;
			div.appendChild( p );
			container.appendChild( div );
		}
		speak( text, 'error' === type ? 'assertive' : 'polite' );
	}

	async function restPost( url, body ) {
		const response = await window.fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( body || {} ),
		} );
		const data = await response.json().catch( () => ( {} ) );
		if ( ! response.ok ) {
			throw new Error(
				( data && data.message ) || messages.syncError || 'Request failed.'
			);
		}
		return data;
	}

	function getCheckedIds( root ) {
		return Array.from(
			root.querySelectorAll( 'input[name="mds_wc_target_stores[]"]:checked' )
		)
			.map( ( el ) => parseInt( el.value, 10 ) )
			.filter( ( n ) => ! Number.isNaN( n ) && n > 0 )
			.sort( ( a, b ) => a - b );
	}

	function init() {
		const root = document.querySelector( '.mds-wc-artist-stores' );
		if ( ! root ) {
			return;
		}

		const artistId = parseInt( root.getAttribute( 'data-artist-id' ) || '0', 10 );
		const noticeBox = root.querySelector( '.mds-wc-artist-stores__notice' );
		const listEl = root.querySelector( '.mds-wc-artist-stores__list' );
		const refreshBtn = root.querySelector( '.mds-wc-refresh-stores-btn' );
		const syncBtn = root.querySelector( '.mds-wc-sync-artist-releases-btn' );
		const dirtyHint = root.querySelector( '.mds-wc-artist-stores__dirty-hint' );

		const initialSelection = JSON.stringify( getCheckedIds( root ) );

		function updateDirtyState() {
			if ( ! syncBtn ) {
				return;
			}
			const current = getCheckedIds( root );
			const isDirty = JSON.stringify( current ) !== initialSelection;
			syncBtn.disabled = isDirty || 0 === current.length;
			if ( dirtyHint ) {
				dirtyHint.hidden = ! isDirty;
			}
		}

		root.addEventListener( 'change', ( event ) => {
			if ( event.target && 'mds_wc_target_stores[]' === event.target.name ) {
				updateDirtyState();
			}
		} );

		if ( refreshBtn ) {
			refreshBtn.addEventListener( 'click', async () => {
				const selected = new Set( getCheckedIds( root ) );
				refreshBtn.disabled = true;
				const originalLabel = refreshBtn.textContent;
				refreshBtn.textContent = messages.refreshing || 'Refreshing…';

				try {
					const data = await restPost( config.refreshUrl, {} );
					const stores = Array.isArray( data.stores ) ? data.stores : [];
					if ( listEl ) {
						listEl.innerHTML = '';
						if ( 0 === stores.length ) {
							const p = document.createElement( 'p' );
							p.className = 'mds-wc-artist-stores__empty';
							p.textContent =
								messages.noStores ||
								'No active WooCommerce stores were detected on this network.';
							listEl.appendChild( p );
						} else {
							stores.forEach( ( store ) => {
								const siteId = parseInt( store.site_id, 10 );
								const inputId = 'mds_wc_target_store_' + siteId;
								const label = document.createElement( 'label' );
								label.className = 'mds-wc-artist-stores__item';
								label.htmlFor = inputId;

								const input = document.createElement( 'input' );
								input.type = 'checkbox';
								input.id = inputId;
								input.name = 'mds_wc_target_stores[]';
								input.value = String( siteId );
								input.checked = selected.has( siteId );

								const textWrap = document.createElement( 'span' );
								textWrap.className = 'mds-wc-artist-stores__label';

								const strong = document.createElement( 'strong' );
								strong.textContent = store.site_name || '';
								textWrap.appendChild( strong );

								if ( store.site_url ) {
									const urlSpan = document.createElement( 'span' );
									urlSpan.className = 'mds-wc-artist-stores__url';
									urlSpan.textContent = store.site_url;
									textWrap.appendChild( urlSpan );
								}

								label.appendChild( input );
								label.appendChild( textWrap );
								listEl.appendChild( label );
							} );
						}
					}
					updateDirtyState();
					showNotice(
						noticeBox,
						messages.refreshed || 'WooCommerce store list refreshed.',
						'success'
					);
				} catch ( error ) {
					showNotice(
						noticeBox,
						error.message || messages.refreshError || 'Unable to refresh stores.',
						'error'
					);
				} finally {
					refreshBtn.disabled = false;
					refreshBtn.textContent = originalLabel;
				}
			} );
		}

		if ( syncBtn && artistId > 0 ) {
			syncBtn.addEventListener( 'click', async () => {
				syncBtn.disabled = true;
				const originalLabel = syncBtn.textContent;
				syncBtn.textContent = messages.syncing || 'Syncing Releases…';

				let page = 1;
				let totalSynced = 0;
				let totalErrors = 0;

				try {
					while ( true ) {
						const data = await restPost( config.syncUrl, {
							page,
						} );
						totalSynced += parseInt( data.synced || 0, 10 );
						totalErrors += parseInt( data.error_count || 0, 10 );

						if ( data.done ) {
							break;
						}
						page += 1;
					}

					const doneText =
						totalErrors > 0
							? ( messages.syncPartial ||
									'Synced %1$d release(s) with %2$d error(s).' )
									.replace( '%1$d', String( totalSynced ) )
									.replace( '%2$d', String( totalErrors ) )
							: ( messages.syncComplete ||
									'Synced Artist and %d release(s) to selected stores.' ).replace(
									'%d',
									String( totalSynced )
							  );

					showNotice( noticeBox, doneText, totalErrors > 0 ? 'error' : 'success' );
				} catch ( error ) {
					showNotice(
						noticeBox,
						error.message || messages.syncError || 'Unable to sync releases.',
						'error'
					);
				} finally {
					syncBtn.textContent = originalLabel;
					updateDirtyState();
				}
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
