/**
 * Leone Therapist Finder – admin helpers.
 */
( function () {
	'use strict';

	// Therapist edit screen: typing a calendar ID ticks that location.
	document.addEventListener( 'input', ( event ) => {
		if ( ! event.target.matches( '[data-ltf-loc-ref]' ) || ! event.target.value.trim() ) {
			return;
		}
		const toggle = event.target.closest( 'tr' ).querySelector( '[data-ltf-loc-toggle]' );
		if ( toggle ) {
			toggle.checked = true;
		}
	} );

	// Settings screen: shortcode builder.
	const builder = document.getElementById( 'ltf-builder' );
	if ( ! builder ) {
		return;
	}

	const ALL = [ 'location', 'service', 'issue', 'language', 'search' ];
	const output = builder.querySelector( '[data-ltf-output]' );
	const order = builder.querySelector( '[data-ltf-order]' );
	const fixed = Array.from( builder.querySelectorAll( '[data-ltf-fix]' ) );
	const checks = Array.from( builder.querySelectorAll( '[data-ltf-filter]' ) );

	const build = () => {
		const attrs = [];
		const lockedFacets = [];

		fixed.forEach( ( select ) => {
			if ( select.value ) {
				attrs.push( `${ select.dataset.ltfFix }="${ select.value }"` );
				lockedFacets.push( select.dataset.ltfFix );
			}
		} );

		// A fixed facet can't also be a visitor filter (that would make it a starting value instead).
		checks.forEach( ( box ) => {
			const locked = lockedFacets.includes( box.dataset.ltfFilter );
			box.disabled = locked;
			box.closest( 'label' ).classList.toggle( 'is-disabled', locked );
		} );

		const filters = checks.filter( ( box ) => box.checked && ! box.disabled ).map( ( box ) => box.dataset.ltfFilter );
		const defaultFilters = ALL.filter( ( key ) => ! lockedFacets.includes( key ) );
		if ( filters.join( ',' ) !== defaultFilters.join( ',' ) ) {
			attrs.push( `filters="${ filters.join( ',' ) }"` );
		}

		if ( order.value !== 'default' ) {
			attrs.push( `order="${ order.value }"` );
		}

		const width = builder.querySelector( '[data-ltf-width]' );
		if ( width && width.value ) {
			attrs.push( `width="${ width.value }"` );
		}

		output.value = `[therapist_finder${ attrs.length ? ' ' + attrs.join( ' ' ) : '' }]`;
	};

	builder.addEventListener( 'change', build );
	builder.querySelector( '[data-ltf-copy]' ).addEventListener( 'click', ( event ) => {
		const button = event.currentTarget;
		const label = button.textContent;
		const done = () => {
			button.textContent = '✓';
			setTimeout( () => ( button.textContent = label ), 1500 );
		};
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( output.value ).then( done );
		} else {
			output.select();
			document.execCommand( 'copy' );
			done();
		}
	} );
	build();
} )();
