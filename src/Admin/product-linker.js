/**
 * Store Cards, accessible product search combobox, and Create Product modal
 * for Release editor screens (Seed & Hand Off architecture).
 *
 * @since 1.0.1
 */

( function () {
	'use strict';

	const config = window.mdsWcProductLinker;
	if ( ! config ) {
		return;
	}

	const messages = config.messages || {};

	/**
	 * Announce a message via wp.a11y.speak() when available.
	 *
	 * @since 1.0.1
	 *
	 * @param {string} text     Message text.
	 * @param {string} priority Politeness level ('polite' or 'assertive').
	 */
	function speak( text, priority = 'polite' ) {
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( text, priority );
		}
	}

	/**
	 * Render an inline WordPress notice inside a container element.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement|null} container Notice container element.
	 * @param {string}           message   Message text.
	 * @param {string}           type      'success' or 'error'.
	 */
	function showNotice( container, message, type ) {
		if ( container ) {
			container.innerHTML = '';
			const notice = document.createElement( 'div' );
			notice.className =
				'error' === type
					? 'notice notice-error inline'
					: 'notice notice-success inline';
			const p = document.createElement( 'p' );
			p.textContent = message;
			notice.appendChild( p );
			container.appendChild( notice );
		}

		speak( message, 'error' === type ? 'assertive' : 'polite' );
	}

	/**
	 * Perform an authenticated WordPress REST request.
	 *
	 * @since 1.0.1
	 *
	 * @param {string} url     Target REST endpoint URL.
	 * @param {Object} options Fetch options (method, body).
	 * @return {Promise<Object>} Parsed JSON response.
	 */
	async function restRequest( url, options = {} ) {
		const headers = {
			'X-WP-Nonce': config.nonce,
		};

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
			const error = new Error(
				( data && data.message ) || messages.createError || 'Request failed.'
			);
			error.data = data;
			throw error;
		}

		return data;
	}

	/**
	 * Read the latest Release fields from the Classic Editor DOM with server-seeded fallbacks.
	 *
	 * @since 1.0.1
	 *
	 * @return {{id: number, title: string, content: string, upc: string, thumbnailUrl: string}}
	 */
	function getCurrentReleaseSeed() {
		const seed = config.release || {};
		const titleEl = document.getElementById( 'title' );
		const excerptEl = document.getElementById( 'excerpt' );
		const contentEl = document.getElementById( 'content' );
		const upcEl =
			document.querySelector( 'input[name="upc_ean"]' ) ||
			document.getElementById( 'upc_ean' );
		const thumbImg = document.querySelector( '#postimagediv img' );

		let content = seed.content || '';
		if ( window.tinymce && window.tinymce.get( 'content' ) && ! window.tinymce.get( 'content' ).isHidden() ) {
			content = window.tinymce.get( 'content' ).getContent();
		} else if ( contentEl && typeof contentEl.value === 'string' ) {
			content = contentEl.value;
		}

		return {
			id: seed.id || 0,
			title: titleEl && titleEl.value ? titleEl.value : seed.title || '',
			excerpt:
				excerptEl && typeof excerptEl.value === 'string'
					? excerptEl.value
					: seed.excerpt || '',
			content,
			upc: upcEl && upcEl.value ? upcEl.value : seed.upc || '',
			thumbnailUrl:
				thumbImg && thumbImg.getAttribute( 'src' )
					? thumbImg.getAttribute( 'src' )
					: seed.thumbnailUrl || '',
		};
	}

	/**
	 * Collect all linked published WooCommerce products currently displayed in Store Cards.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement} wrapper Metabox wrapper element.
	 * @return {Array<{storeName: string, permalink: string}>}
	 */
	function getLinkedPublishedStores( wrapper ) {
		const items = [];
		wrapper.querySelectorAll( '.mds-wc-store-card.is-linked' ).forEach( ( card ) => {
			const viewLink = card.querySelector( '.mds-wc-action-view' );
			if ( viewLink && ! viewLink.hidden && viewLink.href && '#' !== viewLink.getAttribute( 'href' ) ) {
				items.push( {
					storeName: card.dataset.storeName || '',
					permalink: viewLink.href,
				} );
			}
		} );
		return items;
	}

	/**
	 * Sync the enabled/disabled state of the Release Manager Buy Now helper button.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement} wrapper Metabox wrapper element.
	 */
	function syncBuyNowButtonState( wrapper ) {
		const btn = document.getElementById( 'mds_use_linked_product_btn' );
		if ( ! btn ) {
			return;
		}
		const linked = getLinkedPublishedStores( wrapper );
		btn.disabled = 0 === linked.length;
	}

	/**
	 * Reindex the Primary radio values in the Release Manager Buy Now repeater.
	 *
	 * @since 1.0.1
	 */
	function reindexBuyNowPrimary() {
		const rows = document.querySelectorAll( '#mds_buy_now_links_wrapper .mds-buy-now-row' );
		let hasChecked = false;
		rows.forEach( ( row, index ) => {
			const radio = row.querySelector( 'input[name="mds_buy_now_primary"]' );
			if ( radio ) {
				radio.value = String( index );
				if ( radio.checked ) {
					hasChecked = true;
				}
			}
		} );
		if ( ! hasChecked && rows.length ) {
			const firstRadio = rows[ 0 ].querySelector( 'input[name="mds_buy_now_primary"]' );
			if ( firstRadio ) {
				firstRadio.checked = true;
			}
		}
	}

	/**
	 * Populate a Buy Now repeater row with format "all", store name, and product URL.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement} row       Repeater row element.
	 * @param {string}      storeName WooCommerce store name.
	 * @param {string}      url       Published product permalink.
	 */
	function fillBuyNowRow( row, storeName, url ) {
		const formatSelect = row.querySelector( 'select[name="mds_buy_now_format[]"]' );
		const storeInput = row.querySelector( 'input[name="mds_buy_now_store[]"]' );
		const urlInput = row.querySelector( 'input[name="mds_buy_now_url[]"]' );

		if ( formatSelect ) {
			let allOption = formatSelect.querySelector( 'option[value="all"]' );
			if ( ! allOption ) {
				allOption = document.createElement( 'option' );
				allOption.value = 'all';
				allOption.textContent = messages.allFormatLabel || 'All';
				formatSelect.appendChild( allOption );
			}
			formatSelect.value = 'all';
		}
		if ( storeInput ) {
			storeInput.value = storeName;
		}
		if ( urlInput ) {
			urlInput.value = url;
		}
	}

	/**
	 * Wire the "Use Linked Product URL" button in the Release Commerce box to the Multi-Format repeater.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement} wrapper Metabox wrapper element.
	 */
	function initBuyNowIntegration( wrapper ) {
		const btn = document.getElementById( 'mds_use_linked_product_btn' );
		if ( ! btn ) {
			return;
		}

		syncBuyNowButtonState( wrapper );

		btn.addEventListener( 'click', () => {
			const repeater = document.getElementById( 'mds_buy_now_links_wrapper' );
			const template = document.getElementById( 'mds_buy_now_row_template' );
			if ( ! repeater || ! template ) {
				return;
			}

			const linkedStores = getLinkedPublishedStores( wrapper );
			if ( ! linkedStores.length ) {
				return;
			}

			const existingUrls = new Set(
				Array.from( repeater.querySelectorAll( 'input[name="mds_buy_now_url[]"]' ) )
					.map( ( input ) => input.value.trim() )
					.filter( Boolean )
			);

			let addedCount = 0;

			linkedStores.forEach( ( item ) => {
				if ( existingUrls.has( item.permalink ) ) {
					return;
				}

				const emptyRow = Array.from( repeater.querySelectorAll( '.mds-buy-now-row' ) ).find( ( row ) => {
					const u = row.querySelector( 'input[name="mds_buy_now_url[]"]' );
					const f = row.querySelector( 'select[name="mds_buy_now_format[]"]' );
					return u && '' === u.value.trim() && ( ! f || '' === f.value );
				} );

				if ( emptyRow ) {
					fillBuyNowRow( emptyRow, item.storeName, item.permalink );
				} else {
					const index = repeater.querySelectorAll( '.mds-buy-now-row' ).length;
					const tempContainer = document.createElement( 'div' );
					tempContainer.innerHTML = template.innerHTML.replace( /__INDEX__/g, String( index ) );
					const newRow = tempContainer.firstElementChild;
					if ( newRow ) {
						fillBuyNowRow( newRow, item.storeName, item.permalink );
						repeater.appendChild( newRow );
					}
				}

				existingUrls.add( item.permalink );
				addedCount += 1;
			} );

			reindexBuyNowPrimary();

			if ( addedCount > 0 ) {
				speak( messages.buyNowAdded || 'Added linked WooCommerce product URL to Buy Now formats.', 'polite' );
			} else {
				speak( messages.buyNowExists || 'Linked WooCommerce product URLs are already listed.', 'polite' );
			}
		} );
	}

	/**
	 * Render or clear the Release-to-Product drift panel and badge on a Store Card.
	 *
	 * @since 2.1.0
	 *
	 * @param {HTMLElement} card  Store Card section element.
	 * @param {Object|null} drift Drift report payload (`{ has_drift, count, items }`).
	 */
	function renderDriftState( card, drift ) {
		const items = drift && Array.isArray( drift.items ) ? drift.items : [];
		const hasDrift = items.length > 0;
		const driftBadge = card.querySelector( '[data-role="drift-badge"]' );
		const driftPanel = card.querySelector( '.mds-wc-drift-panel' );
		const driftList = card.querySelector( '.mds-wc-drift-list' );

		card.classList.toggle( 'has-drift', hasDrift );

		if ( driftBadge ) {
			driftBadge.hidden = ! hasDrift;
		}

		if ( ! driftPanel || ! driftList ) {
			return;
		}

		driftList.innerHTML = '';

		if ( ! hasDrift ) {
			driftPanel.hidden = true;
			return;
		}

		items.forEach( ( item ) => {
			const li = document.createElement( 'li' );
			li.className = 'mds-wc-drift-item';

			const label = document.createElement( 'label' );
			label.className = 'mds-wc-drift-item__label';

			const checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.className = 'mds-wc-drift-checkbox';
			checkbox.value = item.field || '';
			checkbox.checked = true;

			const textWrap = document.createElement( 'span' );
			textWrap.className = 'mds-wc-drift-item__text';

			const strong = document.createElement( 'strong' );
			strong.textContent = `${ item.label || item.field || '' }: `;

			const diff = document.createElement( 'span' );
			diff.className = 'mds-wc-drift-diff';

			const fromEl = document.createElement( 'span' );
			fromEl.className = 'mds-wc-drift-diff__from';
			fromEl.textContent = item.product_value || '';

			const arrowEl = document.createElement( 'span' );
			arrowEl.className = 'mds-wc-drift-diff__arrow';
			arrowEl.setAttribute( 'aria-hidden', 'true' );
			arrowEl.textContent = ' → ';

			const toEl = document.createElement( 'span' );
			toEl.className = 'mds-wc-drift-diff__to';
			toEl.textContent = item.release_value || '';

			diff.appendChild( fromEl );
			diff.appendChild( arrowEl );
			diff.appendChild( toEl );

			textWrap.appendChild( strong );
			textWrap.appendChild( diff );

			label.appendChild( checkbox );
			label.appendChild( textWrap );
			li.appendChild( label );

			if ( 'upc' === item.field && item.sku_option && item.sku_option.available ) {
				const subLabel = document.createElement( 'label' );
				subLabel.className = 'mds-wc-drift-item__sub';

				const skuCheck = document.createElement( 'input' );
				skuCheck.type = 'checkbox';
				skuCheck.className = 'mds-wc-drift-checkbox';
				skuCheck.value = 'sku';
				skuCheck.checked = true;

				const skuSpan = document.createElement( 'span' );
				const template = messages.driftSkuOption || 'Also update SKU (%1$s → %2$s)';
				skuSpan.textContent = template
					.replace( '%1$s', item.sku_option.current_sku || '' )
					.replace( '%2$s', item.sku_option.new_sku || '' );

				subLabel.appendChild( skuCheck );
				subLabel.appendChild( skuSpan );
				li.appendChild( subLabel );
			}

			driftList.appendChild( li );
		} );

		driftPanel.hidden = false;
	}

	/**
	 * Update a Store Card DOM into its Linked state.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement} card    Store Card section element.
	 * @param {Object}      product Normalized product summary payload.
	 * @param {Object|null} drift   Optional drift report payload.
	 */
	function renderLinkedState( card, product, drift = null ) {
		card.classList.remove( 'is-unlinked' );
		card.classList.add( 'is-linked' );

		const badge = card.querySelector( '[data-role="connection-badge"]' );
		if ( badge ) {
			badge.textContent = messages.statusLinked || 'Linked';
			badge.classList.remove( 'mds-wc-badge--unlinked' );
			badge.classList.add( 'mds-wc-badge--linked' );
		}

		const thumbWrap = card.querySelector( '.mds-wc-product-summary__thumb' );
		if ( thumbWrap ) {
			thumbWrap.innerHTML = '';
			if ( product.thumbnail_url ) {
				const img = document.createElement( 'img' );
				img.src = product.thumbnail_url;
				img.alt = '';
				thumbWrap.appendChild( img );
			} else {
				const icon = document.createElement( 'span' );
				icon.className = 'dashicons dashicons-format-image';
				icon.setAttribute( 'aria-hidden', 'true' );
				thumbWrap.appendChild( icon );
			}
		}

		const titleEl = card.querySelector( '.mds-wc-product-summary__title' );
		if ( titleEl ) {
			titleEl.textContent = product.title || '';
		}

		const metaEl = card.querySelector( '.mds-wc-product-summary__meta' );
		if ( metaEl ) {
			metaEl.innerHTML = '';
			const statusBadge = document.createElement( 'span' );
			if ( 'publish' === product.status ) {
				statusBadge.className = 'mds-wc-badge mds-wc-badge--publish';
				statusBadge.textContent = messages.statusPublished || 'Published';
			} else {
				statusBadge.className = 'mds-wc-badge mds-wc-badge--draft';
				statusBadge.textContent =
					messages.statusDraftPending || 'Draft — Pending Store Review';
			}
			metaEl.appendChild( statusBadge );

			if ( product.price ) {
				const priceEl = document.createElement( 'span' );
				priceEl.className = 'mds-wc-product-summary__price';
				priceEl.textContent = product.price;
				metaEl.appendChild( priceEl );
			}

			if ( product.gtin ) {
				const gtinEl = document.createElement( 'span' );
				gtinEl.className = 'mds-wc-product-summary__gtin';
				gtinEl.textContent = `${ messages.gtinPrefix || 'GTIN:' } ${ product.gtin }`;
				metaEl.appendChild( gtinEl );
			}

			if ( product.sku ) {
				const skuEl = document.createElement( 'span' );
				skuEl.className = 'mds-wc-product-summary__sku';
				skuEl.textContent = `${ messages.skuPrefix || 'SKU:' } ${ product.sku }`;
				metaEl.appendChild( skuEl );
			}
		}

		const viewLink = card.querySelector( '.mds-wc-action-view' );
		if ( viewLink ) {
			if ( 'publish' === product.status && product.permalink ) {
				viewLink.href = product.permalink;
				viewLink.hidden = false;
			} else {
				viewLink.href = '#';
				viewLink.hidden = true;
			}
		}

		const editLink = card.querySelector( '.mds-wc-action-edit' );
		if ( editLink ) {
			if ( product.edit_url ) {
				editLink.href = product.edit_url;
				editLink.hidden = false;
			} else {
				editLink.href = '#';
				editLink.hidden = true;
			}
		}

		const linkedEl = card.querySelector( '.mds-wc-store-card__linked' );
		const pickerEl = card.querySelector( '.mds-wc-store-card__picker' );
		const cancelBtn = card.querySelector( '.mds-wc-cancel-change' );
		if ( linkedEl ) {
			linkedEl.hidden = false;
		}
		if ( pickerEl ) {
			pickerEl.hidden = true;
		}
		if ( cancelBtn ) {
			cancelBtn.hidden = true;
		}

		if ( null !== drift ) {
			renderDriftState( card, drift );
		}

		const wrapper = card.closest( '.mds-wc-linker-wrapper' );
		if ( wrapper ) {
			syncBuyNowButtonState( wrapper );
		}
	}

	/**
	 * Update a Store Card DOM into its Unlinked state.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement} card Store Card section element.
	 */
	function renderUnlinkedState( card ) {
		card.classList.remove( 'is-linked' );
		card.classList.add( 'is-unlinked' );

		const badge = card.querySelector( '[data-role="connection-badge"]' );
		if ( badge ) {
			badge.textContent = messages.statusUnlinked || 'Not Linked';
			badge.classList.remove( 'mds-wc-badge--linked' );
			badge.classList.add( 'mds-wc-badge--unlinked' );
		}

		const linkedEl = card.querySelector( '.mds-wc-store-card__linked' );
		const pickerEl = card.querySelector( '.mds-wc-store-card__picker' );
		const cancelBtn = card.querySelector( '.mds-wc-cancel-change' );
		const searchInput = card.querySelector( '.mds-wc-combobox__input' );

		if ( linkedEl ) {
			linkedEl.hidden = true;
		}
		if ( pickerEl ) {
			pickerEl.hidden = false;
		}
		if ( cancelBtn ) {
			cancelBtn.hidden = true;
		}
		if ( searchInput ) {
			searchInput.value = '';
		}

		renderDriftState( card, null );

		const wrapper = card.closest( '.mds-wc-linker-wrapper' );
		if ( wrapper ) {
			syncBuyNowButtonState( wrapper );
		}
	}

	/**
	 * Initialize a single Store Card's combobox search, linking, and unlink controls.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement} card           Store Card element.
	 * @param {HTMLElement} noticeEl       Metabox notice element.
	 * @param {Function}    openModalForCb Callback to open the Create Product modal.
	 */
	function initStoreCard( card, noticeEl, openModalForCb ) {
		const storeId = Number( card.dataset.storeId );
		const releaseId = Number( card.dataset.releaseId );
		const searchInput = card.querySelector( '.mds-wc-combobox__input' );
		const listbox = card.querySelector( '.mds-wc-combobox__listbox' );
		const changeBtn = card.querySelector( '.mds-wc-action-change' );
		const cancelChangeBtn = card.querySelector( '.mds-wc-cancel-change' );
		const unlinkBtn = card.querySelector( '.mds-wc-action-unlink' );
		const createBtn = card.querySelector( '.mds-wc-open-create-modal' );

		let debounceTimer = null;
		let activeIndex = -1;
		let currentResults = [];

		function closeListbox() {
			if ( listbox ) {
				listbox.hidden = true;
				listbox.innerHTML = '';
			}
			if ( searchInput ) {
				searchInput.setAttribute( 'aria-expanded', 'false' );
				searchInput.removeAttribute( 'aria-activedescendant' );
			}
			activeIndex = -1;
			currentResults = [];
		}

		async function selectProduct( product ) {
			closeListbox();
			if ( searchInput ) {
				searchInput.disabled = true;
			}
			speak( messages.linking || 'Linking product…', 'polite' );

			try {
				const response = await restRequest( config.linkUrl, {
					method: 'POST',
					body: {
						store_id: storeId,
						release_id: releaseId,
						product_id: product.id,
					},
				} );

				if ( response && response.success && response.product ) {
					renderLinkedState( card, response.product, response.drift || { items: [] } );
					showNotice( noticeEl, messages.linked, 'success' );
				}
			} catch ( err ) {
				showNotice(
					noticeEl,
					err.message || messages.linkError,
					'error'
				);
			} finally {
				if ( searchInput ) {
					searchInput.disabled = false;
				}
			}
		}

		function renderSearchResults( results ) {
			if ( ! listbox || ! searchInput ) {
				return;
			}

			listbox.innerHTML = '';
			currentResults = results;
			activeIndex = -1;

			if ( ! results.length ) {
				const emptyLi = document.createElement( 'li' );
				emptyLi.className = 'mds-wc-combobox__status';
				emptyLi.textContent = messages.noResults || 'No matching products found.';
				listbox.appendChild( emptyLi );
				listbox.hidden = false;
				searchInput.setAttribute( 'aria-expanded', 'true' );
				return;
			}

			results.forEach( ( item, index ) => {
				const li = document.createElement( 'li' );
				li.id = `mds-wc-opt-${ storeId }-${ item.id }`;
				li.className = 'mds-wc-combobox__option';
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', 'false' );

				const titleSpan = document.createElement( 'span' );
				titleSpan.className = 'mds-wc-combobox__option-title';
				titleSpan.textContent = item.title;

				const metaSpan = document.createElement( 'span' );
				metaSpan.className = 'mds-wc-combobox__option-meta';
				const metaParts = [ `#${ item.id }` ];
				if ( 'publish' !== item.status ) {
					metaParts.push( item.status );
				}
				if ( item.price ) {
					metaParts.push( item.price );
				}
				metaSpan.textContent = metaParts.join( ' · ' );

				li.appendChild( titleSpan );
				li.appendChild( metaSpan );

				li.addEventListener( 'mousedown', ( event ) => {
					event.preventDefault();
					selectProduct( item );
				} );

				li.addEventListener( 'mouseenter', () => {
					setActiveOption( index );
				} );

				listbox.appendChild( li );
			} );

			listbox.hidden = false;
			searchInput.setAttribute( 'aria-expanded', 'true' );
		}

		function setActiveOption( index ) {
			if ( ! listbox || ! searchInput ) {
				return;
			}
			const options = listbox.querySelectorAll( '[role="option"]' );
			options.forEach( ( opt, i ) => {
				const isActive = i === index;
				opt.classList.toggle( 'is-active', isActive );
				opt.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				if ( isActive ) {
					searchInput.setAttribute( 'aria-activedescendant', opt.id );
				}
			} );
			activeIndex = index;
		}

		async function runSearch( query ) {
			if ( ! listbox || ! searchInput ) {
				return;
			}

			listbox.innerHTML = '';
			const loadingLi = document.createElement( 'li' );
			loadingLi.className = 'mds-wc-combobox__status';
			loadingLi.textContent = messages.searching || 'Searching…';
			listbox.appendChild( loadingLi );
			listbox.hidden = false;
			searchInput.setAttribute( 'aria-expanded', 'true' );

			try {
				const url = new window.URL( config.searchUrl );
				url.searchParams.set( 'store_id', String( storeId ) );
				url.searchParams.set( 'release_id', String( releaseId ) );
				url.searchParams.set( 'q', query );

				const response = await restRequest( url.toString() );
				renderSearchResults( ( response && response.results ) || [] );
			} catch ( err ) {
				closeListbox();
				showNotice(
					noticeEl,
					err.message || messages.searchError,
					'error'
				);
			}
		}

		if ( searchInput ) {
			searchInput.addEventListener( 'input', () => {
				const query = searchInput.value.trim();
				window.clearTimeout( debounceTimer );

				if ( query.length < 2 ) {
					closeListbox();
					return;
				}

				debounceTimer = window.setTimeout( () => {
					runSearch( query );
				}, 250 );
			} );

			searchInput.addEventListener( 'keydown', ( event ) => {
				if ( listbox && ! listbox.hidden ) {
					if ( 'ArrowDown' === event.key ) {
						event.preventDefault();
						if ( currentResults.length ) {
							setActiveOption(
								( activeIndex + 1 ) % currentResults.length
							);
						}
					} else if ( 'ArrowUp' === event.key ) {
						event.preventDefault();
						if ( currentResults.length ) {
							setActiveOption(
								activeIndex <= 0
									? currentResults.length - 1
									: activeIndex - 1
							);
						}
					} else if ( 'Enter' === event.key ) {
						event.preventDefault();
						if ( activeIndex >= 0 && currentResults[ activeIndex ] ) {
							selectProduct( currentResults[ activeIndex ] );
						}
					} else if ( 'Escape' === event.key ) {
						event.preventDefault();
						closeListbox();
					}
				}
			} );

			searchInput.addEventListener( 'blur', () => {
				window.setTimeout( closeListbox, 150 );
			} );
		}

		if ( changeBtn ) {
			changeBtn.addEventListener( 'click', () => {
				const pickerEl = card.querySelector( '.mds-wc-store-card__picker' );
				if ( pickerEl ) {
					pickerEl.hidden = false;
				}
				if ( cancelChangeBtn ) {
					cancelChangeBtn.hidden = false;
				}
				if ( searchInput ) {
					searchInput.focus();
				}
			} );
		}

		if ( cancelChangeBtn ) {
			cancelChangeBtn.addEventListener( 'click', () => {
				const pickerEl = card.querySelector( '.mds-wc-store-card__picker' );
				if ( pickerEl ) {
					pickerEl.hidden = true;
				}
				cancelChangeBtn.hidden = true;
				closeListbox();
			} );
		}

		if ( unlinkBtn ) {
			unlinkBtn.addEventListener( 'click', async () => {
				if ( ! window.confirm( messages.confirmUnlink ) ) {
					return;
				}

				unlinkBtn.disabled = true;
				speak( messages.unlinking || 'Unlinking…', 'polite' );

				try {
					const url = new window.URL( config.linkUrl );
					url.searchParams.set( 'store_id', String( storeId ) );
					url.searchParams.set( 'release_id', String( releaseId ) );

					await restRequest( url.toString(), { method: 'DELETE' } );
					renderUnlinkedState( card );
					showNotice( noticeEl, messages.unlinked, 'success' );
				} catch ( err ) {
					showNotice(
						noticeEl,
						err.message || messages.unlinkError,
						'error'
					);
				} finally {
					unlinkBtn.disabled = false;
				}
			} );
		}

		if ( createBtn ) {
			createBtn.addEventListener( 'click', () => {
				openModalForCb( card, createBtn );
			} );
		}

		const syncDriftBtn = card.querySelector( '.mds-wc-action-sync-drift' );
		if ( syncDriftBtn && config.syncProductUrl ) {
			syncDriftBtn.addEventListener( 'click', async () => {
				const selectedFields = Array.from(
					card.querySelectorAll( '.mds-wc-drift-checkbox:checked' )
				)
					.map( ( input ) => input.value )
					.filter( Boolean );

				if ( ! selectedFields.length ) {
					showNotice(
						noticeEl,
						messages.driftSelectOne || 'Select at least one field to update.',
						'error'
					);
					return;
				}

				syncDriftBtn.disabled = true;
				syncDriftBtn.textContent = messages.driftSyncing || 'Updating product…';
				speak( messages.driftSyncing || 'Updating product…', 'polite' );

				try {
					const response = await restRequest( config.syncProductUrl, {
						method: 'POST',
						body: {
							store_id: storeId,
							release_id: releaseId,
							fields: selectedFields,
						},
					} );

					if ( response && response.success && response.product ) {
						renderLinkedState(
							card,
							response.product,
							response.drift || { items: [] }
						);
						const warnings = Array.isArray( response.warnings )
							? response.warnings.filter( Boolean )
							: [];
						const message = warnings.length
							? `${ messages.driftSynced || 'Product updated.' } ${ warnings.join( ' ' ) }`
							: messages.driftSynced || 'Selected fields updated on the WooCommerce product.';
						showNotice( noticeEl, message, warnings.length ? 'error' : 'success' );
					}
				} catch ( err ) {
					showNotice(
						noticeEl,
						err.message || messages.driftSyncError,
						'error'
					);
				} finally {
					syncDriftBtn.disabled = false;
					syncDriftBtn.textContent = messages.driftSyncBtn || 'Update Product';
				}
			} );
		}
	}

	/**
	 * Initialize the native <dialog> Create Product modal.
	 *
	 * @since 1.0.1
	 *
	 * @param {HTMLElement} wrapper Metabox wrapper element.
	 * @return {Function} Function to open the modal for a specific store card.
	 */
	function initCreateModal( wrapper ) {
		const dialog = document.getElementById( 'mds-wc-create-product-modal' );
		if ( ! dialog ) {
			return () => {};
		}

		const noticeEl = wrapper.querySelector( '.mds-wc-linker-notice' );
		const modalNotice = dialog.querySelector( '.mds-wc-modal__notice' );
		const storeLabel = document.getElementById( 'mds-wc-modal-store-label' );
		const nameInput = document.getElementById( 'mds-wc-create-name' );
		const shortDescInput = document.getElementById( 'mds-wc-create-short-desc' );
		const descInput = document.getElementById( 'mds-wc-create-desc' );
		const statusSelect = document.getElementById( 'mds-wc-create-status' );
		const priceInput = document.getElementById( 'mds-wc-create-price' );
		const skuInput = document.getElementById( 'mds-wc-create-sku' );
		const categoriesBox = document.getElementById( 'mds-wc-create-categories' );
		const tagsInput = document.getElementById( 'mds-wc-create-tags' );
		const tagsDatalist = document.getElementById( 'mds-wc-tag-suggestions' );
		const thumbWrap = document.getElementById( 'mds-wc-create-thumb-wrap' );
		const thumbImg = document.getElementById( 'mds-wc-create-thumb-img' );
		const syncThumbCheck = document.getElementById( 'mds-wc-create-sync-thumb' );
		const submitBtn = document.getElementById( 'mds-wc-create-submit' );

		let activeCard = null;
		let returnFocusEl = null;

		function closeModal() {
			if ( dialog.open ) {
				dialog.close();
			}
			if ( returnFocusEl && typeof returnFocusEl.focus === 'function' ) {
				returnFocusEl.focus();
			}
		}

		dialog.querySelectorAll( '[data-action="close-modal"]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', closeModal );
		} );

		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				closeModal();
			}
		} );

		async function loadStoreTaxonomies( storeId, releaseId ) {
			if ( categoriesBox ) {
				categoriesBox.innerHTML = `<p class="mds-wc-muted">${ messages.loadingTaxonomies || 'Loading…' }</p>`;
			}
			if ( tagsDatalist ) {
				tagsDatalist.innerHTML = '';
			}

			try {
				const url = new window.URL( config.taxonomiesUrl );
				url.searchParams.set( 'store_id', String( storeId ) );
				url.searchParams.set( 'release_id', String( releaseId ) );

				const data = await restRequest( url.toString() );
				const categories = ( data && data.categories ) || [];
				const tags = ( data && data.tags ) || [];

				if ( categoriesBox ) {
					categoriesBox.innerHTML = '';
					if ( ! categories.length ) {
						const p = document.createElement( 'p' );
						p.className = 'mds-wc-muted';
						p.textContent = messages.noCategories || 'No categories found.';
						categoriesBox.appendChild( p );
					} else {
						categories.forEach( ( cat ) => {
							const label = document.createElement( 'label' );
							label.className = 'mds-wc-category-item';
							if ( cat.parent ) {
								label.classList.add( 'is-child' );
							}

							const checkbox = document.createElement( 'input' );
							checkbox.type = 'checkbox';
							checkbox.name = 'mds_wc_modal_categories[]';
							checkbox.value = String( cat.id );

							label.appendChild( checkbox );
							label.appendChild( document.createTextNode( ` ${ cat.name }` ) );
							categoriesBox.appendChild( label );
						} );
					}
				}

				if ( tagsDatalist ) {
					tags.forEach( ( tag ) => {
						const opt = document.createElement( 'option' );
						opt.value = tag.name;
						tagsDatalist.appendChild( opt );
					} );
				}
			} catch ( err ) {
				if ( categoriesBox ) {
					categoriesBox.innerHTML = '';
				}
			}
		}

		if ( submitBtn ) {
			submitBtn.addEventListener( 'click', async () => {
				if ( ! activeCard ) {
					return;
				}

				const nameVal = nameInput ? nameInput.value.trim() : '';
				if ( ! nameVal ) {
					if ( nameInput ) {
						nameInput.focus();
					}
					return;
				}

				const storeId = Number( activeCard.dataset.storeId );
				const releaseId = Number( activeCard.dataset.releaseId );
				const selectedCats = Array.from(
					dialog.querySelectorAll( 'input[name="mds_wc_modal_categories[]"]:checked' )
				).map( ( el ) => Number( el.value ) );

				submitBtn.disabled = true;
				submitBtn.textContent = messages.creating || 'Creating product…';
				if ( modalNotice ) {
					modalNotice.innerHTML = '';
				}

				try {
					const response = await restRequest( config.generateUrl, {
						method: 'POST',
						body: {
							store_id: storeId,
							release_id: releaseId,
							name: nameVal,
							short_description: shortDescInput ? shortDescInput.value : '',
							description: descInput ? descInput.value : '',
							status: statusSelect ? statusSelect.value : 'draft',
							regular_price: priceInput ? priceInput.value.trim() : '',
							sku: skuInput ? skuInput.value.trim() : '',
							categories: selectedCats,
							tags: tagsInput ? tagsInput.value : '',
							sync_thumbnail: syncThumbCheck ? syncThumbCheck.checked : true,
						},
					} );

					if ( response && response.success && response.product ) {
						renderLinkedState( activeCard, response.product, response.drift || { items: [] } );
						closeModal();
						showNotice( noticeEl, messages.created, 'success' );
					}
				} catch ( err ) {
					showNotice(
						modalNotice,
						err.message || messages.createError,
						'error'
					);
				} finally {
					submitBtn.disabled = false;
					submitBtn.textContent = messages.createSubmit || 'Create & Link Product';
				}
			} );
		}

		return function openModalFor( card, triggerBtn ) {
			activeCard = card;
			returnFocusEl = triggerBtn;

			const release = getCurrentReleaseSeed();
			const storeId = Number( card.dataset.storeId );
			const releaseId = Number( card.dataset.releaseId );

			if ( modalNotice ) {
				modalNotice.innerHTML = '';
			}
			if ( storeLabel ) {
				storeLabel.textContent = card.dataset.storeName || '';
			}
			if ( nameInput ) {
				nameInput.value = release.title;
			}
			if ( shortDescInput ) {
				shortDescInput.value = release.excerpt || '';
			}
			if ( descInput ) {
				descInput.value = release.content;
			}
			if ( statusSelect ) {
				statusSelect.value = 'draft';
			}
			if ( priceInput ) {
				priceInput.value = '';
			}
			if ( skuInput ) {
				skuInput.value = release.upc;
			}
			if ( tagsInput ) {
				tagsInput.value = '';
			}
			if ( thumbWrap && thumbImg ) {
				if ( release.thumbnailUrl ) {
					thumbImg.src = release.thumbnailUrl;
					thumbWrap.hidden = false;
					if ( syncThumbCheck ) {
						syncThumbCheck.checked = true;
					}
				} else {
					thumbWrap.hidden = true;
				}
			}

			dialog.showModal();
			if ( nameInput ) {
				nameInput.focus();
			}
			loadStoreTaxonomies( storeId, releaseId );
		};
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		const wrapper = document.querySelector( '.mds-wc-linker-wrapper' );
		if ( ! wrapper ) {
			return;
		}

		const noticeEl = wrapper.querySelector( '.mds-wc-linker-notice' );
		const openModalFor = initCreateModal( wrapper );

		wrapper.querySelectorAll( '.mds-wc-store-card' ).forEach( ( card ) => {
			initStoreCard( card, noticeEl, openModalFor );
		} );

		initBuyNowIntegration( wrapper );
	} );
} )();
