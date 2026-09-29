/**
 * Leone Therapist Finder – instant filtering for [therapist_finder].
 *
 * Progressive enhancement: the server renders every card and a working GET form. This script
 * filters in the browser, shows live counts on every option, keeps the URL shareable, and
 * pushes analytics events to window.dataLayer (Google Tag Manager) when present.
 */
( function () {
	'use strict';

	const FACETS = [ 'location', 'service', 'issue', 'language' ];

	const normalise = ( text ) =>
		String( text || '' )
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.toLowerCase()
			.replace( /\s+/g, ' ' )
			.trim();

	const tokens = ( text ) => normalise( text ).split( ' ' ).filter( Boolean );

	// Minimal sprintf for the translated strings: %s, %d and positional %1$s / %2$d.
	const format = ( str, ...args ) => {
		let i = 0;
		return String( str ).replace( /%(?:(\d+)\$)?[sd]/g, ( match, pos ) => String( pos ? args[ pos - 1 ] : args[ i++ ] ) );
	};

	const el = ( tag, className, text ) => {
		const node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	};

	class Finder {
		constructor( root ) {
			this.root = root;
			const config = root.querySelector( 'script.ltf-config' );
			this.cfg = config ? JSON.parse( config.textContent || '{}' ) : {};
			this.t = this.cfg.i18n || {};
			this.form = root.querySelector( '.ltf-filters' );
			this.list = root.querySelector( '.ltf-results' );
			this.statusText = root.querySelector( '.ltf-status__text' );
			this.reset = root.querySelector( '.ltf-status__reset' );
			this.empty = root.querySelector( '.ltf-empty' );
			this.relax = root.querySelector( '[data-ltf-relax]' );
			this.search = root.querySelector( 'input[type="search"]' );
			this.expanded = new WeakSet();
			this.controls = {};

			FACETS.forEach( ( facet ) => {
				const name = this.cfg.param + facet;
				const radios = Array.from( root.querySelectorAll( `input[type="radio"][name="${ name }"]` ) );
				const select = root.querySelector( `select[name="${ name }"]` );
				if ( radios.length ) {
					this.controls[ facet ] = { type: 'radio', inputs: radios };
				} else if ( select ) {
					Array.from( select.options ).forEach( ( option ) => {
						option.dataset.label = option.textContent;
					} );
					this.controls[ facet ] = { type: 'select', el: select };
				}
			} );

			this.cards = Array.from( root.querySelectorAll( '[data-ltf-card]' ) ).map( ( node ) => ( {
				el: node,
				name: node.dataset.name || '',
				search: node.dataset.search || '',
				booking: JSON.parse( node.dataset.booking || '{}' ),
				facets: FACETS.reduce( ( acc, facet ) => {
					acc[ facet ] = new Set( ( node.dataset[ facet ] || '' ).split( ' ' ).filter( Boolean ) );
					return acc;
				}, {} ),
				chipList: node.querySelector( '.ltf-chips' ),
				chips: Array.from( node.querySelectorAll( '.ltf-chip-item' ) ),
				more: node.querySelector( '[data-ltf-more]' ),
				bookBox: node.querySelector( '[data-ltf-book]' ),
				locations: Array.from( node.querySelectorAll( '.ltf-loc' ) ),
			} ) );

			if ( this.cfg.order === 'random' ) {
				this.shuffle();
			}

			root.classList.add( 'ltf--js' );
			this.alignWide();
			this.bind();
			this.state = this.readState();
			// Phones fold secondary filters away; open them if one is already in use (e.g. a shared link).
			this.toggleFilters( !! ( this.state.language || this.state.q ) );
			this.update( false );
		}

		// width="wide"/"full": keep the finder centred on the screen even when the theme's content
		// column is off to one side.
		alignWide() {
			if ( ! this.root.classList.contains( 'ltf--wide' ) || ! this.root.parentElement ) {
				return;
			}
			this.root.style.setProperty( '--ltf-wide-shift', '0px' );
			const parent = this.root.parentElement.getBoundingClientRect();
			const shift = document.documentElement.clientWidth / 2 - ( parent.left + parent.width / 2 );
			this.root.style.setProperty( '--ltf-wide-shift', Math.round( shift ) + 'px' );
		}

		toggleFilters( open ) {
			const button = this.root.querySelector( '[data-ltf-toggle-filters]' );
			if ( ! button || ! this.form ) {
				return;
			}
			this.form.classList.toggle( 'is-expanded', open );
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			button.textContent = open ? button.dataset.fewer : button.dataset.more;
		}

		bind() {
			let searchTimer;
			let trackTimer;

			const changed = () => {
				this.state = this.readState();
				this.update( true );
				clearTimeout( trackTimer );
				trackTimer = setTimeout( () => this.track( 'filter', Object.assign( { results: this.visible }, this.state ) ), 1000 );
			};

			if ( this.form ) {
				this.form.addEventListener( 'change', ( event ) => {
					if ( event.target !== this.search ) {
						changed();
					}
				} );
				this.form.addEventListener( 'submit', ( event ) => {
					event.preventDefault();
					changed();
				} );
			}

			if ( this.search ) {
				this.search.addEventListener( 'input', () => {
					clearTimeout( searchTimer );
					searchTimer = setTimeout( changed, 180 );
				} );
			}

			this.root.addEventListener( 'click', ( event ) => {
				if ( event.target.closest( '[data-ltf-toggle-filters]' ) ) {
					this.toggleFilters( ! this.form.classList.contains( 'is-expanded' ) );
					return;
				}

				const chip = event.target.closest( '[data-ltf-issue]' );
				if ( chip && this.controls.issue ) {
					event.preventDefault();
					this.setValue( 'issue', chip.dataset.ltfIssue );
					changed();
					this.statusText.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
					return;
				}

				const more = event.target.closest( '[data-ltf-more]' );
				if ( more ) {
					event.preventDefault();
					const card = this.cards.find( ( c ) => c.el.contains( more ) );
					if ( this.expanded.has( card ) ) {
						this.expanded.delete( card );
					} else {
						this.expanded.add( card );
					}
					this.updateCard( card );
					return;
				}

				const clear = event.target.closest( '[data-ltf-clear]' );
				if ( clear ) {
					this.setValue( clear.dataset.ltfClear, '' );
					changed();
					return;
				}

				if ( event.target.closest( '.ltf-status__reset' ) || event.target.closest( '[data-ltf-relax] a' ) ) {
					event.preventDefault();
					FACETS.concat( 'q' ).forEach( ( facet ) => this.setValue( facet, '' ) );
					changed();
					return;
				}

				const book = event.target.closest( '[data-ltf-book-link]' );
				if ( book ) {
					const card = this.cards.find( ( c ) => c.el.contains( book ) );
					this.track( 'book', { therapist: card ? card.name : '', location: book.dataset.ltfBookLink } );
				}
			} );

			let resizeTimer;
			window.addEventListener( 'resize', () => {
				clearTimeout( resizeTimer );
				resizeTimer = setTimeout( () => this.alignWide(), 150 );
			} );

			// Close any open "Book a session" menu when clicking elsewhere or pressing Escape.
			document.addEventListener( 'click', ( event ) => {
				this.root.querySelectorAll( 'details.ltf-book-menu[open]' ).forEach( ( menu ) => {
					if ( ! menu.contains( event.target ) ) {
						menu.open = false;
					}
				} );
			} );
			this.root.addEventListener( 'keydown', ( event ) => {
				// "+N more" is a link (profile fallback without JS) acting as a button: support Space.
				if ( event.key === ' ' && event.target.matches( '[data-ltf-more]' ) ) {
					event.preventDefault();
					event.target.click();
					return;
				}
				if ( event.key !== 'Escape' ) {
					return;
				}
				const menu = event.target.closest( 'details.ltf-book-menu[open]' );
				if ( menu ) {
					menu.open = false;
					menu.querySelector( 'summary' ).focus();
				}
			} );
		}

		readState() {
			const state = { q: this.search ? this.search.value : '' };
			FACETS.forEach( ( facet ) => {
				const control = this.controls[ facet ];
				if ( ! control ) {
					state[ facet ] = '';
				} else if ( control.type === 'radio' ) {
					const checked = control.inputs.find( ( input ) => input.checked );
					state[ facet ] = checked ? checked.value : '';
				} else {
					state[ facet ] = control.el.value;
				}
			} );
			return state;
		}

		setValue( facet, value ) {
			if ( facet === 'q' ) {
				if ( this.search ) {
					this.search.value = value;
				}
				return;
			}
			const control = this.controls[ facet ];
			if ( ! control ) {
				return;
			}
			if ( control.type === 'radio' ) {
				control.inputs.forEach( ( input ) => {
					input.checked = input.value === value;
				} );
			} else {
				control.el.value = value;
			}
		}

		// Mirrors Finder::matches() in PHP.
		matches( card, state ) {
			for ( const facet of FACETS ) {
				if ( state[ facet ] && ! card.facets[ facet ].has( state[ facet ] ) ) {
					return false;
				}
			}
			return tokens( state.q ).every( ( token ) => card.search.includes( token ) );
		}

		countWith( facet, value ) {
			const state = Object.assign( {}, this.state, { [ facet ]: value } );
			return this.cards.filter( ( card ) => this.matches( card, state ) ).length;
		}

		update( userChange ) {
			this.visible = 0;
			this.cards.forEach( ( card ) => {
				const match = this.matches( card, this.state );
				card.el.hidden = ! match;
				if ( match ) {
					this.visible++;
				}
				this.updateCard( card );
			} );

			this.updateCounts();
			this.updateStatus();
			this.updateEmpty();
			if ( userChange ) {
				this.syncUrl();
			}
		}

		updateCounts() {
			Object.keys( this.controls ).forEach( ( facet ) => {
				const control = this.controls[ facet ];
				if ( control.type === 'radio' ) {
					control.inputs.forEach( ( input ) => {
						const count = this.countWith( facet, input.value );
						const badge = input.parentNode.querySelector( '.ltf-pill__count' );
						if ( badge ) {
							badge.textContent = count;
						}
						input.disabled = count === 0 && ! input.checked;
						input.closest( '.ltf-pill' ).classList.toggle( 'is-disabled', input.disabled );
					} );
				} else {
					Array.from( control.el.options ).forEach( ( option ) => {
						if ( ! option.value ) {
							return;
						}
						const count = this.countWith( facet, option.value );
						option.textContent = `${ option.dataset.label } (${ count })`;
						option.disabled = count === 0 && ! option.selected;
					} );
				}
			} );
		}

		updateCard( card ) {
			const active = this.state.issue;
			const limit = this.cfg.issueLimit || 4;
			const expanded = this.expanded.has( card );

			if ( card.chipList && card.chips.length ) {
				// Matched issue first, so it is obvious why this therapist is listed.
				const ordered = active
					? card.chips.filter( ( li ) => li.dataset.issue === active ).concat( card.chips.filter( ( li ) => li.dataset.issue !== active ) )
					: card.chips;
				ordered.forEach( ( li, index ) => {
					li.hidden = ! expanded && index >= limit;
					li.classList.toggle( 'is-match', !! active && li.dataset.issue === active );
					card.chipList.appendChild( li );
				} );
			}

			if ( card.more ) {
				const extra = Math.max( 0, card.chips.length - limit );
				card.more.hidden = extra === 0;
				card.more.textContent = expanded ? this.t.fewerIssues : format( this.t.moreIssues, extra );
				card.more.setAttribute( 'role', 'button' );
				card.more.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
			}

			const location = this.state.location || this.cfg.lockedLocation || '';
			card.locations.forEach( ( li ) => li.classList.toggle( 'is-match', !! location && li.dataset.location === location ) );

			this.renderBooking( card, location );
		}

		// Mirrors Finder::booking_html() in PHP.
		renderBooking( card, location ) {
			if ( ! card.bookBox ) {
				return;
			}
			const service = this.state.service || this.cfg.lockedService || '';
			const type = ( this.cfg.appointmentTypes || {} )[ service ] || '';
			let entries = Object.entries( card.booking );
			if ( location && card.booking[ location ] ) {
				entries = [ [ location, card.booking[ location ] ] ];
			}

			const key = entries.map( ( entry ) => entry[ 0 ] ).join( ',' ) + '|' + type;
			if ( card.bookKey === key ) {
				return;
			}
			card.bookKey = key;

			const withType = ( url ) => {
				if ( ! type ) {
					return url;
				}
				const parsed = new URL( url, window.location.href );
				parsed.searchParams.set( 'appointmentType', type );
				return parsed.toString();
			};

			const box = card.bookBox;
			box.textContent = '';

			if ( ! entries.length ) {
				const link = el( 'a', 'ltf-btn ltf-btn--primary', this.t.enquire );
				link.href = this.cfg.contactUrl || '#';
				box.appendChild( link );
				return;
			}

			if ( entries.length === 1 ) {
				const [ slug, entry ] = entries[ 0 ];
				const link = el( 'a', 'ltf-btn ltf-btn--primary', entry.online ? this.t.bookOnline : format( this.t.bookIn, entry.label ) );
				link.href = withType( entry.url );
				link.dataset.ltfBookLink = slug;
				box.appendChild( link );
				return;
			}

			const menu = el( 'details', 'ltf-book-menu' );
			menu.appendChild( el( 'summary', 'ltf-btn ltf-btn--primary', this.t.bookMenu ) );
			const list = el( 'ul', 'ltf-book-menu__list' );
			entries.forEach( ( [ slug, entry ] ) => {
				const link = el( 'a', 'ltf-book-menu__link' );
				link.href = withType( entry.url );
				link.dataset.ltfBookLink = slug;
				const icon = card.el.querySelector( `.ltf-loc[data-location="${ slug }"] svg` );
				if ( icon ) {
					link.appendChild( icon.cloneNode( true ) );
				}
				link.appendChild( el( 'span', '', entry.online ? this.t.menuOnline : format( this.t.menuClinic, entry.label ) ) );
				const item = el( 'li' );
				item.appendChild( link );
				list.appendChild( item );
			} );
			menu.appendChild( list );
			box.appendChild( menu );
		}

		updateStatus() {
			const total = this.cards.length;
			this.statusText.textContent =
				this.visible === total ? format( this.t.showingAll, total ) : format( this.t.showingSome, this.visible, total );

			const filtered = FACETS.concat( 'q' ).some( ( facet ) => this.state[ facet ] );
			if ( this.reset ) {
				this.reset.hidden = ! filtered;
			}
		}

		// When nothing matches, offer one-click ways out, each showing how many it would bring back.
		updateEmpty() {
			if ( ! this.empty ) {
				return;
			}
			this.empty.hidden = this.visible > 0;
			if ( this.visible > 0 || ! this.relax ) {
				return;
			}

			const buttons = [];
			FACETS.concat( 'q' ).forEach( ( facet ) => {
				const value = this.state[ facet ];
				const count = value ? this.countWith( facet, '' ) : 0;
				if ( ! count ) {
					return;
				}
				const label = facet === 'q' ? format( this.t.clearSearch, value, count ) : format( this.t.removeFilter, this.labelFor( facet, value ), count );
				const button = el( 'button', 'ltf-btn ltf-btn--ghost', label );
				button.type = 'button';
				button.dataset.ltfClear = facet;
				buttons.push( button );
			} );

			if ( buttons.length ) {
				this.relax.textContent = '';
				buttons.forEach( ( button ) => this.relax.appendChild( button ) );
			}
		}

		labelFor( facet, value ) {
			const control = this.controls[ facet ];
			if ( ! control ) {
				return value;
			}
			if ( control.type === 'radio' ) {
				const input = control.inputs.find( ( i ) => i.value === value );
				const text = input && input.closest( '.ltf-pill' ).querySelector( '.ltf-pill__text' );
				return text ? text.textContent : value;
			}
			const option = Array.from( control.el.options ).find( ( o ) => o.value === value );
			return option ? option.dataset.label : value;
		}

		// Keep filters in the address bar so results can be shared and survive a reload.
		syncUrl() {
			if ( ! this.cfg.urlSync || ! window.history.replaceState ) {
				return;
			}
			const url = new URL( window.location.href );
			const defaults = this.cfg.defaults || {};
			FACETS.concat( 'q' ).forEach( ( facet ) => {
				if ( facet === 'q' ? ! this.search : ! this.controls[ facet ] ) {
					return;
				}
				const key = this.cfg.param + facet;
				const value = this.state[ facet ];
				if ( value === ( defaults[ facet ] || '' ) ) {
					url.searchParams.delete( key );
				} else {
					url.searchParams.set( key, value ); // May be empty: "any", overriding a default.
				}
			} );
			window.history.replaceState( window.history.state, '', url );
		}

		shuffle() {
			for ( let i = this.cards.length - 1; i > 0; i-- ) {
				const j = Math.floor( Math.random() * ( i + 1 ) );
				[ this.cards[ i ], this.cards[ j ] ] = [ this.cards[ j ], this.cards[ i ] ];
			}
			this.cards.forEach( ( card ) => this.list.appendChild( card.el ) );
		}

		track( action, data ) {
			this.root.dispatchEvent( new CustomEvent( `ltf:${ action }`, { bubbles: true, detail: data } ) );
			if ( Array.isArray( window.dataLayer ) ) {
				window.dataLayer.push( Object.assign( { event: `therapist_finder_${ action }` }, data ) );
			}
		}
	}

	const start = () => document.querySelectorAll( '[data-ltf]' ).forEach( ( root ) => new Finder( root ) );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
