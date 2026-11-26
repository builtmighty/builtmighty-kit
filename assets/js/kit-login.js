/**
 * Built Mighty Kit: code input drawn as cells, and copy buttons.
 *
 * The code stays one real <input autocomplete="one-time-code">, so paste,
 * phone autofill, password managers and screen readers treat it as one
 * field. The cells are decoration on top.
 */
( function () {
	'use strict';

	document.querySelectorAll( '.bmk-otp' ).forEach( function ( box ) {
		var input = box.querySelector( 'input' );
		var len = parseInt( box.getAttribute( 'data-len' ), 10 ) || 6;
		var cells = document.createElement( 'div' );
		cells.className = 'bmk-cells';
		cells.setAttribute( 'aria-hidden', 'true' );
		var spans = [];
		for ( var i = 0; i < len; i++ ) {
			if ( len === 8 && i === 4 ) cells.appendChild( document.createElement( 'i' ) );
			var s = document.createElement( 'span' );
			spans.push( s );
			cells.appendChild( s );
		}
		box.appendChild( cells );
		box.classList.add( 'bmk-enhanced' );
		input.setAttribute( 'maxlength', String( len ) );

		var paint = function () {
			var v = input.value;
			spans.forEach( function ( s, i ) {
				s.textContent = v[ i ] || '';
				s.classList.toggle( 'bmk-filled', !! v[ i ] );
				s.classList.toggle( 'bmk-cur', i === Math.min( v.length, len - 1 ) );
			} );
		};
		input.addEventListener( 'input', function () {
			input.value = input.value.replace( /\D/g, '' ).slice( 0, len );
			input.removeAttribute( 'aria-invalid' );
			paint();
			if ( input.value.length === len && input.form && ! input.form.dataset.bmkSubmitted ) {
				input.form.dataset.bmkSubmitted = '1';
				input.form.requestSubmit ? input.form.requestSubmit() : input.form.submit();
			}
		} );
		input.addEventListener( 'focus', function () { box.classList.add( 'bmk-focus' ); var l = input.value.length; input.setSelectionRange( l, l ); } );
		input.addEventListener( 'blur', function () { box.classList.remove( 'bmk-focus' ); } );
		input.addEventListener( 'keydown', function ( e ) {
			if ( [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].indexOf( e.key ) > -1 ) e.preventDefault();
		} );
		paint();
		if ( document.activeElement === input ) box.classList.add( 'bmk-focus' );
	} );

	document.querySelectorAll( '[data-bmk-copy]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var el = document.querySelector( btn.getAttribute( 'data-bmk-copy' ) );
			if ( ! el ) return;
			var text = el.tagName === 'OL'
				? Array.prototype.map.call( el.querySelectorAll( 'li' ), function ( li ) { return li.textContent.trim(); } ).join( '\n' )
				: el.textContent.replace( /\s+/g, '' );
			var done = function () { var t = btn.textContent; btn.textContent = 'Copied'; setTimeout( function () { btn.textContent = t; }, 1600 ); };
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( text ).then( done, function () {} );
			}
		} );
	} );
} )();
