/**
 * Built Mighty Kit admin.
 *
 * Settings controls, the save bar (Discard and Undo), confirm dialogs,
 * toasts, actions and Ctrl K search. No dependencies.
 */
( function () {
	'use strict';

	var app = document.querySelector( '.bmk-app' );
	if ( ! app || ! window.bmkKit ) return;

	var cfg = window.bmkKit;
	var $ = function ( s, r ) { return ( r || document ).querySelector( s ); };
	var $$ = function ( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); };
	var esc = function ( s ) { var d = document.createElement( 'div' ); d.textContent = String( s == null ? '' : s ); return d.innerHTML.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' ); };
	var icon = function ( name ) { return '<svg class="bmk-icon" aria-hidden="true"><use href="#bmk-i-' + name + '"/></svg>'; };

	// ------------------------------------------------------------------
	// Requests
	// ------------------------------------------------------------------

	function rest( path, body ) {
		return fetch( cfg.rest + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body || {} )
		} ).then( function ( res ) {
			return res.json().catch( function () { return {}; } ).then( function ( data ) {
				if ( ! res.ok ) {
					var err = new Error( ( data && data.message ) || 'Something went wrong. Reload the page and try again.' );
					err.data = data; err.status = res.status;
					throw err;
				}
				return data;
			} );
		} );
	}

	function ajax( action, data ) {
		var form = new FormData();
		form.append( 'action', action );
		Object.keys( data || {} ).forEach( function ( k ) { form.append( k, data[ k ] ); } );
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: form } )
			.then( function ( res ) { return res.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) throw new Error( ( json && ( typeof json.data === 'string' ? json.data : json.data && json.data.message ) ) || 'That didn\'t work. Try again.' );
				return json.data;
			} );
	}

	// ------------------------------------------------------------------
	// Toasts
	// ------------------------------------------------------------------

	var toasts = $( '.bmk-toasts', app );

	function toast( html, opts ) {
		opts = opts || {};
		var t = document.createElement( 'div' );
		t.className = 'bmk-toast' + ( opts.error ? ' bmk-toast-error' : '' );
		t.setAttribute( 'role', opts.error ? 'alert' : 'status' );
		t.innerHTML = icon( opts.error ? 'alert' : 'checkc' ) + '<span>' + html + '</span>' + ( opts.undo ? '<button type="button" class="bmk-toast-undo">Undo</button>' : '<span></span>' );
		toasts.appendChild( t );
		var timer;
		var kill = function () {
			if ( t.classList.contains( 'bmk-out' ) ) return;
			t.classList.add( 'bmk-out' );
			setTimeout( function () { t.remove(); }, 170 );
		};
		var undo = $( '.bmk-toast-undo', t );
		if ( undo ) undo.addEventListener( 'click', function () { clearTimeout( timer ); kill(); opts.undo(); } );
		timer = setTimeout( kill, opts.error ? 8000 : 6000 );
		return t;
	}

	// ------------------------------------------------------------------
	// Dialog
	// ------------------------------------------------------------------

	var scrim = $( '[data-bmk-dialog]', app );
	var lastFocus = null;

	function dialog( opts ) {
		return new Promise( function ( resolve ) {
			var box = $( '.bmk-dialog', scrim );
			var ack = $( '[data-bmk-ack]', scrim );
			var ok = $( '[data-bmk-confirm]', scrim );
			var cancel = $( '[data-bmk-cancel]', scrim );
			var body = $( '[data-bmk-dialog-body]', scrim );

			$( '#bmk-dlg-t', scrim ).textContent = opts.title || '';
			$( '#bmk-dlg-d', scrim ).textContent = opts.text || '';
			$( '#bmk-dlg-d', scrim ).hidden = ! opts.text;
			body.innerHTML = '';
			if ( opts.body ) body.appendChild( opts.body );
			box.classList.toggle( 'bmk-wide', !! opts.wide );
			box.classList.toggle( 'bmk-neutral', ! opts.danger );
			$( '.bmk-dh .bmk-icon use', scrim ).setAttribute( 'href', '#bmk-i-' + ( opts.icon || ( opts.danger ? 'alert' : 'info' ) ) );

			ok.hidden = !! opts.alertOnly;
			ok.textContent = opts.confirmLabel || 'Confirm';
			ok.className = 'bmk-btn ' + ( opts.danger ? 'bmk-btn-danger' : 'bmk-btn-primary' );
			cancel.textContent = opts.cancelLabel || ( opts.alertOnly ? 'Close' : 'Cancel' );

			ack.hidden = ! opts.ack;
			if ( opts.ack ) {
				$( 'span', ack ).textContent = opts.ack;
				$( 'input', ack ).checked = false;
				ok.disabled = true;
				$( 'input', ack ).onchange = function ( e ) { ok.disabled = ! e.target.checked; };
			} else {
				ok.disabled = false;
			}

			lastFocus = document.activeElement;
			scrim.hidden = false;
			( opts.alertOnly ? cancel : cancel ).focus();

			var done = function ( result ) {
				scrim.hidden = true;
				ok.onclick = cancel.onclick = scrim.onclick = null;
				document.removeEventListener( 'keydown', onKey, true );
				if ( lastFocus ) lastFocus.focus();
				resolve( result );
			};
			var onKey = function ( e ) {
				if ( e.key === 'Escape' ) { e.preventDefault(); done( false ); return; }
				if ( e.key === 'Tab' ) {
					var f = $$( 'button:not([disabled]):not([hidden]), input:not([disabled]), iframe', box ).filter( function ( el ) { return el.offsetParent !== null; } );
					if ( ! f.length ) return;
					var first = f[ 0 ], last = f[ f.length - 1 ];
					if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); }
					else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
				}
			};
			ok.onclick = function () { done( true ); };
			cancel.onclick = function () { done( false ); };
			scrim.onclick = function ( e ) { if ( e.target === scrim ) done( false ); };
			document.addEventListener( 'keydown', onKey, true );
		} );
	}

	// ------------------------------------------------------------------
	// Settings controls
	// ------------------------------------------------------------------

	var controls = $$( '[data-kit-key]', app );

	function read( el ) {
		switch ( el.dataset.type ) {
			case 'bool': return el.getAttribute( 'aria-checked' ) === 'true';
			case 'list': return $$( '.bmk-tag', el ).map( function ( t ) { return t.dataset.value; } );
			case 'roles':
			case 'multi': return $$( 'input[type="checkbox"]:not([disabled])', el ).filter( function ( c ) { return c.checked && c.value !== 'on'; } ).map( function ( c ) { return c.value; } );
			case 'int': return el.value === '' ? '' : Number( el.value );
			case 'enum':
				if ( el.classList.contains( 'bmk-seg' ) ) {
					var on = $( 'button[aria-checked="true"]', el );
					return on ? on.dataset.value : '';
				}
				return el.value;
			default: return el.value;
		}
	}

	function write( el, value ) {
		switch ( el.dataset.type ) {
			case 'bool':
				el.setAttribute( 'aria-checked', value ? 'true' : 'false' );
				reveal( el );
				break;
			case 'list':
				$$( '.bmk-tag', el ).forEach( function ( t ) { t.remove(); } );
				( value || [] ).forEach( function ( v ) { addTag( el, v, true ); } );
				break;
			case 'roles':
			case 'multi':
				$$( 'input[type="checkbox"]:not([disabled])', el ).forEach( function ( c ) { c.checked = ( value || [] ).map( String ).indexOf( c.value ) > -1; } );
				break;
			case 'enum':
				if ( el.classList.contains( 'bmk-seg' ) ) {
					$$( 'button', el ).forEach( function ( b ) {
						var on = b.dataset.value === String( value );
						b.setAttribute( 'aria-checked', on ? 'true' : 'false' );
						b.tabIndex = on ? 0 : -1;
					} );
				} else {
					el.value = value;
				}
				break;
			default:
				el.value = value == null ? '' : value;
		}
	}

	function initial( el ) {
		try { return JSON.parse( el.dataset.initial ); } catch ( e ) { return null; }
	}

	function same( a, b ) { return JSON.stringify( a ) === JSON.stringify( b ); }

	function reveal( sw ) {
		var key = sw.dataset.kitKey;
		var more = $( '.bmk-setting-more[data-parent="' + key + '"]', app );
		if ( more ) more.hidden = sw.getAttribute( 'aria-checked' ) !== 'true';
	}

	function changes() {
		var out = {};
		controls.forEach( function ( el ) {
			var v = read( el );
			if ( ! same( v, initial( el ) ) ) out[ el.dataset.kitKey ] = v;
		} );
		return out;
	}

	var bar = $( '.bmk-savebar', app );
	var content = $( '.bmk-content', app );

	function dirtyCheck() {
		var n = Object.keys( changes() ).length;
		$( '[data-bmk-dirty]', bar ).textContent = n + ( n === 1 ? ' change' : ' changes' );
		bar.classList.toggle( 'bmk-show', n > 0 );
		content.classList.toggle( 'bmk-dirty', n > 0 );
	}

	function clearErrors() {
		$$( '.bmk-err', app ).forEach( function ( e ) { e.classList.remove( 'bmk-err' ); } );
		$$( '.bmk-errmsg[data-bmk-generated]', app ).forEach( function ( e ) { e.remove(); } );
		$$( '.bmk-errmsg', app ).forEach( function ( e ) { e.hidden = true; } );
	}

	function showError( key, message ) {
		var el = $( '[data-kit-key="' + key + '"]', app );
		if ( ! el ) return;
		var wrap = el.closest( '.bmk-field' ) || el.closest( '.bmk-setting' );
		wrap.classList.add( 'bmk-err' );
		var msg = $( '.bmk-errmsg', wrap );
		if ( ! msg ) {
			msg = document.createElement( 'span' );
			msg.className = 'bmk-errmsg';
			msg.setAttribute( 'data-bmk-generated', '' );
			( el.closest( '.bmk-setting-control' ) || el ).insertAdjacentElement( 'afterend', msg );
		}
		msg.hidden = false;
		msg.innerHTML = icon( 'alert' ) + '<span>' + esc( message ) + '</span>';
	}

	function applySaved( saved ) {
		Object.keys( saved ).forEach( function ( key ) {
			var el = $( '[data-kit-key="' + key + '"]', app );
			if ( ! el ) return;
			el.dataset.initial = JSON.stringify( saved[ key ] );
			write( el, saved[ key ] );
		} );
		dirtyCheck();
	}

	function save( payload, quiet ) {
		var btn = $( '[data-bmk-save]', bar );
		btn.setAttribute( 'aria-busy', 'true' );
		clearErrors();
		return rest( 'settings', { changes: payload } ).then( function ( res ) {
			btn.removeAttribute( 'aria-busy' );
			applySaved( res.saved || {} );
			if ( ! quiet ) {
				var n = Object.keys( res.saved || {} ).length;
				toast( '<b>Settings saved.</b> ' + n + ( n === 1 ? ' change' : ' changes' ) + ' applied.', {
					undo: function () {
						save( res.before || {}, true ).then( function () { toast( '<b>Changes undone.</b>' ); } );
					}
				} );
			}
			return res;
		} ).catch( function ( err ) {
			btn.removeAttribute( 'aria-busy' );
			var errors = err.data && err.data.errors;
			if ( errors ) {
				if ( err.data.saved ) applySaved( err.data.saved );
				Object.keys( errors ).forEach( function ( k ) { showError( k, errors[ k ] ); } );
				var firstKey = Object.keys( errors )[ 0 ];
				var first = firstKey && $( '[data-kit-key="' + firstKey + '"]', app );
				if ( first ) first.scrollIntoView( { block: 'center', behavior: 'smooth' } );
				toast( '<b>Some changes weren\'t saved.</b> Fix the highlighted fields and save again.', { error: true } );
			} else {
				toast( esc( err.message ), { error: true } );
			}
			throw err;
		} );
	}

	// Switches.
	$$( '.bmk-switch[data-kit-key]', app ).forEach( function ( sw ) {
		sw.addEventListener( 'click', function () {
			sw.setAttribute( 'aria-checked', sw.getAttribute( 'aria-checked' ) === 'true' ? 'false' : 'true' );
			reveal( sw );
			dirtyCheck();
		} );
	} );

	// Segments (radio groups with arrow keys).
	$$( '.bmk-seg[data-kit-key]', app ).forEach( function ( group ) {
		var btns = $$( 'button', group );
		var select = function ( i, focus ) {
			btns.forEach( function ( b, j ) {
				b.setAttribute( 'aria-checked', i === j ? 'true' : 'false' );
				b.tabIndex = i === j ? 0 : -1;
			} );
			if ( focus ) btns[ i ].focus();
			dirtyCheck();
		};
		btns.forEach( function ( b, i ) {
			b.addEventListener( 'click', function () { select( i ); } );
			b.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) { e.preventDefault(); select( ( i + 1 ) % btns.length, true ); }
				if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) { e.preventDefault(); select( ( i - 1 + btns.length ) % btns.length, true ); }
			} );
		} );
	} );

	// Inputs, selects, checkboxes.
	controls.forEach( function ( el ) {
		if ( el.matches( 'input, select, textarea' ) ) el.addEventListener( 'input', dirtyCheck );
		if ( el.matches( 'select' ) ) el.addEventListener( 'change', dirtyCheck );
		if ( el.matches( '.bmk-checks' ) ) el.addEventListener( 'change', dirtyCheck );
	} );

	// Tag inputs.
	function addTag( box, value, silent ) {
		value = String( value ).trim();
		if ( ! value ) return;
		if ( $$( '.bmk-tag', box ).some( function ( t ) { return t.dataset.value === value; } ) ) return;
		var tag = document.createElement( 'span' );
		tag.className = 'bmk-tag';
		tag.dataset.value = value;
		tag.innerHTML = esc( value ) + '<button type="button" aria-label="Remove ' + esc( value ) + '">' + icon( 'x' ).replace( 'bmk-icon', 'bmk-icon bmk-sm' ) + '</button>';
		$( 'input', box ).before( tag );
		if ( ! silent ) dirtyCheck();
	}
	$$( '.bmk-tags[data-kit-key]', app ).forEach( function ( box ) {
		var input = $( 'input', box );
		box.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '.bmk-tag button' );
			if ( rm ) { rm.parentElement.remove(); dirtyCheck(); input.focus(); return; }
			if ( e.target === box ) input.focus();
		} );
		var commit = function () {
			input.value.split( /[\s,]+/ ).forEach( function ( v ) { addTag( box, v ); } );
			input.value = '';
		};
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ',' ) { e.preventDefault(); commit(); }
			if ( e.key === 'Backspace' && ! input.value ) {
				var tags = $$( '.bmk-tag', box );
				if ( tags.length ) { tags[ tags.length - 1 ].remove(); dirtyCheck(); }
			}
		} );
		input.addEventListener( 'blur', commit );
		input.addEventListener( 'paste', function () { setTimeout( commit, 0 ); } );
	} );

	// Save bar.
	if ( bar ) {
		$( '[data-bmk-save]', bar ).addEventListener( 'click', function () {
			var payload = changes();
			if ( Object.keys( payload ).length ) save( payload ).catch( function () {} );
		} );
		$( '[data-bmk-discard]', bar ).addEventListener( 'click', function () {
			controls.forEach( function ( el ) { write( el, initial( el ) ); } );
			clearErrors();
			dirtyCheck();
			toast( '<b>Changes discarded.</b>' );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' && bar.classList.contains( 'bmk-show' ) ) {
				e.preventDefault();
				$( '[data-bmk-save]', bar ).click();
			}
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( Object.keys( changes() ).length ) { e.preventDefault(); e.returnValue = ''; }
		} );
	}

	// ------------------------------------------------------------------
	// Quick turn-on buttons and actions
	// ------------------------------------------------------------------

	$$( '[data-bmk-quick]', app ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var payload = {};
			payload[ btn.dataset.bmkQuick ] = btn.dataset.value === 'true' ? true : btn.dataset.value;
			btn.setAttribute( 'aria-busy', 'true' );
			rest( 'settings', { changes: payload } ).then( function () {
				toast( '<b>Turned on.</b> Reloading.' );
				setTimeout( function () { window.location.reload(); }, 500 );
			} ).catch( function ( err ) {
				btn.removeAttribute( 'aria-busy' );
				toast( esc( err.message ), { error: true } );
			} );
		} );
	} );

	$$( '[data-bmk-action]', app ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var go = function () {
				var data = {};
				Object.keys( btn.dataset ).forEach( function ( k ) {
					if ( k.indexOf( 'bmk' ) !== 0 ) data[ k ] = btn.dataset[ k ];
				} );
				btn.setAttribute( 'aria-busy', 'true' );
				rest( btn.dataset.bmkAction, data ).then( function ( res ) {
					btn.removeAttribute( 'aria-busy' );
					toast( '<b>' + esc( res.message || 'Done.' ) + '</b>' );
					if ( res.reload ) setTimeout( function () { window.location.reload(); }, 700 );
				} ).catch( function ( err ) {
					btn.removeAttribute( 'aria-busy' );
					toast( esc( err.message ), { error: true } );
				} );
			};
			if ( btn.dataset.bmkConfirmTitle ) {
				dialog( { title: btn.dataset.bmkConfirmTitle, text: btn.dataset.bmkConfirmText, confirmLabel: btn.textContent.trim(), danger: true } ).then( function ( ok ) { if ( ok ) go(); } );
			} else {
				go();
			}
		} );
	} );

	// Copy.
	$$( '[data-bmk-copy]', app ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var src = btn.parentElement.querySelector( '[data-bmk-copy-src]' );
			if ( ! src ) return;
			var text = src.textContent.trim();
			var fallback = function () {
				var r = document.createRange(); r.selectNodeContents( src );
				var s = window.getSelection(); s.removeAllRanges(); s.addRange( r );
				toast( 'Selected. Press Ctrl C to copy.' );
			};
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( text ).then( function () { toast( '<b>Copied.</b>' ); }, fallback );
			} else {
				fallback();
			}
		} );
	} );

	// Redirected email viewer.
	$$( '[data-bmk-mail]', app ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var mail;
			try { mail = JSON.parse( btn.dataset.bmkMail ); } catch ( e ) { return; }
			var wrap = document.createElement( 'div' );
			wrap.innerHTML = '<dl class="bmk-envdl"><dt>To</dt><dd></dd><dt>Sent</dt><dd></dd></dl>';
			$$( 'dd', wrap )[ 0 ].textContent = mail.to;
			$$( 'dd', wrap )[ 1 ].textContent = mail.when;
			var view;
			if ( mail.html ) {
				view = document.createElement( 'iframe' );
				view.setAttribute( 'sandbox', '' ); // No scripts, no forms, no navigation.
				view.setAttribute( 'title', 'Email preview' );
				view.srcdoc = mail.body;
			} else {
				view = document.createElement( 'pre' );
				view.textContent = mail.body;
			}
			view.style.marginTop = '12px';
			wrap.appendChild( view );
			dialog( { title: mail.subject || '(No subject)', body: wrap, wide: true, alertOnly: true, icon: 'mail' } );
		} );
	} );

	// ------------------------------------------------------------------
	// Bundler
	// ------------------------------------------------------------------

	var bundler = $( '[data-bmk-bundler]', app );
	if ( bundler ) {
		var picked = function ( type ) {
			return $$( '.bmk-bundle-list[data-type="' + type + '"] input:checked', bundler ).map( function ( c ) { return c.value; } );
		};
		var saveBtn = $( '[data-bmk-bundle-save]', bundler );
		var rebuildBtn = $( '[data-bmk-bundle-rebuild]', bundler );
		if ( saveBtn ) saveBtn.addEventListener( 'click', function () {
			rest( 'bundle', { js: picked( 'js' ), css: picked( 'css' ) } )
				.then( function ( res ) { toast( '<b>' + esc( res.message ) + '</b>' ); } )
				.catch( function ( err ) { toast( esc( err.message ), { error: true } ); } );
		} );
		if ( rebuildBtn ) rebuildBtn.addEventListener( 'click', function () {
			var spin = $( '.bmk-spin', rebuildBtn );
			spin.hidden = false; rebuildBtn.setAttribute( 'aria-busy', 'true' );
			rest( 'bundle', { js: picked( 'js' ), css: picked( 'css' ) } )
				.then( function () { return ajax( 'kit_rebuild_bundle', { _nonce: cfg.nonces.bundle } ); } )
				.then( function () { toast( '<b>Bundles rebuilt.</b> Visitors get the new files on their next page load.' ); } )
				.catch( function ( err ) { toast( esc( err.message ), { error: true } ); } )
				.then( function () { spin.hidden = true; rebuildBtn.removeAttribute( 'aria-busy' ); } );
		} );
	}

	// ------------------------------------------------------------------
	// Monitoring
	// ------------------------------------------------------------------

	$$( '[data-bmk-crm]', app ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var action = btn.dataset.bmkCrm;
			var labels = {
				kit_crm_register: 'Site connected.',
				kit_crm_test_connection: 'Connection works.',
				kit_crm_send_report_now: 'Report sent.',
				kit_crm_run_backfill: 'Past sales sent.'
			};
			btn.setAttribute( 'aria-busy', 'true' );
			ajax( action, { nonce: action === 'kit_crm_register' ? cfg.nonces.crmRegister : cfg.nonces.crm } )
				.then( function () {
					toast( '<b>' + labels[ action ] + '</b>' );
					if ( action === 'kit_crm_register' ) setTimeout( function () { window.location.reload(); }, 700 );
				} )
				.catch( function ( err ) { toast( esc( err.message ), { error: true } ); } )
				.then( function () { btn.removeAttribute( 'aria-busy' ); } );
		} );
	} );

	var keyBtn = $( '[data-bmk-crm-key]', app );
	if ( keyBtn ) keyBtn.addEventListener( 'click', function () {
		var input = $( '#bmk-crm-key', app );
		rest( 'crm/key', { key: input.value } )
			.then( function ( res ) { toast( '<b>' + esc( res.message ) + '</b>' ); setTimeout( function () { window.location.reload(); }, 700 ); } )
			.catch( function ( err ) { toast( esc( err.message ), { error: true } ); } );
	} );

	// ------------------------------------------------------------------
	// Search (Ctrl K)
	// ------------------------------------------------------------------

	var palette = $( '[data-bmk-palette]', app );
	var pInput = $( 'input', palette );
	var pList = $( 'ul', palette );
	var pEmpty = $( '.bmk-palette-empty', palette );
	var pIndex = 0;

	function renderResults() {
		var q = pInput.value.trim().toLowerCase();
		var words = q.split( /\s+/ ).filter( Boolean );
		var results = ( cfg.search || [] ).filter( function ( item ) {
			var hay = ( item.label + ' ' + item.desc + ' ' + item.page ).toLowerCase();
			return words.every( function ( w ) { return hay.indexOf( w ) > -1; } );
		} ).slice( 0, 30 );
		pIndex = 0;
		pList.innerHTML = results.map( function ( item, i ) {
			return '<li><a role="option" href="' + esc( item.url ) + '" aria-selected="' + ( i === 0 ? 'true' : 'false' ) + '"><b>' + esc( item.label ) + '</b><small>' + esc( item.desc ) + '</small><span>' + esc( item.page ) + '</span></a></li>';
		} ).join( '' );
		pEmpty.hidden = results.length > 0;
	}
	function openPalette() {
		lastFocus = document.activeElement;
		palette.hidden = false;
		pInput.value = '';
		renderResults();
		pInput.focus();
	}
	function closePalette() {
		palette.hidden = true;
		if ( lastFocus ) lastFocus.focus();
	}
	function move( d ) {
		var links = $$( 'a', pList );
		if ( ! links.length ) return;
		links[ pIndex ].setAttribute( 'aria-selected', 'false' );
		pIndex = ( pIndex + d + links.length ) % links.length;
		links[ pIndex ].setAttribute( 'aria-selected', 'true' );
		links[ pIndex ].scrollIntoView( { block: 'nearest' } );
	}
	$$( '[data-bmk-search]', app ).forEach( function ( b ) { b.addEventListener( 'click', openPalette ); } );
	pInput.addEventListener( 'input', renderResults );
	pInput.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'ArrowDown' ) { e.preventDefault(); move( 1 ); }
		if ( e.key === 'ArrowUp' ) { e.preventDefault(); move( -1 ); }
		if ( e.key === 'Enter' ) {
			var link = $$( 'a', pList )[ pIndex ];
			if ( link ) { e.preventDefault(); window.location.href = link.href; closePalette(); }
		}
		if ( e.key === 'Escape' ) { e.preventDefault(); closePalette(); }
	} );
	pList.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( 'a' ) ) closePalette();
	} );
	palette.addEventListener( 'click', function ( e ) { if ( e.target === palette ) closePalette(); } );
	document.addEventListener( 'keydown', function ( e ) {
		if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 'k' ) {
			e.preventDefault();
			if ( palette.hidden ) openPalette(); else closePalette();
		}
	} );

	// Highlight a setting reached from search (on load, and when a result is on this page).
	function revealFromHash() {
		if ( window.location.hash.indexOf( '#bmk-row-' ) !== 0 ) return;
		var target = document.getElementById( window.location.hash.slice( 1 ) );
		if ( ! target ) return;
		var more = target.closest( '.bmk-setting-more' );
		if ( more && more.hidden ) {
			var parent = $( '[data-kit-key="' + more.dataset.parent + '"]', app );
			if ( parent ) { /* Show it without changing the setting. */ more.hidden = false; }
		}
		setTimeout( function () { target.scrollIntoView( { block: 'center' } ); }, 50 );
	}
	revealFromHash();
	window.addEventListener( 'hashchange', revealFromHash );

	dirtyCheck();

} )();
