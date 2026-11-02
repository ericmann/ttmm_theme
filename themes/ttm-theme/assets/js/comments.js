/**
 * The comments section (patterns/comments.php). No network requests, ever. Without this script
 * the form and every comment simply show; with it:
 *
 * - the form waits behind the section's button, and core's comment-reply.js moves it under a
 *   comment when someone clicks Reply;
 * - threads with replies can be collapsed, and stay collapsed on the reader's next visit
 *   (localStorage, this browser only);
 * - comments posted since the reader's last visit are marked new;
 * - Reflections (journal) show the newest five, Reader notes (fiction) start closed.
 *
 * The body class ttm-comments-{discussion|reflections|notes} (ttm-core) picks the behaviour.
 */
( function () {
	'use strict';

	const words = window.ttmComments || {};
	const BUSY = 8;
	const REFLECTIONS_SHOWN = 5;

	function say( key, n ) {
		return String( words[ key ] || key ).replace( '%d', String( n ) );
	}

	function replies( n ) {
		return say( 1 === n ? 'reply' : 'replies', n );
	}

	function button( label, className ) {
		const el = document.createElement( 'button' );
		el.type = 'button';
		el.className = className;
		el.textContent = label;
		return el;
	}

	function storage( postId ) {
		const key = 'ttm-comments:' + postId;
		let state = {};
		try {
			state =
				JSON.parse( window.localStorage.getItem( key ) || '{}' ) || {};
		} catch {
			state = {};
		}
		return {
			get: ( name, fallback ) =>
				undefined === state[ name ] ? fallback : state[ name ],
			set: ( name, value ) => {
				state[ name ] = value;
				try {
					window.localStorage.setItem( key, JSON.stringify( state ) );
				} catch {}
			},
		};
	}

	function idOf( li ) {
		return parseInt( li.id.replace( 'comment-', '' ), 10 ) || 0;
	}

	function kids( li ) {
		const ol = li.querySelector( ':scope > ol' );
		return ol ? ol.querySelectorAll( 'li.comment' ).length : 0;
	}

	function init( section ) {
		const body = document.body.classList;
		let kind = 'discussion';
		if ( body.contains( 'ttm-comments-reflections' ) ) {
			kind = 'reflections';
		} else if ( body.contains( 'ttm-comments-notes' ) ) {
			kind = 'notes';
		}
		const match = document.body.className.match( /postid-(\d+)/ );
		const store = storage( match ? match[ 1 ] : '0' );
		const head = section.querySelector( '.ttm-comments__head' );
		const list = section.querySelector( '.ttm-comments__list' );
		const respond = section.querySelector( '#respond' );
		const open = section.querySelector( '.ttm-comments__open a' );
		const all = list ? [ ...list.querySelectorAll( 'li.comment' ) ] : [];

		section.classList.add( 'is-enhanced' );

		// Reader notes start closed on every visit, unless the reader came here for them.
		const wanted =
			/^#(comment-\d+|respond|comments)$/.test( window.location.hash ) ||
			/[?&](replytocom|unapproved)=/.test( window.location.search );
		let toggle = null;
		function setClosed( closed ) {
			section.classList.toggle( 'is-closed', closed );
			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', String( ! closed ) );
				toggle.textContent = closed
					? say( 'readNotes' )
					: say( 'close' );
			}
		}
		if ( 'notes' === kind && head ) {
			toggle = button( '', 'ttm-comments__toggle' );
			toggle.setAttribute( 'aria-controls', 'comments' );
			toggle.addEventListener( 'click', () =>
				setClosed( ! section.classList.contains( 'is-closed' ) )
			);
			head.appendChild( toggle );
			setClosed( ! wanted );
		}

		// The form: hidden until the button, a Reply link, or a return trip asks for it.
		function setWriting( writing ) {
			section.classList.toggle( 'is-writing', writing );
			if ( open ) {
				open.setAttribute( 'aria-expanded', String( writing ) );
			}
		}
		if ( open && respond ) {
			open.setAttribute( 'aria-controls', 'respond' );
			open.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				const cancel = document.getElementById(
					'cancel-comment-reply-link'
				);
				// Under a comment: bring the form back to the top instead of closing it.
				const replying = !! cancel && 'none' !== cancel.style.display;
				if ( replying && cancel ) {
					cancel.click();
				}
				const writing =
					replying || ! section.classList.contains( 'is-writing' );
				setWriting( writing );
				setClosed( false );
				if ( writing ) {
					const field = respond.querySelector( 'textarea' );
					if ( field ) {
						field.focus();
					}
				}
			} );
		}
		section.addEventListener( 'click', ( event ) => {
			const reply = event.target.closest( '.comment-reply-link' );
			if ( reply ) {
				setWriting( true );
				// comment-reply.js has just set the heading to the link's "Reply to …".
				const heading = document.getElementById( 'reply-title' );
				const name = reply
					.closest( 'li.comment' )
					?.querySelector( '.ttm-comment__name' );
				if ( heading && name && heading.firstChild ) {
					heading.firstChild.textContent = String(
						words.replyingTo || '%s'
					).replace( '%s', name.textContent.trim() );
				}
			} else if ( event.target.closest( '#cancel-comment-reply-link' ) ) {
				setWriting( false );
			}
		} );
		setWriting(
			/^#respond$/.test( window.location.hash ) ||
				/[?&]replytocom=/.test( window.location.search )
		);
		if ( 'notes' === kind && section.classList.contains( 'is-writing' ) ) {
			setClosed( false );
		}

		// New since the last visit.
		const latest = all.reduce(
			( max, li ) => Math.max( max, idOf( li ) ),
			0
		);
		const seen = store.get( 'seen', null );
		if ( null !== seen && 'reflections' !== kind ) {
			const fresh = all.filter( ( li ) => idOf( li ) > seen );
			fresh.forEach( ( li ) => {
				li.classList.add( 'is-new' );
				const meta = li.querySelector( '.ttm-comment__meta' );
				if ( meta ) {
					const tag = document.createElement( 'span' );
					tag.className = 'ttm-comment__new';
					tag.textContent = say( 'newTag' );
					meta.appendChild( tag );
				}
			} );
			const count = section.querySelector( '.ttm-comments__count' );
			if ( fresh.length && count ) {
				count.textContent += ' · ' + say( 'newSince', fresh.length );
			}
		}
		if ( latest ) {
			store.set( 'seen', latest );
		}

		// Reflections: the newest few, then "Show all".
		if ( 'reflections' === kind && list ) {
			const top = [ ...list.querySelectorAll( ':scope > li.comment' ) ];
			if ( top.length > REFLECTIONS_SHOWN ) {
				top.slice( REFLECTIONS_SHOWN ).forEach(
					( li ) => ( li.hidden = true )
				);
				const more = button(
					say( 'showAll', top.length ),
					'ttm-comments__more'
				);
				more.addEventListener( 'click', () => {
					top.forEach( ( li ) => ( li.hidden = false ) );
					more.remove();
				} );
				list.after( more );
			}
			return;
		}

		// Collapsible threads, remembered per post.
		let collapsed = store.get( 'collapsed', [] );
		const threads = all.filter( ( li ) => kids( li ) > 0 );
		function setCollapsed( li, value, remember ) {
			li.classList.toggle( 'is-collapsed', value );
			const id = idOf( li );
			collapsed = collapsed.filter( ( c ) => c !== id );
			if ( value ) {
				collapsed.push( id );
			}
			if ( remember ) {
				store.set( 'collapsed', collapsed );
			}
		}
		threads.forEach( ( li ) => {
			const comment = li.querySelector( ':scope > .ttm-comment' );
			if ( ! comment ) {
				return;
			}
			const name = comment.querySelector( '.ttm-comment__name' );
			const text = comment.querySelector( '.ttm-comment__body' );
			const excerpt = text ? text.textContent.trim().slice( 0, 60 ) : '';
			const fold = button( '', 'ttm-comment__fold' );
			fold.setAttribute( 'aria-expanded', 'false' );
			const who = document.createElement( 'b' );
			who.textContent = name ? name.textContent.trim() : '';
			const go = document.createElement( 'span' );
			go.className = 'ttm-comment__fold-go';
			go.textContent = say( 'show' );
			fold.append(
				who,
				' · “' +
					excerpt +
					( excerpt.length >= 60 ? '…' : '' ) +
					'” · ' +
					replies( kids( li ) ) +
					' ',
				go
			);
			fold.addEventListener( 'click', () =>
				setCollapsed( li, false, true )
			);
			const hide = button( say( 'collapse' ), 'ttm-comment__collapse' );
			hide.setAttribute( 'aria-expanded', 'true' );
			hide.addEventListener( 'click', () =>
				setCollapsed( li, true, true )
			);
			comment.appendChild( hide );
			li.insertBefore( fold, comment );
			if ( collapsed.includes( idOf( li ) ) ) {
				setCollapsed( li, true, false );
			}
		} );
		store.set(
			'collapsed',
			collapsed.filter( ( id ) =>
				threads.some( ( li ) => idOf( li ) === id )
			)
		);

		if ( all.length >= BUSY && threads.length && head ) {
			const tools = document.createElement( 'p' );
			tools.className = 'ttm-comments__tools';
			const collapseAll = button(
				say( 'collapseAll' ),
				'ttm-comments__tool'
			);
			const expandAll = button(
				say( 'expandAll' ),
				'ttm-comments__tool'
			);
			collapseAll.addEventListener( 'click', () =>
				threads.forEach( ( li ) => setCollapsed( li, true, true ) )
			);
			expandAll.addEventListener( 'click', () =>
				threads.forEach( ( li ) => setCollapsed( li, false, true ) )
			);
			tools.append( collapseAll, expandAll );
			head.after( tools );
		}
	}

	function ready() {
		document.querySelectorAll( '.ttm-comments' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
