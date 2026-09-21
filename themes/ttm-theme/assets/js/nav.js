/**
 * Phone navigation toggle. The only front-end script the theme ships (SPEC §3.2 rule 6).
 * No network requests, ever: toggles html.ttm-nav-open when the core Navigation block's
 * responsive overlay opens/closes, and closes the overlay on in-page link click.
 */
/* global MutationObserver */
( function () {
	'use strict';

	function setOpen( isOpen ) {
		document.documentElement.classList.toggle( 'ttm-nav-open', isOpen );
	}

	function observe( container ) {
		setOpen( container.classList.contains( 'is-menu-open' ) );

		new MutationObserver( function () {
			setOpen( container.classList.contains( 'is-menu-open' ) );
		} ).observe( container, {
			attributes: true,
			attributeFilter: [ 'class' ],
		} );

		container.addEventListener( 'click', function ( event ) {
			const link = event.target.closest( 'a[href]' );
			if ( ! link ) {
				return;
			}
			const closeButton = container.querySelector(
				'.wp-block-navigation__responsive-container-close'
			);
			if ( closeButton ) {
				closeButton.click();
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const containers = document.querySelectorAll(
			'.wp-block-navigation__responsive-container'
		);
		for ( let i = 0; i < containers.length; i++ ) {
			observe( containers[ i ] );
		}
	} );
} )();
