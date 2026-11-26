/**
 * Built Mighty Kit in wp-admin: screenshot paste for the dashboard form.
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-bmk-slack-form]' );
	if ( ! form ) return;

	var drop = form.querySelector( '[data-bmk-paste]' );
	var input = form.querySelector( 'input[name="slack_screenshot"]' );
	var label = drop.querySelector( 'span' );
	var MAX = 5 * 1024 * 1024;

	function take( file ) {
		if ( ! file || file.type.indexOf( 'image' ) !== 0 ) return;
		if ( file.size > MAX ) { label.textContent = 'That image is over 5 MB. Try a smaller screenshot.'; return; }
		var reader = new FileReader();
		reader.onload = function ( e ) {
			drop.style.backgroundImage = 'url(' + e.target.result + ')';
			drop.classList.add( 'bmk-has-image' );
			drop.setAttribute( 'aria-label', 'Screenshot attached. Paste again to replace it.' );
			input.value = String( e.target.result ).replace( /^data:image\/[a-z+]+;base64,/, '' );
		};
		reader.readAsDataURL( file );
	}

	function onPaste( e ) {
		var items = ( e.clipboardData || {} ).items || [];
		for ( var i = 0; i < items.length; i++ ) {
			if ( items[ i ].type.indexOf( 'image' ) === 0 ) {
				e.preventDefault();
				take( items[ i ].getAsFile() );
				return;
			}
		}
	}

	drop.addEventListener( 'paste', onPaste );
	form.querySelector( 'textarea' ).addEventListener( 'paste', onPaste );
	drop.addEventListener( 'dragover', function ( e ) { e.preventDefault(); } );
	drop.addEventListener( 'drop', function ( e ) {
		e.preventDefault();
		if ( e.dataTransfer && e.dataTransfer.files[ 0 ] ) take( e.dataTransfer.files[ 0 ] );
	} );
	form.addEventListener( 'submit', function () {
		var btn = form.querySelector( 'button[type="submit"]' );
		btn.disabled = true;
		btn.textContent = 'Sending';
	} );
} )();
