/**
 * Built Mighty Kit: passkeys (add from the profile, use at sign-in).
 */
( function () {
	'use strict';

	// Profile card: confirm risky actions, copy new backup codes.
	document.querySelectorAll( '.bmk-pcard [data-bmk-confirm]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( e ) {
			if ( ! window.confirm( btn.getAttribute( 'data-bmk-confirm' ) ) ) e.preventDefault();
		} );
	} );
	document.querySelectorAll( '[data-bmk-copy-list]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var list = document.querySelector( btn.getAttribute( 'data-bmk-copy-list' ) );
			if ( ! list || ! navigator.clipboard ) return;
			var text = Array.prototype.map.call( list.querySelectorAll( 'li' ), function ( li ) { return li.textContent.trim(); } ).join( '\n' );
			navigator.clipboard.writeText( text ).then( function () { btn.textContent = 'Copied'; } );
		} );
	} );
	// Enter in the passkey name field adds the passkey instead of saving the whole profile.
	var nameField = document.getElementById( 'kit-passkey-label' );
	if ( nameField ) nameField.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' ) { e.preventDefault(); var b = document.querySelector( '[data-bmk-passkey-add]' ); if ( b ) b.click(); }
	} );

	if ( ! window.PublicKeyCredential ) {
		document.querySelectorAll( '[data-bmk-passkey-add], [data-bmk-passkey-login]' ).forEach( function ( b ) {
			b.disabled = true;
			b.title = 'This browser doesn\'t support passkeys.';
		} );
		return;
	}

	var toBuf = function ( s ) {
		s = s.replace( /-/g, '+' ).replace( /_/g, '/' );
		while ( s.length % 4 ) s += '=';
		var bin = atob( s ), out = new Uint8Array( bin.length );
		for ( var i = 0; i < bin.length; i++ ) out[ i ] = bin.charCodeAt( i );
		return out.buffer;
	};
	var toB64 = function ( buf ) {
		var bytes = new Uint8Array( buf ), bin = '';
		for ( var i = 0; i < bytes.length; i++ ) bin += String.fromCharCode( bytes[ i ] );
		return btoa( bin ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	};
	var post = function ( url, body, nonce ) {
		var headers = { 'Content-Type': 'application/json' };
		if ( nonce ) headers[ 'X-WP-Nonce' ] = nonce;
		return fetch( url, { method: 'POST', credentials: 'same-origin', headers: headers, body: JSON.stringify( body || {} ) } )
			.then( function ( r ) { return r.json().then( function ( d ) { if ( ! r.ok ) throw new Error( d.message || 'Something went wrong.' ); return d; } ); } );
	};
	var friendly = function ( err ) {
		if ( err && err.name === 'NotAllowedError' ) return 'The passkey prompt was closed or timed out. Try again.';
		if ( err && err.name === 'InvalidStateError' ) return 'This device already has a passkey for your account.';
		return ( err && err.message ) || 'That didn\'t work. Try again.';
	};

	// Add a passkey (profile).
	var add = document.querySelector( '[data-bmk-passkey-add]' );
	if ( add ) {
		var status = document.querySelector( '[data-bmk-passkey-status]' );
		add.addEventListener( 'click', function () {
			var label = ( document.getElementById( 'kit-passkey-label' ) || {} ).value || '';
			add.disabled = true;
			status.textContent = 'Follow the prompt from your browser.';
			post( add.dataset.begin, {}, add.dataset.nonce ).then( function ( args ) {
				var pk = args.publicKey;
				pk.challenge = toBuf( pk.challenge );
				pk.user.id = toBuf( pk.user.id );
				( pk.excludeCredentials || [] ).forEach( function ( c ) { c.id = toBuf( c.id ); } );
				return navigator.credentials.create( { publicKey: pk } );
			} ).then( function ( cred ) {
				return post( add.dataset.finish, {
					label: label,
					clientDataJSON: toB64( cred.response.clientDataJSON ),
					attestationObject: toB64( cred.response.attestationObject )
				}, add.dataset.nonce );
			} ).then( function () {
				status.textContent = 'Passkey added. Reloading.';
				setTimeout( function () { window.location.reload(); }, 600 );
			} ).catch( function ( err ) {
				add.disabled = false;
				status.textContent = friendly( err );
			} );
		} );
	}

	// Use a passkey (code screen).
	var login = document.querySelector( '[data-bmk-passkey-login]' );
	if ( login ) {
		var field = document.getElementById( 'kit-passkey-response' );
		var errEl = document.getElementById( 'kit-passkey-err' );
		var go = function () {
			login.disabled = true;
			errEl.hidden = true;
			post( login.dataset.rest ).then( function ( args ) {
				var pk = args.publicKey;
				pk.challenge = toBuf( pk.challenge );
				( pk.allowCredentials || [] ).forEach( function ( c ) { c.id = toBuf( c.id ); } );
				return navigator.credentials.get( { publicKey: pk } );
			} ).then( function ( cred ) {
				field.value = JSON.stringify( {
					id: toB64( cred.rawId ),
					clientDataJSON: toB64( cred.response.clientDataJSON ),
					authenticatorData: toB64( cred.response.authenticatorData ),
					signature: toB64( cred.response.signature )
				} );
				login.form.submit();
			} ).catch( function ( err ) {
				login.disabled = false;
				errEl.textContent = friendly( err );
				errEl.hidden = false;
			} );
		};
		login.addEventListener( 'click', go );
	}
} )();
