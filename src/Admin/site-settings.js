/**
 * Designer-oriented dynamic MDS Block Injection manager with Up/Down steppers,
 * HTML5 Drag & Drop blueprint, collapsible Live Preview, and REST save.
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
	const availableBlocks = config.availableBlocks || {};
	const customBlockKey = config.customBlockKey || '__custom_shortcode';

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

	/**
	 * Resolve metadata for a block name from availableBlocks with safe fallback.
	 *
	 * @param {string} blockName Registered block name.
	 * @return {{name: string, title: string, description: string, badge: string, variant: string}}
	 */
	function getBlockMeta( blockName ) {
		if ( availableBlocks[ blockName ] ) {
			return availableBlocks[ blockName ];
		}
		return {
			name: blockName,
			title: blockName,
			description: '',
			badge: 'MD',
			variant: 'extension',
		};
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		const form = document.getElementById( 'mds-wc-site-settings-form' );
		const saveBtn = document.getElementById( 'mds-wc-site-save-btn' );
		const statusEl = document.getElementById( 'mds-wc-site-save-status' );
		const addBlockSelect = document.getElementById( 'mds-wc-add-block-select' );
		const addBlockBtn = document.getElementById( 'mds-wc-add-block-btn' );
		const cardsContainer = document.getElementById( 'mds-wc-block-cards' );
		const blueprintCanvas = form ? form.querySelector( '.mds-wc-blueprint__canvas' ) : null;

		const previewDetails = document.getElementById( 'mds-wc-live-preview' );
		const previewProductSelect = document.getElementById( 'mds-wc-preview-product' );
		const previewIframe = document.getElementById( 'mds-wc-preview-iframe' );
		const previewStage = document.getElementById( 'mds-wc-preview-stage' );
		const previewRefreshBtn = document.getElementById( 'mds-wc-preview-refresh' );
		const previewOpenTabLink = document.getElementById( 'mds-wc-preview-open-tab' );

		if ( ! form || ! saveBtn || ! cardsContainer ) {
			return;
		}

		let items = Array.isArray( config.items )
			? config.items.map( ( item ) => ( { ...item } ) )
			: [];
		let draggedItemId = null;
		let previewTimer = null;

		/**
		 * Find an item by its unique ID.
		 *
		 * @param {string} id Item ID.
		 * @return {Object|undefined}
		 */
		function findItem( id ) {
			return items.find( ( item ) => item.id === id );
		}

		/**
		 * Move an item to a specific slot or into the disabled tray.
		 *
		 * @param {string}  itemId     Item ID.
		 * @param {string}  targetSlot Active slot key or 'disabled'.
		 * @param {boolean} announce   Whether to speak status update.
		 */
		function moveItemToSlot( itemId, targetSlot, announce = true ) {
			const item = findItem( itemId );
			if ( ! item ) {
				return;
			}

			if ( 'disabled' === targetSlot ) {
				item.enabled = false;
			} else {
				item.enabled = true;
				item.placement = targetSlot;
			}

			renderAll();
			schedulePreviewRefresh();

			if ( announce ) {
				const meta = getBlockMeta( item.block );
				const slotName = slotLabels[ targetSlot ] || targetSlot;
				const template = i18n.movedTo || 'Moved %1$s to %2$s.';
				speak(
					template.replace( '%1$s', meta.title ).replace( '%2$s', slotName ),
					'polite'
				);
			}
		}

		/**
		 * Step an active item up or down by one slot.
		 *
		 * @param {string} itemId    Item ID.
		 * @param {string} direction 'up' or 'down'.
		 */
		function stepItem( itemId, direction ) {
			const item = findItem( itemId );
			if ( ! item || ! item.enabled || ! slotOrder.length ) {
				return;
			}

			const idx = slotOrder.indexOf( item.placement );
			if ( -1 === idx ) {
				return;
			}

			const nextIdx = 'up' === direction ? idx - 1 : idx + 1;
			if ( nextIdx < 0 || nextIdx >= slotOrder.length ) {
				return;
			}

			moveItemToSlot( itemId, slotOrder[ nextIdx ], true );
		}

		/**
		 * Add a new block item from the MDS Blocks dropdown.
		 */
		function addSelectedBlock() {
			if ( ! addBlockSelect || ! addBlockSelect.value ) {
				return;
			}

			const blockName = addBlockSelect.value;
			const meta = getBlockMeta( blockName );
			const defaultPlacement =
				'mds-rm/release-meta' === blockName
					? 'woocommerce_single_product_summary:25'
					: 'woocommerce_after_single_product_summary:15';

			items.push( {
				id: `item_${ Date.now() }_${ Math.floor( Math.random() * 1000 ) }`,
				block: blockName,
				placement: defaultPlacement,
				enabled: true,
				hide_buy_now: 'mds-rm/release-links' === blockName,
				content: '',
			} );

			renderAll();
			schedulePreviewRefresh();

			const msg = ( i18n.addedBlock || 'Added %s to product layout.' ).replace( '%s', meta.title );
			speak( msg, 'polite' );
		}

		/**
		 * Remove an item from the dynamic block list.
		 *
		 * @param {string} itemId Item ID.
		 */
		function removeItem( itemId ) {
			const item = findItem( itemId );
			if ( ! item ) {
				return;
			}

			const meta = getBlockMeta( item.block );
			items = items.filter( ( row ) => row.id !== itemId );
			renderAll();
			schedulePreviewRefresh();

			const msg = ( i18n.removedBlock || 'Removed %s from product layout.' ).replace( '%s', meta.title );
			speak( msg, 'polite' );
		}

		/**
		 * Render all dynamic block Control Cards in the left column.
		 */
		function renderControlCards() {
			cardsContainer.innerHTML = '';

			if ( ! items.length ) {
				const empty = document.createElement( 'p' );
				empty.className = 'description mds-wc-empty-blocks';
				empty.textContent = i18n.emptyList || 'No blocks added yet.';
				cardsContainer.appendChild( empty );
				return;
			}

			items.forEach( ( item ) => {
				const meta = getBlockMeta( item.block );
				const isEnabled = Boolean( item.enabled );
				const idx = slotOrder.indexOf( item.placement );
				const slotLabel = isEnabled
					? slotLabels[ item.placement ] || item.placement
					: slotLabels.disabled || 'Disabled';

				const card = document.createElement( 'div' );
				card.className = `mds-wc-control-card mds-wc-control-card--${ meta.variant } ${ isEnabled ? 'is-enabled' : 'is-disabled' }`;

				// Card Header: Badge + Title + Code + Enabled Toggle + Remove Button.
				const top = document.createElement( 'div' );
				top.className = 'mds-wc-control-card__top';

				const head = document.createElement( 'div' );
				head.className = 'mds-wc-control-card__head';

				const pill = document.createElement( 'span' );
				pill.className = `mds-wc-pill mds-wc-pill--${ meta.variant }`;
				pill.setAttribute( 'aria-hidden', 'true' );
				pill.textContent = meta.badge;

				const titleWrap = document.createElement( 'div' );
				const titleSpan = document.createElement( 'span' );
				titleSpan.className = 'mds-wc-control-card__title';
				titleSpan.textContent = meta.title;

				const codeEl = document.createElement( 'code' );
				codeEl.className = 'mds-wc-shortcode-tag';
				codeEl.textContent = customBlockKey === item.block ? '[custom]' : item.block;

				titleWrap.appendChild( titleSpan );
				titleWrap.appendChild( codeEl );
				head.appendChild( pill );
				head.appendChild( titleWrap );

				const topActions = document.createElement( 'div' );
				topActions.className = 'mds-wc-control-card__top-actions';

				const toggleLabel = document.createElement( 'label' );
				toggleLabel.className = 'mds-wc-enable-toggle';
				const toggleInput = document.createElement( 'input' );
				toggleInput.type = 'checkbox';
				toggleInput.checked = isEnabled;
				toggleInput.addEventListener( 'change', () => {
					moveItemToSlot( item.id, toggleInput.checked ? item.placement : 'disabled', true );
				} );
				const toggleText = document.createElement( 'span' );
				toggleText.textContent = i18n.enabled || 'Enabled';
				toggleLabel.appendChild( toggleInput );
				toggleLabel.appendChild( toggleText );

				const removeBtn = document.createElement( 'button' );
				removeBtn.type = 'button';
				removeBtn.className = 'mds-wc-remove-card-btn';
				removeBtn.setAttribute( 'aria-label', `${ i18n.remove || 'Remove block' }: ${ meta.title }` );
				removeBtn.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
				removeBtn.addEventListener( 'click', () => removeItem( item.id ) );

				topActions.appendChild( toggleLabel );
				topActions.appendChild( removeBtn );
				top.appendChild( head );
				top.appendChild( topActions );
				card.appendChild( top );

				// Position Stepper Row.
				const stepper = document.createElement( 'div' );
				stepper.className = 'mds-wc-stepper';

				const posEl = document.createElement( 'span' );
				posEl.className = 'mds-wc-stepper__position';
				posEl.textContent = slotLabel;

				const btnGroup = document.createElement( 'div' );
				btnGroup.className = 'mds-wc-stepper__buttons';

				const upBtn = document.createElement( 'button' );
				upBtn.type = 'button';
				upBtn.className = 'button button-secondary mds-wc-step-btn';
				upBtn.disabled = ! isEnabled || idx <= 0;
				upBtn.setAttribute( 'aria-label', `${ i18n.moveUp || 'Move up' }: ${ meta.title }` );
				upBtn.innerHTML = '<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>';
				upBtn.addEventListener( 'click', () => stepItem( item.id, 'up' ) );

				const downBtn = document.createElement( 'button' );
				downBtn.type = 'button';
				downBtn.className = 'button button-secondary mds-wc-step-btn';
				downBtn.disabled = ! isEnabled || -1 === idx || idx >= slotOrder.length - 1;
				downBtn.setAttribute( 'aria-label', `${ i18n.moveDown || 'Move down' }: ${ meta.title }` );
				downBtn.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
				downBtn.addEventListener( 'click', () => stepItem( item.id, 'down' ) );

				btnGroup.appendChild( upBtn );
				btnGroup.appendChild( downBtn );
				stepper.appendChild( posEl );
				stepper.appendChild( btnGroup );
				card.appendChild( stepper );

				if ( meta.description ) {
					const desc = document.createElement( 'p' );
					desc.className = 'description';
					desc.textContent = meta.description;
					card.appendChild( desc );
				}

				// Block-specific lightweight controls.
				if ( 'mds-rm/release-links' === item.block ) {
					const buyNowLabel = document.createElement( 'label' );
					buyNowLabel.className = 'mds-wc-inline-toggle';
					const buyNowInput = document.createElement( 'input' );
					buyNowInput.type = 'checkbox';
					buyNowInput.checked = Boolean( item.hide_buy_now );
					buyNowInput.addEventListener( 'change', () => {
						item.hide_buy_now = buyNowInput.checked;
						schedulePreviewRefresh();
					} );
					const buyNowSpan = document.createElement( 'span' );
					buyNowSpan.textContent = i18n.hideBuyNow || 'Hide Buy Now icon on product pages';
					buyNowLabel.appendChild( buyNowInput );
					buyNowLabel.appendChild( buyNowSpan );
					card.appendChild( buyNowLabel );
				} else if ( customBlockKey === item.block ) {
					const textarea = document.createElement( 'textarea' );
					textarea.className = 'large-text code mds-wc-custom-textarea';
					textarea.rows = 3;
					textarea.placeholder = i18n.customPlaceholder || '[release-links show_audio="1"]';
					textarea.setAttribute( 'aria-label', i18n.customLabel || 'Custom Shortcode or Block Markup' );
					textarea.value = item.content || '';
					textarea.addEventListener( 'input', () => {
						item.content = textarea.value;
					} );
					card.appendChild( textarea );
				}

				cardsContainer.appendChild( card );
			} );
		}

		/**
		 * Render draggable chips with inline Up/Down buttons inside each Blueprint slot.
		 */
		function renderBlueprintChips() {
			form.querySelectorAll( '.mds-wc-slot' ).forEach( ( slot ) => {
				const slotKey = slot.dataset.slot;
				const chipsWrap = slot.querySelector( '.mds-wc-slot__chips' );
				if ( ! chipsWrap ) {
					return;
				}
				chipsWrap.innerHTML = '';

				const matching = items.filter( ( item ) => {
					const effectiveSlot = item.enabled ? item.placement : 'disabled';
					return effectiveSlot === slotKey;
				} );

				slot.classList.toggle( 'has-items', matching.length > 0 );

				matching.forEach( ( item ) => {
					const meta = getBlockMeta( item.block );
					const idx = slotOrder.indexOf( slotKey );

					const chip = document.createElement( 'div' );
					chip.className = `mds-wc-slot-chip mds-wc-slot-chip--${ meta.variant }`;
					chip.setAttribute( 'draggable', 'true' );
					chip.dataset.itemId = item.id;

					const grip = document.createElement( 'span' );
					grip.className = 'dashicons dashicons-move mds-wc-slot-chip__grip';
					grip.setAttribute( 'aria-hidden', 'true' );

					const pill = document.createElement( 'span' );
					pill.className = `mds-wc-pill mds-wc-pill--${ meta.variant }`;
					pill.textContent = meta.badge;
					pill.setAttribute( 'aria-hidden', 'true' );

					const label = document.createElement( 'span' );
					label.className = 'mds-wc-slot-chip__label';
					label.textContent = meta.title;

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
						up.setAttribute( 'aria-label', `${ i18n.moveUp || 'Move up' }: ${ meta.title }` );
						up.innerHTML = '<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>';
						up.addEventListener( 'click', ( event ) => {
							event.stopPropagation();
							stepItem( item.id, 'up' );
						} );

						const down = document.createElement( 'button' );
						down.type = 'button';
						down.className = 'mds-wc-chip-step';
						down.disabled = -1 === idx || idx >= slotOrder.length - 1;
						down.setAttribute( 'aria-label', `${ i18n.moveDown || 'Move down' }: ${ meta.title }` );
						down.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
						down.addEventListener( 'click', ( event ) => {
							event.stopPropagation();
							stepItem( item.id, 'down' );
						} );

						actions.appendChild( up );
						actions.appendChild( down );
						chip.appendChild( actions );
					}

					chip.addEventListener( 'dragstart', ( event ) => {
						draggedItemId = item.id;
						chip.classList.add( 'is-dragging' );
						if ( blueprintCanvas ) {
							blueprintCanvas.classList.add( 'is-dragging-active' );
						}
						if ( event.dataTransfer ) {
							event.dataTransfer.effectAllowed = 'move';
							event.dataTransfer.setData( 'text/plain', item.id );
						}
					} );

					chip.addEventListener( 'dragend', () => {
						draggedItemId = null;
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
		 * Re-render both Control Cards and Blueprint Chips.
		 */
		function renderAll() {
			renderControlCards();
			renderBlueprintChips();
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

					const itemId =
						draggedItemId ||
						( event.dataTransfer ? event.dataTransfer.getData( 'text/plain' ) : '' );
					const targetSlot = slot.dataset.slot;

					if ( itemId && targetSlot ) {
						moveItemToSlot( itemId, targetSlot, true );
					}
					draggedItemId = null;
				} );
			} );
		}

		/**
		 * Build the signed Live Preview URL with current unsaved item placements.
		 *
		 * @return {string} Preview URL or empty string.
		 */
		function buildPreviewUrl() {
			if ( ! previewProductSelect || ! previewProductSelect.value ) {
				return '';
			}

			const previewItems = items.map( ( item ) => ( {
				id: item.id,
				block: item.block,
				placement: item.placement,
				enabled: Boolean( item.enabled ),
				hide_buy_now: Boolean( item.hide_buy_now ),
			} ) );

			const url = new window.URL( previewProductSelect.value );
			url.searchParams.set( 'mds_wc_preview', '1' );
			url.searchParams.set( 'mds_wc_preview_nonce', config.previewNonce || '' );
			url.searchParams.set( 'mds_wc_preview_items', JSON.stringify( previewItems ) );
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

		if ( addBlockBtn ) {
			addBlockBtn.addEventListener( 'click', addSelectedBlock );
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
		renderAll();

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
					body: JSON.stringify( { items } ),
				} );

				const data = await response.json().catch( () => ( {} ) );
				if ( ! response.ok || ( data && false === data.success ) ) {
					throw new Error( ( data && data.message ) || i18n.saveError || 'Unable to save settings.' );
				}

				if ( data && data.settings && Array.isArray( data.settings.items ) ) {
					items = data.settings.items;
					renderAll();
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
