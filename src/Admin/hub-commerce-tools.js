/**
 * Hub Release Admin Tools controller:
 * - Inline Store Chooser Promo / Coupon / Priority drawer on linked Store Cards
 * - Hub-side WooCommerce Variable product & WPC External Variations (wpcev_url/wpcev_text) modal
 * - POD & Manual-Portal Provisioning Assistant drawer (1-click Copy + MCP Prompt)
 *
 * @since 3.0.0
 */
( function () {
	'use strict';

	const config = window.mdsWcProductLinker;
	if ( ! config ) {
		return;
	}

	function speak( text, priority = 'polite' ) {
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( text, priority );
		}
	}

	function showNotice( container, message, type = 'success' ) {
		if ( container ) {
			container.innerHTML = '';
			const div = document.createElement( 'div' );
			div.className =
				'error' === type
					? 'notice notice-error inline'
					: 'notice notice-success inline';
			const p = document.createElement( 'p' );
			p.textContent = message;
			div.appendChild( p );
			container.appendChild( div );
		}
		speak( message, 'error' === type ? 'assertive' : 'polite' );
	}

	async function restRequest( url, options = {} ) {
		const headers = { 'X-WP-Nonce': config.nonce };
		const fetchOptions = {
			method: options.method || 'GET',
			credentials: 'same-origin',
			headers,
		};
		if ( options.body !== undefined ) {
			headers[ 'Content-Type' ] = 'application/json';
			fetchOptions.body = JSON.stringify( options.body );
		}
		const response = await window.fetch( url, fetchOptions );
		const data = await response.json().catch( () => ( {} ) );
		if ( ! response.ok ) {
			throw new Error( ( data && data.message ) || 'Request failed.' );
		}
		return data;
	}

	async function copyText( text, button, statusContainer ) {
		if ( ! navigator.clipboard || ! navigator.clipboard.writeText ) {
			return;
		}
		await navigator.clipboard.writeText( text );
		if ( button ) {
			const orig = button.textContent;
			button.textContent = 'Copied!';
			window.setTimeout( () => {
				button.textContent = orig;
			}, 1800 );
		}
		if ( statusContainer ) {
			speak( 'Copied to clipboard.', 'polite' );
		}
	}

	// 1. Inline Store Chooser & Promo Settings save handler.
	document.addEventListener( 'click', async ( e ) => {
		const savePromoBtn = e.target.closest( '.mds-cm-save-promo-btn' );
		if ( ! savePromoBtn ) {
			return;
		}

		const card = savePromoBtn.closest( '.mds-wc-store-card' );
		const drawer = savePromoBtn.closest( '.mds-cm-promo-drawer' );
		if ( ! card || ! drawer ) {
			return;
		}

		const releaseId = parseInt( card.getAttribute( 'data-release-id' ) || '0', 10 );
		const storeId = parseInt( card.getAttribute( 'data-store-id' ) || '0', 10 );
		const notice = drawer.querySelector( '.mds-cm-promo-notice' );

		const payload = {
			release_id: releaseId,
			store_id: storeId,
			format: ( drawer.querySelector( '[data-field="format"]' ) || {} ).value || '',
			promo_badge: ( drawer.querySelector( '[data-field="promo_badge"]' ) || {} ).value || '',
			coupon_code: ( drawer.querySelector( '[data-field="coupon_code"]' ) || {} ).value || '',
			promo_expires_gmt: ( drawer.querySelector( '[data-field="promo_expires_gmt"]' ) || {} ).value || '',
			priority_weight: parseInt( ( drawer.querySelector( '[data-field="priority_weight"]' ) || {} ).value || '0', 10 ) || 0,
		};

		savePromoBtn.disabled = true;
		try {
			const res = await restRequest( config.promoUrl, {
				method: 'POST',
				body: payload,
			} );
			showNotice( notice, res.message || 'Promo settings saved.', 'success' );
		} catch ( err ) {
			showNotice( notice, err.message, 'error' );
		} finally {
			savePromoBtn.disabled = false;
		}
	} );

	// 2. Hub-side Variations & WPC External Variations Modal.
	const varModal = document.getElementById( 'mds-cm-variations-modal' );
	const varForm = document.getElementById( 'mds-cm-variations-form' );
	const varList = document.getElementById( 'mds-cm-variations-list' );
	const varNotice = document.getElementById( 'mds-cm-variations-notice' );
	const varSubtitle = document.getElementById( 'mds-cm-variations-subtitle' );
	let varState = { releaseId: 0, storeId: 0, productId: 0 };

	function closeDialog( el ) {
		if ( ! el ) {
			return;
		}
		if ( typeof el.close === 'function' ) {
			el.close();
		} else {
			el.removeAttribute( 'open' );
		}
	}

	function openDialog( el ) {
		if ( ! el ) {
			return;
		}
		if ( typeof el.showModal === 'function' ) {
			el.showModal();
		} else {
			el.setAttribute( 'open', 'open' );
		}
	}

	if ( varModal ) {
		varModal.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( '[data-action="close-variations-modal"]' ) ) {
				closeDialog( varModal );
			}
		} );
	}

	document.addEventListener( 'click', async ( e ) => {
		const btn = e.target.closest( '.mds-cm-edit-variations-btn' );
		if ( ! btn || ! varModal || ! varList ) {
			return;
		}

		const card = btn.closest( '.mds-wc-store-card' );
		const releaseId = parseInt( card.getAttribute( 'data-release-id' ) || '0', 10 );
		const storeId = parseInt( card.getAttribute( 'data-store-id' ) || '0', 10 );
		const productId = parseInt( btn.getAttribute( 'data-product-id' ) || '0', 10 );

		varState = { releaseId, storeId, productId };
		if ( varSubtitle ) {
			varSubtitle.textContent = card.getAttribute( 'data-store-name' ) || '';
		}
		if ( varNotice ) {
			varNotice.innerHTML = '';
		}
		varList.innerHTML = '<p>Loading variations…</p>';
		openDialog( varModal );

		try {
			const url =
				config.variationsUrl +
				'?release_id=' +
				encodeURIComponent( releaseId ) +
				'&store_id=' +
				encodeURIComponent( storeId ) +
				'&product_id=' +
				encodeURIComponent( productId );
			const res = await restRequest( url );
			const variations = res.variations || [];

			if ( 0 === variations.length ) {
				varList.innerHTML =
					'<p class="description">This product has no variations yet. Convert it to a Variable Product in WooCommerce or seed a Variable Product to configure per-format external POD URLs.</p>';
				return;
			}

			let html = '';
			if ( ! res.supports_external_variations ) {
				html +=
					'<div class="notice notice-warning inline"><p>WPC External Variations is not active on this store. External URLs saved here will still power the MDS Store Chooser block.</p></div>';
			}

			html +=
				'<table class="widefat striped mds-cm-variations-table"><thead><tr><th>Variation</th><th>SKU</th><th>External POD URL (wpcev_url)</th><th>Button Text (wpcev_text)</th></tr></thead><tbody>';
			variations.forEach( ( v ) => {
				html +=
					'<tr data-variation-id="' +
					String( v.id ) +
					'">' +
					'<td><strong>' +
					escapeHtml( v.title ) +
					'</strong></td>' +
					'<td><input type="text" class="small-text" data-vfield="sku" value="' +
					escapeAttr( v.sku ) +
					'" /></td>' +
					'<td><input type="url" class="widefat" data-vfield="wpcev_url" placeholder="https://elasticstage.com/..." value="' +
					escapeAttr( v.wpcev_url ) +
					'" /></td>' +
					'<td><input type="text" class="widefat" data-vfield="wpcev_text" placeholder="Order Vinyl on elasticStage" value="' +
					escapeAttr( v.wpcev_text ) +
					'" /></td>' +
					'</tr>';
			} );
			html += '</tbody></table>';
			varList.innerHTML = html;
		} catch ( err ) {
			showNotice( varNotice, err.message, 'error' );
		}
	} );

	if ( varForm ) {
		varForm.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const rows = [];
			varList.querySelectorAll( 'tr[data-variation-id]' ).forEach( ( tr ) => {
				rows.push( {
					id: parseInt( tr.getAttribute( 'data-variation-id' ), 10 ),
					sku: ( tr.querySelector( '[data-vfield="sku"]' ) || {} ).value || '',
					wpcev_url: ( tr.querySelector( '[data-vfield="wpcev_url"]' ) || {} ).value || '',
					wpcev_text: ( tr.querySelector( '[data-vfield="wpcev_text"]' ) || {} ).value || '',
				} );
			} );

			try {
				const res = await restRequest( config.variationsUrl, {
					method: 'POST',
					body: {
						release_id: varState.releaseId,
						store_id: varState.storeId,
						product_id: varState.productId,
						variations: rows,
					},
				} );
				showNotice( varNotice, res.message || 'Variations saved.', 'success' );
			} catch ( err ) {
				showNotice( varNotice, err.message, 'error' );
			}
		} );
	}

	// 3. POD & Manual-Portal Provisioning Assistant Modal.
	const podModal = document.getElementById( 'mds-cm-pod-modal' );
	const podChannel = document.getElementById( 'mds-cm-pod-channel' );
	const podLaunch = document.getElementById( 'mds-cm-pod-launch-link' );
	const podCopyMcp = document.getElementById( 'mds-cm-pod-copy-mcp' );
	const podFields = document.getElementById( 'mds-cm-pod-fields' );
	const podArtQa = document.getElementById( 'mds-cm-pod-artwork-qa' );
	const podNotice = document.getElementById( 'mds-cm-pod-notice' );
	let podReleaseId = 0;

	async function loadPodPack( releaseId, channel ) {
		if ( ! podFields ) {
			return;
		}
		podFields.innerHTML = '<p>Loading provisioning pack…</p>';
		try {
			const url =
				config.podPackUrl +
				'?release_id=' +
				encodeURIComponent( releaseId ) +
				'&channel=' +
				encodeURIComponent( channel );
			const pack = await restRequest( url );

			if ( podLaunch ) {
				if ( pack.portal_url ) {
					podLaunch.href = pack.portal_url;
					podLaunch.hidden = false;
				} else {
					podLaunch.hidden = true;
				}
			}

			if ( podArtQa && pack.artwork ) {
				const art = pack.artwork;
				const badgeClass = art.print_ready
					? 'mds-wc-badge--linked'
					: 'mds-wc-badge--drift';
				const qaText = art.print_ready
					? 'Print-Ready Artwork (' + art.width + '×' + art.height + ')'
					: art.width > 0
						? 'Artwork Below 3000×3000 (' + art.width + '×' + art.height + ')'
						: 'No Featured Image Set';
				podArtQa.innerHTML =
					'<span class="mds-wc-badge ' +
					badgeClass +
					'">' +
					escapeHtml( qaText ) +
					'</span>' +
					( art.url
						? ' <a href="' +
							escapeAttr( art.url ) +
							'" target="_blank" rel="noopener noreferrer">Open Full-Res Cover Art</a>'
						: '' );
			}

			const fields = pack.fields || {};
			const rows = [
				[ 'Release Title', fields.title || '' ],
				[ 'Artist', fields.artist || '' ],
				[ 'UPC / EAN', fields.upc_ean || '' ],
				[ 'Suggested SKU', fields.suggested_sku || '' ],
				[ 'Release Date', fields.release_date || '' ],
				[ 'Record Label', fields.record_label || '' ],
				[ 'Media Formats', ( fields.media_formats || [] ).join( ', ' ) ],
				[ 'Short Summary', fields.short_summary || '' ],
			];

			podFields.innerHTML = rows
				.map( ( [ label, val ] ) => {
					return (
						'<div class="mds-cm-pod-row">' +
						'<div class="mds-cm-pod-row__label"><strong>' +
						escapeHtml( label ) +
						'</strong></div>' +
						'<div class="mds-cm-pod-row__val"><code>' +
						escapeHtml( val || '—' ) +
						'</code></div>' +
						'<button type="button" class="button button-small mds-cm-pod-copy-val" data-copy="' +
						escapeAttr( val ) +
						'"' +
						( val ? '' : ' disabled' ) +
						'>Copy</button>' +
						'</div>'
					);
				} )
				.join( '' );
		} catch ( err ) {
			showNotice( podNotice, err.message, 'error' );
		}
	}

	if ( podModal ) {
		podModal.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( '[data-action="close-pod-modal"]' ) ) {
				closeDialog( podModal );
				return;
			}
			const copyBtn = e.target.closest( '.mds-cm-pod-copy-val' );
			if ( copyBtn ) {
				copyText( copyBtn.getAttribute( 'data-copy' ) || '', copyBtn, podNotice );
			}
		} );
	}

	if ( podChannel ) {
		podChannel.addEventListener( 'change', () => {
			if ( podReleaseId > 0 ) {
				loadPodPack( podReleaseId, podChannel.value );
			}
		} );
	}

	if ( podCopyMcp ) {
		podCopyMcp.addEventListener( 'click', () => {
			const channel = podChannel ? podChannel.value : 'elasticstage';
			const prompt =
				'Call the WordPress MCP ability mds-commerce/get-pod-provisioning-pack with {"release_id": ' +
				podReleaseId +
				', "channel": "' +
				channel +
				'"} and use the returned verified metadata to fill out the open release creation form for my review before submitting.';
			copyText( prompt, podCopyMcp, podNotice );
		} );
	}

	document.addEventListener( 'click', ( e ) => {
		const openPodBtn = e.target.closest( '.mds-cm-open-pod-modal' );
		if ( ! openPodBtn || ! podModal ) {
			return;
		}
		podReleaseId = parseInt( openPodBtn.getAttribute( 'data-release-id' ) || '0', 10 );
		openDialog( podModal );
		loadPodPack( podReleaseId, podChannel ? podChannel.value : 'elasticstage' );
	} );

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function escapeAttr( str ) {
		return escapeHtml( str ).replace( /"/g, '&quot;' );
	}
} )();
