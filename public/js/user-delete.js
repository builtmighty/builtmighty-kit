/**
 * Delete Users search.
 *
 * Replaces the "Attribute all content to another user" drop-down on the Delete
 * Users screen with a paginated search, and shows what "Delete all content"
 * would remove. The native <select> stays in the form, visually hidden, so the
 * form still submits reassign_user and core's validation in common.js keeps
 * working. Data comes from BuiltMightyKit\Private\user_delete::enqueue().
 *
 * Standalone file: not built by laravel-mix. Edit it directly.
 *
 * @package Built Mighty Kit
 * @since   5.2.0
 */
( function () {
	'use strict';

	const settings = window.kitUserDelete;
	const form = document.getElementById( 'updateusers' );

	if ( ! settings || ! form ) {
		return;
	}

	const i18n = settings.i18n;
	let instance = 0;

	// Start after DOM-ready handlers that are already registered. Before WordPress 7.0,
	// core's own handler disables Confirm Deletion until a radio changes, so the
	// preselect below has to fire after it.
	if ( window.jQuery ) {
		window.jQuery( start );
	} else {
		start();
	}

	/**
	 * Set up every content decision on the screen.
	 */
	function start() {
		form.querySelectorAll( 'fieldset' ).forEach( setUpFieldset );
	}

	/**
	 * Set up one content decision.
	 *
	 * WordPress 7.0+ renders one fieldset per user (delete_option[ID]); older
	 * versions render a single fieldset for every user (delete_option).
	 *
	 * @param {HTMLFieldSetElement} fieldset The decision fieldset.
	 */
	function setUpFieldset( fieldset ) {
		const deleteRadio   = fieldset.querySelector( 'input[type="radio"][name^="delete_option"][value="delete"]' );
		const reassignRadio = fieldset.querySelector( 'input[type="radio"][name^="delete_option"][value="reassign"]' );

		if ( ! deleteRadio || ! reassignRadio ) {
			return;
		}

		const match   = /^delete_option\[(\d+)\]$/.exec( deleteRadio.name );
		const userIds = ( match ? [ match[ 1 ] ] : settings.exclude.map( String ) )
			.filter( ( id ) => settings.users[ id ] );

		addNotices( fieldset, userIds );

		// Preselect "Delete all content" when it would remove nothing.
		const safe = userIds.length > 0 && userIds.every( ( id ) => settings.users[ id ].safe );
		if ( safe && ! fieldset.querySelector( 'input[type="radio"]:checked' ) ) {
			deleteRadio.checked = true;
			deleteRadio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		const select = fieldset.querySelector( 'select[name^="reassign_user"]' ) ||
			createSelect( reassignRadio, match ? match[ 1 ] : '' );

		enhance( select, reassignRadio, ! match );
	}

	/**
	 * Show what "Delete all content" would do, above the choices.
	 *
	 * @param {HTMLFieldSetElement} fieldset The decision fieldset.
	 * @param {string[]}            userIds  Users this decision covers.
	 */
	function addNotices( fieldset, userIds ) {
		const choices = fieldset.querySelector( 'ul' );

		userIds.forEach( ( id ) => {
			const info = settings.users[ id ];

			if ( ! info.title ) {
				return;
			}

			const notice = document.createElement( 'div' );
			notice.className = 'notice inline kit-user-delete-notice notice-' + info.tone;

			const paragraph = document.createElement( 'p' );
			const strong = document.createElement( 'strong' );
			strong.textContent = info.title;
			paragraph.appendChild( strong );

			if ( info.detail ) {
				paragraph.appendChild( document.createTextNode( ' ' + info.detail ) );
			}

			notice.appendChild( paragraph );
			fieldset.insertBefore( notice, choices );
		} );
	}

	/**
	 * Create the reassign field when core rendered none.
	 *
	 * wp_dropdown_users() prints nothing when its query finds no users.
	 *
	 * @param {HTMLInputElement} reassignRadio The "Attribute all content" radio.
	 * @param {string}           userId        User ID on WordPress 7.0+, empty before.
	 * @return {HTMLSelectElement} The new field.
	 */
	function createSelect( reassignRadio, userId ) {
		const select = document.createElement( 'select' );
		select.name = userId ? 'reassign_user[' + userId + ']' : 'reassign_user';
		select.id = userId ? 'reassign_user_' + userId : 'reassign_user';
		select.appendChild( new Option( i18n.selectUser, '' ) );
		( reassignRadio.closest( 'li' ) || reassignRadio.parentNode ).appendChild( select );
		return select;
	}

	/**
	 * Whether the field holds a real user.
	 *
	 * @param {HTMLSelectElement} select The reassign field.
	 * @return {boolean} True when a user is selected.
	 */
	function hasUser( select ) {
		return select.selectedIndex >= 0 && '' !== select.value && '-1' !== select.value;
	}

	/**
	 * Turn a reassign field into a search.
	 *
	 * @param {HTMLSelectElement} select        The reassign field.
	 * @param {HTMLInputElement}  reassignRadio The "Attribute all content" radio.
	 * @param {boolean}           guardSubmit   Validate on submit (core does it on WordPress 7.0+).
	 */
	function enhance( select, reassignRadio, guardSubmit ) {
		const number   = ++instance;
		const listId   = 'kit-user-search-list-' + number;
		const statusId = 'kit-user-search-status-' + number;

		// The users core rendered, already limited by dropdown_args().
		const suggestions = Array.from( select.options )
			.filter( ( option ) => '' !== option.value && '-1' !== option.value )
			.map( ( option ) => ( { id: option.value, text: option.text, email: '' } ) );

		let options      = [];
		let active       = -1;
		let query        = '';
		let page         = 0;
		let more         = false;
		let selectedText = hasUser( select ) ? select.options[ select.selectedIndex ].text : null;
		let controller   = null;
		let timer        = null;

		// Build.
		const wrap = document.createElement( 'div' );
		wrap.className = 'kit-user-search';

		const input = document.createElement( 'input' );
		input.type = 'text';
		input.id = ( select.id || 'reassign_user' ) + '_search';
		input.className = 'regular-text kit-user-search-input';
		input.placeholder = i18n.placeholder;
		input.autocomplete = 'off';
		input.spellcheck = false;
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', listId );
		input.setAttribute( 'aria-describedby', statusId );

		if ( null !== selectedText ) {
			input.value = selectedText;
		}

		const list = document.createElement( 'div' );
		list.id = listId;
		list.className = 'kit-user-search-list';
		list.setAttribute( 'role', 'listbox' );
		list.hidden = true;

		const status = document.createElement( 'p' );
		status.id = statusId;
		status.className = 'description kit-user-search-status';
		status.setAttribute( 'aria-live', 'polite' );

		wrap.append( input, list, status );
		select.insertAdjacentElement( 'afterend', wrap );

		// Point core's screen-reader label at the search field.
		const label = select.id ? form.querySelector( 'label[for="' + select.id + '"]' ) : null;
		if ( label ) {
			label.htmlFor = input.id;
		} else {
			input.setAttribute( 'aria-label', i18n.label );
		}

		// Hide the native field. It still submits, and core's validation can still focus it.
		select.classList.add( 'kit-user-search-native' );
		select.tabIndex = -1;
		select.setAttribute( 'aria-hidden', 'true' );
		select.addEventListener( 'focus', () => input.focus() );

		// Mirror core's error state onto the search field.
		new MutationObserver( () => {
			if ( select.hasAttribute( 'aria-invalid' ) ) {
				input.setAttribute( 'aria-invalid', select.getAttribute( 'aria-invalid' ) );
			} else {
				input.removeAttribute( 'aria-invalid' );
			}
		} ).observe( select, { attributes: true, attributeFilter: [ 'aria-invalid' ] } );

		// Events.
		input.addEventListener( 'input', onInput );
		input.addEventListener( 'keydown', onKeydown );
		input.addEventListener( 'focus', open );
		input.addEventListener( 'click', open );
		input.addEventListener( 'blur', close );
		list.addEventListener( 'mousedown', ( event ) => event.preventDefault() );

		if ( guardSubmit ) {
			form.addEventListener( 'submit', ( event ) => {
				if ( reassignRadio.checked && ! hasUser( select ) ) {
					event.preventDefault();
					input.setAttribute( 'aria-invalid', 'true' );
					status.textContent = i18n.chooseUser;
					input.focus();
				}
			} );
		}

		/**
		 * Open the list: suggestions when empty, otherwise the last results.
		 */
		function open() {
			if ( ! list.hidden ) {
				return;
			}

			if ( '' === input.value.trim() || input.value === selectedText ) {
				showSuggestions();
			} else if ( options.length ) {
				render();
			}
		}

		/**
		 * Close the list.
		 */
		function close() {
			list.hidden = true;
			active = -1;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
		}

		/**
		 * Show the suggested users.
		 */
		function showSuggestions() {
			options = suggestions.slice();
			more = false;
			active = -1;
			render();

			if ( 1 === suggestions.length ) {
				status.textContent = i18n.suggestedOne;
			} else if ( suggestions.length ) {
				status.textContent = i18n.suggestedMany.replace( '%d', suggestions.length );
			} else {
				status.textContent = i18n.typeToSearch;
			}
		}

		/**
		 * Search as the user types.
		 */
		function onInput() {
			const value = input.value.trim();

			// Editing the text drops the previous choice.
			if ( null !== selectedText && input.value !== selectedText ) {
				clearSelection();
			}

			clearTimeout( timer );
			if ( controller ) {
				controller.abort();
			}

			if ( '' === value ) {
				showSuggestions();
				return;
			}

			if ( value.length < settings.minChars ) {
				options = [];
				more = false;
				active = -1;
				render();
				status.textContent = i18n.typeMore;
				return;
			}

			status.textContent = i18n.searching;
			timer = setTimeout( () => {
				query = value;
				fetchPage( 1 );
			}, 300 );
		}

		/**
		 * Keyboard support for the combobox.
		 *
		 * @param {KeyboardEvent} event Key event.
		 */
		function onKeydown( event ) {
			const count = list.hidden ? 0 : list.children.length;

			switch ( event.key ) {
				case 'ArrowDown':
				case 'ArrowUp':
					event.preventDefault();
					if ( list.hidden ) {
						open();
						return;
					}
					if ( count ) {
						active = 'ArrowDown' === event.key ? ( active + 1 ) % count : ( active - 1 + count ) % count;
						render();
					}
					break;

				case 'Enter':
					// Never let Enter in the search submit the deletion form.
					event.preventDefault();
					if ( ! list.hidden && active >= 0 ) {
						activate( active );
					}
					break;

				case 'Escape':
					if ( ! list.hidden ) {
						event.preventDefault();
						close();
					} else if ( input.value ) {
						event.preventDefault();
						input.value = '';
						onInput();
					}
					break;

				case 'Tab':
					close();
					break;
			}
		}

		/**
		 * Choose a result, or load the next page.
		 *
		 * @param {number} index Option index.
		 */
		function activate( index ) {
			if ( index === options.length && more ) {
				fetchPage( page + 1 );
			} else {
				choose( index );
			}
		}

		/**
		 * Put the chosen user into the native field.
		 *
		 * @param {number} index Option index.
		 */
		function choose( index ) {
			const user = options[ index ];

			if ( ! user ) {
				return;
			}

			const value = String( user.id );
			let option = Array.from( select.options ).find( ( item ) => item.value === value );
			if ( ! option ) {
				option = new Option( user.text, value );
				select.appendChild( option );
			}

			select.value = value;
			input.value = user.text;
			selectedText = user.text;
			input.removeAttribute( 'aria-invalid' );
			close();

			reassignRadio.checked = true;
			reassignRadio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );

			status.textContent = i18n.selected.replace( '%s', user.text );
		}

		/**
		 * Clear the chosen user.
		 *
		 * Also unchecks "Attribute all content", so the form can never submit a
		 * reassignment without a user. Older WordPress would delete the content.
		 */
		function clearSelection() {
			selectedText = null;

			if ( select.querySelector( 'option[value=""]' ) ) {
				select.value = '';
			} else {
				select.selectedIndex = -1;
			}

			if ( reassignRadio.checked ) {
				reassignRadio.checked = false;
				reassignRadio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}

			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		/**
		 * Fetch a page of results.
		 *
		 * @param {number} pageNumber Page to load, starting at 1.
		 */
		async function fetchPage( pageNumber ) {
			if ( controller ) {
				controller.abort();
			}
			controller = new AbortController();

			const params = new URLSearchParams( {
				action: settings.action,
				nonce: settings.nonce,
				q: query,
				page: String( pageNumber ),
			} );
			settings.exclude.forEach( ( id ) => params.append( 'exclude[]', id ) );

			status.textContent = i18n.searching;

			try {
				const response = await fetch( settings.ajaxUrl + '?' + params.toString(), {
					credentials: 'same-origin',
					signal: controller.signal,
				} );
				const body = await response.json();

				if ( ! response.ok || ! body || ! body.success ) {
					throw new Error( 'Search failed' );
				}

				const firstNew = 1 === pageNumber ? 0 : options.length;
				options = 1 === pageNumber ? body.data.results : options.concat( body.data.results );
				page = pageNumber;
				more = !! body.data.more;

				// After "Load more", keep keyboard focus on the first new result.
				active = 1 === pageNumber ? -1 : Math.min( firstNew, options.length - 1 );

				render();
				announce();
			} catch ( error ) {
				if ( 'AbortError' !== error.name ) {
					status.textContent = i18n.error;
				}
			}
		}

		/**
		 * Describe the results.
		 */
		function announce() {
			if ( ! options.length ) {
				status.textContent = i18n.noResults;
				return;
			}

			const found = 1 === options.length ? i18n.resultOne : i18n.resultMany.replace( '%d', options.length );
			status.textContent = more ? found + ' ' + i18n.moreAvailable : found;
		}

		/**
		 * Draw the list.
		 */
		function render() {
			list.textContent = '';

			options.forEach( ( user, index ) => {
				const option = createOption( index );

				const name = document.createElement( 'span' );
				name.className = 'kit-user-search-name';
				name.textContent = user.text;
				option.appendChild( name );

				if ( user.email ) {
					const email = document.createElement( 'span' );
					email.className = 'kit-user-search-email';
					email.textContent = user.email;
					option.appendChild( email );
				}

				list.appendChild( option );
			} );

			if ( more ) {
				const loadMore = createOption( options.length );
				loadMore.classList.add( 'kit-user-search-more' );
				loadMore.textContent = i18n.loadMore;
				list.appendChild( loadMore );
			}

			const count = list.children.length;
			list.hidden = 0 === count;
			input.setAttribute( 'aria-expanded', String( count > 0 ) );

			if ( active >= 0 && list.children[ active ] ) {
				input.setAttribute( 'aria-activedescendant', list.children[ active ].id );
				list.children[ active ].scrollIntoView( { block: 'nearest' } );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		/**
		 * Create one option row.
		 *
		 * @param {number} index Option index.
		 * @return {HTMLDivElement} The row.
		 */
		function createOption( index ) {
			const option = document.createElement( 'div' );
			option.id = listId + '-' + index;
			option.className = 'kit-user-search-option';
			option.setAttribute( 'role', 'option' );
			option.setAttribute( 'aria-selected', String( index === active ) );
			option.addEventListener( 'click', () => activate( index ) );
			return option;
		}
	}
}() );
