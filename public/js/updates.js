/**
 * Built Mighty Kit: warning before plugin and theme changes on the live store.
 */
( function () {
	'use strict';
	var modal = document.getElementById( 'builtmighty-kit-modal' );
	if ( ! modal ) return;
	var leave = document.getElementById( 'builtmighty-kit-modal-leave' );
	var go = document.getElementById( 'builtmighty-kit-modal-continue' );
	var close = function () {
		modal.hidden = true;
		document.removeEventListener( 'keydown', onKey, true );
	};
	var onKey = function ( e ) {
		if ( e.key === 'Escape' ) { e.preventDefault(); close(); }
		if ( e.key === 'Tab' ) {
			if ( e.shiftKey && document.activeElement === leave ) { e.preventDefault(); go.focus(); }
			else if ( ! e.shiftKey && document.activeElement === go ) { e.preventDefault(); leave.focus(); }
		}
	};
	modal.hidden = false;
	leave.focus();
	document.addEventListener( 'keydown', onKey, true );
	go.addEventListener( 'click', close );
	leave.addEventListener( 'click', function () { window.location.href = window.kit_updates ? window.kit_updates.admin_url : '/wp-admin/'; } );
} )();
