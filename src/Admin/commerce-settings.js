/**
 * Admin controller for Releases -> Settings -> Commerce:
 * - Sub-tab switching (Store Connections, Store Chooser Defaults, WooCommerce Product Injection)
 * - Add / Edit / Test / Delete Store Connections modal wizard
 * - Store Chooser global defaults form
 *
 * @since 3.0.0
 */
( function () {
	'use strict';

	const config = window.mdsCmSettings;
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

	// 1. Accessible sub-tab switching.
	const subTabButtons = document.querySelectorAll( '.mds-cm-subtab-btn' );
	const subTabPanels = document.querySelectorAll( '.mds-cm-subtab-panel' );

	subTabButtons.forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			const target = btn.getAttribute( 'data-subtab' );
			subTabButtons.forEach( ( b ) => {
				const active = b.getAttribute( 'data-subtab' ) === target;
				b.classList.toggle( 'is-active', active );
				b.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );
			subTabPanels.forEach( ( panel ) => {
				const active = panel.getAttribute( 'data-subtab-panel' ) === target;
				panel.hidden = ! active;
			} );
		} );
	} );

	// 2. Store Connections Manager & Wizard Modal.
	const noticeEl = document.getElementById( 'mds-cm-stores-notice' );
	const modal = document.getElementById( 'mds-cm-store-modal' );
	const form = document.getElementById( 'mds-cm-store-form' );
	const addBtn = document.getElementById( 'mds-cm-add-store-btn' );
	const tableBody = document.getElementById( 'mds-cm-remote-stores-tbody' );

	function openModal( store = null ) {
		if ( ! modal || ! form ) {
			return;
		}
		form.reset();
		const promo = ( store && store.promo ) || {};
		form.elements.store_id.value = store ? store.store_id || '' : '';
		form.elements.provider.value = store ? store.provider || 'shopify' : 'shopify';
		form.elements.name.value = store ? store.name || '' : '';
		form.elements.url.value = store ? store.url || '' : '';
		form.elements.api_key.value = store ? store.api_key || '' : '';
		form.elements.api_secret.value = '';
		form.elements.secret_constant.value = store ? store.secret_constant || '' : '';
		form.elements.sync_mode.value = store ? store.sync_mode || 'manual' : 'manual';
		form.elements.poll_interval.value = store ? store.poll_interval || 'hourly' : 'hourly';
		form.elements.promo_badge.value = promo.promo_badge || '';
		form.elements.coupon_code.value = promo.coupon_code || '';
		form.elements.promo_expires_gmt.value = promo.promo_expires_gmt || '';
		form.elements.priority_weight.value = String( promo.priority_weight || 0 );

		const titleEl = document.getElementById( 'mds-cm-store-modal-title' );
		if ( titleEl ) {
			titleEl.textContent = store
				? config.messages.editStore
				: config.messages.addStore;
		}
		if ( typeof modal.showModal === 'function' ) {
			modal.showModal();
		} else {
			modal.setAttribute( 'open', 'open' );
		}
	}

	function closeModal() {
		if ( ! modal ) {
			return;
		}
		if ( typeof modal.close === 'function' ) {
			modal.close();
		} else {
			modal.removeAttribute( 'open' );
		}
	}

	if ( addBtn ) {
		addBtn.addEventListener( 'click', () => openModal( null ) );
	}

	if ( modal ) {
		modal.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( '[data-action="close-store-modal"]' ) ) {
				closeModal();
			}
		} );
	}

	if ( form ) {
		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const payload = {
				store_id: form.elements.store_id.value.trim(),
				provider: form.elements.provider.value,
				name: form.elements.name.value.trim(),
				url: form.elements.url.value.trim(),
				api_key: form.elements.api_key.value.trim(),
				api_secret: form.elements.api_secret.value.trim(),
				secret_constant: form.elements.secret_constant.value.trim(),
				sync_mode: form.elements.sync_mode.value,
				poll_interval: form.elements.poll_interval.value,
				promo_badge: form.elements.promo_badge.value.trim(),
				coupon_code: form.elements.coupon_code.value.trim(),
				promo_expires_gmt: form.elements.promo_expires_gmt.value.trim(),
				priority_weight: parseInt( form.elements.priority_weight.value, 10 ) || 0,
			};

			try {
				const res = await restRequest( config.storesUrl, {
					method: 'POST',
					body: payload,
				} );
				closeModal();
				showNotice( noticeEl, res.message || config.messages.saved, 'success' );
				window.location.reload();
			} catch ( err ) {
				showNotice( noticeEl, err.message, 'error' );
			}
		} );
	}

	// Delegated Test / Edit / Delete buttons on Store Connections tables.
	document.addEventListener( 'click', async ( e ) => {
		const testBtn = e.target.closest( '.mds-cm-test-store-btn' );
		if ( testBtn ) {
			const key = testBtn.getAttribute( 'data-store-key' );
			const orig = testBtn.textContent;
			testBtn.disabled = true;
			testBtn.textContent = config.messages.testing;
			try {
				const res = await restRequest(
					config.storesUrl + '/' + encodeURIComponent( key ) + '/test',
					{ method: 'POST' }
				);
				showNotice( noticeEl, res.message, res.ok ? 'success' : 'error' );
			} catch ( err ) {
				showNotice( noticeEl, err.message, 'error' );
			} finally {
				testBtn.disabled = false;
				testBtn.textContent = orig;
			}
			return;
		}

		const editBtn = e.target.closest( '.mds-cm-edit-store-btn' );
		if ( editBtn ) {
			const raw = editBtn.getAttribute( 'data-store-json' );
			if ( raw ) {
				try {
					openModal( JSON.parse( raw ) );
				} catch ( err ) {
					showNotice( noticeEl, err.message, 'error' );
				}
			}
			return;
		}

		const delBtn = e.target.closest( '.mds-cm-delete-store-btn' );
		if ( delBtn ) {
			const key = delBtn.getAttribute( 'data-store-key' );
			if ( ! window.confirm( config.messages.confirmDelete ) ) {
				return;
			}
			try {
				const res = await restRequest(
					config.storesUrl + '/' + encodeURIComponent( key ),
					{ method: 'DELETE' }
				);
				const row = delBtn.closest( 'tr' );
				if ( row ) {
					row.remove();
				}
				if ( tableBody && 0 === tableBody.querySelectorAll( 'tr' ).length ) {
					window.location.reload();
				}
				showNotice( noticeEl, res.message, 'success' );
			} catch ( err ) {
				showNotice( noticeEl, err.message, 'error' );
			}
		}
	} );

	// 3. Store Chooser Global Defaults form.
	const chooserForm = document.getElementById( 'mds-cm-chooser-defaults-form' );
	const chooserNotice = document.getElementById( 'mds-cm-chooser-notice' );

	if ( chooserForm ) {
		chooserForm.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const payload = {
				heading: chooserForm.elements.heading.value.trim(),
				group_by: chooserForm.elements.group_by.value,
				layout: chooserForm.elements.layout.value,
				prioritize_promos: !! chooserForm.elements.prioritize_promos.checked,
			};

			try {
				const res = await restRequest( config.chooserDefaultsUrl, {
					method: 'POST',
					body: payload,
				} );
				showNotice( chooserNotice, res.message, 'success' );
			} catch ( err ) {
				showNotice( chooserNotice, err.message, 'error' );
			}
		} );
	}

	// 4. License & MDS API Gateway actions.
	const licenseForm = document.getElementById( 'mds-cm-license-form' );
	const licenseNotice = document.getElementById( 'mds-cm-license-notice' );
	const licenseRefresh = document.getElementById( 'mds-cm-license-refresh' );
	const licenseDeactivate = document.getElementById( 'mds-cm-license-deactivate' );

	if ( licenseForm && config.licenseBaseUrl ) {
		licenseForm.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const key = licenseForm.elements.license_key.value.trim();
			try {
				const res = await restRequest( config.licenseBaseUrl + '/activate', {
					method: 'POST',
					body: { license_key: key },
				} );
				showNotice( licenseNotice, res.message, 'success' );
				window.location.reload();
			} catch ( err ) {
				showNotice( licenseNotice, err.message, 'error' );
			}
		} );
	}

	if ( licenseRefresh && config.licenseBaseUrl ) {
		licenseRefresh.addEventListener( 'click', async () => {
			try {
				const res = await restRequest( config.licenseBaseUrl + '/refresh', {
					method: 'POST',
				} );
				showNotice( licenseNotice, res.message, 'success' );
				window.location.reload();
			} catch ( err ) {
				showNotice( licenseNotice, err.message, 'error' );
			}
		} );
	}

	if ( licenseDeactivate && config.licenseBaseUrl ) {
		licenseDeactivate.addEventListener( 'click', async () => {
			try {
				const res = await restRequest( config.licenseBaseUrl + '/deactivate', {
					method: 'POST',
				} );
				showNotice( licenseNotice, res.message, 'success' );
				window.location.reload();
			} catch ( err ) {
				showNotice( licenseNotice, err.message, 'error' );
			}
		} );
	}
} )();
