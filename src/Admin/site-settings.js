/**
 * Designer-oriented WooCommerce product injection blueprint with Up/Down steppers,
 * HTML5 Drag & Drop placement, collapsible Live Preview, and REST save.
 *
 * @since 1.0.1
 */

( function () {
	'use strict';

	const config = window.mdsWcSiteSettings;
	if ( ! config ) {
		return;
	}

	const i18n = config.messages || {};
	const slotOrder = Array.isArray( config.slotOrder ) ? config.slotOrder : [];
	const slotLabels = config.slotLabels || {};

	/**
	 * Announce a message via wp.a11y.speak() when available.
	 *
	 * @since 1.0.1
	 *
	 * @param {string} message  Message text.
	 * @param {string} priority 'polite' or 'assertive'.
	 */
	function speak( message, priority = 'polite' ) {
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak && message ) {
			window.wp.a11y.speak( message, priority );
		}
	}

	/**
	 * Update inline status element and announce to screen readers.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement|null} el      Status container.
	 * @param {string}           type    'success' or 'error'.
	 * @param {string}           message Message text.
	 */
	function setStatus( el, type, message ) {
		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'is-success', 'is-error' );
			if ( 'success' === type ) {
				el.classList.add( 'is-success' );
			} else if ( 'error' === type ) {
				el.classList.add( 'is-error' );
			}
		}

		speak( message, 'error' === type ? 'assertive' : 'polite' );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		const form = document.getElementById( 'mds-wc-site-settings-form' );
		const saveBtn = document.getElementById( 'mds-wc-site-save-btn' );
		const statusEl = document.getElementById( 'mds-wc-site-save-status' );
		const hideBuyNowCheck = document.getElementById( 'mds-wc-hide-buy-now' );
		const customContentArea = document.getElementById( 'mds-wc-custom-content' );
		const blueprintCanvas = form ? form.querySelector( '.mds-wc-blueprint__canvas' ) : null;

		const previewDetails = document.getElementById( 'mds-wc-live-preview' );
		const previewProductSelect = document.getElementById( 'mds-wc-preview-product' );
		const previewIframe = document.getElementById( 'mds-wc-preview-iframe' );
		const previewStage = document.getElementById( 'mds-wc-preview-stage' );
		const previewRefreshBtn = document.getElementById( 'mds-wc-preview-refresh' );
		const previewOpenTabLink = document.getElementById( 'mds-wc-preview-open-tab' );

		if ( ! form || ! saveBtn ) {
			return;
		}

		const components = {
			meta: {
				code: 'RM',
				label: i18n.releaseMeta || 'Release Meta',
				defaultSlot: 'woocommerce_single_product_summary:25',
				input: document.getElementById( 'mds-wc-release-meta-placement' ),
			},
			links: {
				code: 'RL',
				label: i18n.releaseLinks || 'Release Links',
				defaultSlot: 'woocommerce_after_single_product_summary:15',
				input: document.getElementById( 'mds-wc-release-links-placement' ),
			},
			custom: {
				code: 'CS',
				label: i18n.customContent || 'Custom Shortcode / Block',
				defaultSlot: 'woocommerce_after_single_product_summary:15',
				input: document.getElementById( 'mds-wc-custom-placement' ),
			},
		};

		const lastActiveSlot = {};
		Object.keys( components ).forEach( ( key ) => {
			const val = components[ key ].input ? components[ key ].input.value : 'disabled';
			lastActiveSlot[ key ] = 'disabled' !== val ? val : components[ key ].defaultSlot;
		} );

		let draggedComponentKey = null;
		let previewTimer = null;

		/**
		 * Return the current unsaved form values.
		 *
		 * @return {Object} Current form state.
		 */
		function getCurrentValues() {
			return {
				release_meta_placement: components.meta.input ? components.meta.input.value : 'disabled',
				release_links_placement: components.links.input ? components.links.input.value : 'disabled',
				hide_buy_now: hideBuyNowCheck ? hideBuyNowCheck.checked : false,
				custom_placement: components.custom.input ? components.custom.input.value : 'disabled',
				custom_content: customContentArea ? customContentArea.value : '',
			};
		}

		/**
		 * Set a component's placement slot, update UI controls and blueprint, and refresh preview.
		 *
		 * @param {string}  componentKey Component key ('meta', 'links', 'custom').
		 * @param {string}  targetSlot   Target slot key or 'disabled'.
		 * @param {boolean} announce     Whether to announce via wp.a11y.speak().
		 */
		function setComponentSlot( componentKey, targetSlot, announce = true ) {
			const comp = components[ componentKey ];
			if ( ! comp || ! comp.input ) {
				return;
			}

			if ( 'disabled' !== targetSlot ) {
				lastActiveSlot[ componentKey ] = targetSlot;
			}

			comp.input.value = targetSlot;
			syncAllUI();
			schedulePreviewRefresh();

			if ( announce ) {
				const slotName = slotLabels[ targetSlot ] || targetSlot;
				const template = i18n.movedTo || 'Moved %1$s to %2$s.';
				speak(
					template.replace( '%1$s', comp.label ).replace( '%2$s', slotName ),
					'polite'
				);
			}
		}

		/**
		 * Move a component up or down by one slot in the ordered product layout.
		 *
		 * @param {string} componentKey Component key ('meta', 'links', 'custom').
		 * @param {string} direction    'up' or 'down'.
		 */
		function stepComponent( componentKey, direction ) {
			const comp = components[ componentKey ];
			if ( ! comp || ! comp.input || ! slotOrder.length ) {
				return;
			}

			const currentIndex = slotOrder.indexOf( comp.input.value );
			if ( -1 === currentIndex ) {
				return;
			}

			const nextIndex = 'up' === direction ? currentIndex - 1 : currentIndex + 1;
			if ( nextIndex < 0 || nextIndex >= slotOrder.length ) {
				return;
			}

			setComponentSlot( componentKey, slotOrder[ nextIndex ], true );
		}

		/**
		 * Synchronize Control Cards (labels, toggles, stepper disabled states) and Blueprint chips.
		 */
		function syncAllUI() {
			Object.keys( components ).forEach( ( key ) => {
				const comp = components[ key ];
				const val = comp.input ? comp.input.value : 'disabled';
				const isEnabled = 'disabled' !== val;
				const idx = slotOrder.indexOf( val );

				const card = form.querySelector( `[data-component-card="${ key }"]` );
				if ( card ) {
					card.classList.toggle( 'is-enabled', isEnabled );
					card.classList.toggle( 'is-disabled', ! isEnabled );
				}

				const toggle = form.querySelector( `.mds-wc-component-toggle[data-component="${ key }"]` );
				if ( toggle ) {
					toggle.checked = isEnabled;
				}

				const posLabel = form.querySelector( `[data-component-label="${ key }"]` );
				if ( posLabel ) {
					posLabel.textContent = slotLabels[ val ] || val;
				}

				const upBtn = form.querySelector( `.mds-wc-step-btn[data-component="${ key }"][data-direction="up"]` );
				const downBtn = form.querySelector( `.mds-wc-step-btn[data-component="${ key }"][data-direction="down"]` );
				if ( upBtn ) {
					upBtn.disabled = ! isEnabled || idx <= 0;
				}
				if ( downBtn ) {
					downBtn.disabled = ! isEnabled || -1 === idx || idx >= slotOrder.length - 1;
				}
			} );

			renderBlueprintChips();
		}

		/**
		 * Render draggable component chips with inline Up/Down buttons inside each Blueprint slot.
		 */
		function renderBlueprintChips() {
			const slots = form.querySelectorAll( '.mds-wc-slot' );

			slots.forEach( ( slot ) => {
				const slotKey = slot.dataset.slot;
				const chipsWrap = slot.querySelector( '.mds-wc-slot__chips' );
				if ( ! chipsWrap ) {
					return;
				}
				chipsWrap.innerHTML = '';

				const items = Object.keys( components ).filter( ( key ) => {
					const val = components[ key ].input ? components[ key ].input.value : 'disabled';
					return val === slotKey;
				} );

				slot.classList.toggle( 'has-items', items.length > 0 );

				items.forEach( ( key ) => {
					const comp = components[ key ];
					const idx = slotOrder.indexOf( slotKey );

					const chip = document.createElement( 'div' );
					chip.className = `mds-wc-slot-chip mds-wc-slot-chip--${ key }`;
					chip.setAttribute( 'draggable', 'true' );
					chip.dataset.component = key;

					const grip = document.createElement( 'span' );
					grip.className = 'dashicons dashicons-move mds-wc-slot-chip__grip';
					grip.setAttribute( 'aria-hidden', 'true' );

					const pill = document.createElement( 'span' );
					pill.className = `mds-wc-pill mds-wc-pill--${ key }`;
					pill.textContent = comp.code;
					pill.setAttribute( 'aria-hidden', 'true' );

					const label = document.createElement( 'span' );
					label.className = 'mds-wc-slot-chip__label';
					label.textContent = comp.label;

					chip.appendChild( grip );
					chip.appendChild( pill );
					chip.appendChild( label );

					if ( 'disabled' !== slotKey ) {
						const actions = document.createElement( 'span' );
						actions.className = 'mds-wc-slot-chip__actions';

						const up = document.createElement( 'button' );
						up.type = 'button';
						up.className = 'mds-wc-chip-step';
						up.disabled = idx <= 0;
						up.setAttribute( 'aria-label', `${ i18n.moveUp || 'Move up' }: ${ comp.label }` );
						up.innerHTML = '<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>';
						up.addEventListener( 'click', ( event ) => {
							event.stopPropagation();
							stepComponent( key, 'up' );
						} );

						const down = document.createElement( 'button' );
						down.type = 'button';
						down.className = 'mds-wc-chip-step';
						down.disabled = -1 === idx || idx >= slotOrder.length - 1;
						down.setAttribute( 'aria-label', `${ i18n.moveDown || 'Move down' }: ${ comp.label }` );
						down.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
						down.addEventListener( 'click', ( event ) => {
							event.stopPropagation();
							stepComponent( key, 'down' );
						} );

						actions.appendChild( up );
						actions.appendChild( down );
						chip.appendChild( actions );
					}

					chip.addEventListener( 'dragstart', ( event ) => {
						draggedComponentKey = key;
						chip.classList.add( 'is-dragging' );
						if ( blueprintCanvas ) {
							blueprintCanvas.classList.add( 'is-dragging-active' );
						}
						if ( event.dataTransfer ) {
							event.dataTransfer.effectAllowed = 'move';
							event.dataTransfer.setData( 'text/plain', key );
						}
					} );

					chip.addEventListener( 'dragend', () => {
						draggedComponentKey = null;
						chip.classList.remove( 'is-dragging' );
						if ( blueprintCanvas ) {
							blueprintCanvas.classList.remove( 'is-dragging-active' );
						}
						form.querySelectorAll( '.mds-wc-slot.is-drag-over' ).forEach( ( s ) => {
							s.classList.remove( 'is-drag-over' );
						} );
					} );

					chipsWrap.appendChild( chip );
				} );
			} );
		}

		/**
		 * Wire HTML5 drag-and-drop listeners onto every Blueprint slot and the Disabled tray.
		 */
		function initBlueprintDropzones() {
			form.querySelectorAll( '.mds-wc-slot' ).forEach( ( slot ) => {
				slot.addEventListener( 'dragover', ( event ) => {
					event.preventDefault();
					if ( event.dataTransfer ) {
						event.dataTransfer.dropEffect = 'move';
					}
					slot.classList.add( 'is-drag-over' );
				} );

				slot.addEventListener( 'dragleave', ( event ) => {
					if ( ! slot.contains( event.relatedTarget ) ) {
						slot.classList.remove( 'is-drag-over' );
					}
				} );

				slot.addEventListener( 'drop', ( event ) => {
					event.preventDefault();
					slot.classList.remove( 'is-drag-over' );
					if ( blueprintCanvas ) {
						blueprintCanvas.classList.remove( 'is-dragging-active' );
					}

					const key =
						draggedComponentKey ||
						( event.dataTransfer ? event.dataTransfer.getData( 'text/plain' ) : '' );
					const targetSlot = slot.dataset.slot;

					if ( key && components[ key ] && targetSlot ) {
						setComponentSlot( key, targetSlot, true );
					}
					draggedComponentKey = null;
				} );
			} );
		}

		/**
		 * Build the signed Live Preview URL with current unsaved placement overrides.
		 *
		 * @return {string} Preview URL or empty string.
		 */
		function buildPreviewUrl() {
			if ( ! previewProductSelect || ! previewProductSelect.value ) {
				return '';
			}

			const values = getCurrentValues();
			const url = new window.URL( previewProductSelect.value );
			url.searchParams.set( 'mds_wc_preview', '1' );
			url.searchParams.set( 'mds_wc_preview_nonce', config.previewNonce || '' );
			url.searchParams.set( 'release_meta_placement', values.release_meta_placement );
			url.searchParams.set( 'release_links_placement', values.release_links_placement );
			url.searchParams.set( 'hide_buy_now', values.hide_buy_now ? '1' : '0' );
			url.searchParams.set( 'custom_placement', values.custom_placement );
			url.searchParams.set( '_t', String( Date.now() ) );

			return url.toString();
		}

		/**
		 * Load or refresh the Live Preview iframe when the collapsible panel is open.
		 */
		function refreshLivePreview() {
			if ( ! previewDetails || ! previewDetails.open || ! previewIframe ) {
				return;
			}

			const previewUrl = buildPreviewUrl();
			if ( ! previewUrl ) {
				return;
			}

			previewIframe.src = previewUrl;
			if ( previewOpenTabLink ) {
				previewOpenTabLink.href = previewUrl;
			}
		}

		/**
		 * Debounced refresh when a designer adjusts placement while Live Preview is open.
		 */
		function schedulePreviewRefresh() {
			if ( ! previewDetails || ! previewDetails.open ) {
				return;
			}
			window.clearTimeout( previewTimer );
			previewTimer = window.setTimeout( refreshLivePreview, 250 );
		}

		form.querySelectorAll( '.mds-wc-step-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				stepComponent( btn.dataset.component, btn.dataset.direction );
			} );
		} );

		form.querySelectorAll( '.mds-wc-component-toggle' ).forEach( ( toggle ) => {
			toggle.addEventListener( 'change', () => {
				const key = toggle.dataset.component;
				if ( ! key || ! components[ key ] ) {
					return;
				}
				const targetSlot = toggle.checked
					? lastActiveSlot[ key ] || components[ key ].defaultSlot
					: 'disabled';
				setComponentSlot( key, targetSlot, true );
			} );
		} );

		if ( hideBuyNowCheck ) {
			hideBuyNowCheck.addEventListener( 'change', schedulePreviewRefresh );
		}

		if ( previewDetails ) {
			previewDetails.addEventListener( 'toggle', () => {
				if ( previewDetails.open ) {
					refreshLivePreview();
				}
			} );
		}

		if ( previewProductSelect ) {
			previewProductSelect.addEventListener( 'change', refreshLivePreview );
		}

		if ( previewRefreshBtn ) {
			previewRefreshBtn.addEventListener( 'click', refreshLivePreview );
		}

		form.querySelectorAll( '[data-viewport]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const viewport = btn.dataset.viewport || 'desktop';
				form.querySelectorAll( '[data-viewport]' ).forEach( ( other ) => {
					const active = other === btn;
					other.classList.toggle( 'is-active', active );
					other.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );
				if ( previewStage ) {
					previewStage.classList.remove( 'is-desktop', 'is-tablet', 'is-mobile' );
					previewStage.classList.add( `is-${ viewport }` );
				}
			} );
		} );

		initBlueprintDropzones();
		syncAllUI();

		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();

			saveBtn.disabled = true;
			saveBtn.textContent = i18n.saving || 'Saving…';
			setStatus( statusEl, '', '' );

			try {
				const response = await window.fetch( config.url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce,
					},
					body: JSON.stringify( getCurrentValues() ),
				} );

				const data = await response.json().catch( () => ( {} ) );
				if ( ! response.ok || ( data && false === data.success ) ) {
					throw new Error( ( data && data.message ) || i18n.saveError || 'Unable to save settings.' );
				}

				setStatus( statusEl, 'success', ( data && data.message ) || i18n.saved || 'Settings saved.' );
				if ( previewDetails && previewDetails.open ) {
					refreshLivePreview();
				}
			} catch ( err ) {
				setStatus( statusEl, 'error', err.message || i18n.saveError || 'Unable to save settings.' );
			} finally {
				saveBtn.disabled = false;
				saveBtn.textContent = i18n.save || 'Save Settings';
			}
		} );
	} );
} )();
