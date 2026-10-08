/**
 * Motor Anime.js — desenho de traço em SVG.
 *
 * A matemática do dash é feita aqui, não pela API do Anime, para que o mesmo
 * código sirva à v3 (`anime( { targets } )`) e à v4 (`anime.animate( targets )`).
 */
( function () {
	'use strict';

	if ( ! window.wfan || ! window.anime ) {
		return;
	}

	var anime = window.anime;
	var util = window.wfan.util;

	function animate( targets, props ) {
		if ( typeof anime.animate === 'function' ) {
			return anime.animate( targets, props );
		}

		if ( typeof anime === 'function' ) {
			return anime( assign( { targets: targets }, props ) );
		}

		return null;
	}

	function strokes( el ) {
		var found = el.querySelectorAll( 'path, line, polyline, polygon, circle, rect, ellipse' );

		return [].slice.call( found ).filter( function ( node ) {
			return typeof node.getTotalLength === 'function';
		} );
	}

	function prime( node ) {
		var length = 0;

		try {
			length = node.getTotalLength();
		} catch ( e ) {
			return 0;
		}

		node.style.strokeDasharray = length + ' ' + length;
		node.style.strokeDashoffset = String( length );

		return length;
	}

	window.wfan.register( 'anime', {

		play: function ( el, spec, targets ) {
			var nodes = strokes( el );

			if ( ! nodes.length ) {
				// Sem traço para desenhar, cai no comportamento mais útil:
				// revelar o elemento e não fingir animação.
				util.reveal( el );
				return;
			}

			nodes.forEach( prime );

			var each = spec.stagger ? spec.stagger.each : 0;

			nodes.forEach( function ( node, index ) {
				animate( node, {
					strokeDashoffset: 0,
					duration: Math.max( 1, spec.duration ),
					delay: spec.delay + ( index * each ),
					easing: 'easeOutQuad',
					ease: 'outQuad'
				} );
			} );

			el.__wfanAnimeNodes = nodes;
			void targets;
		},

		reset: function ( el ) {
			( el.__wfanAnimeNodes || strokes( el ) ).forEach( prime );
		}
	} );

	function assign( target ) {
		[].slice.call( arguments, 1 ).forEach( function ( source ) {
			if ( ! source ) {
				return;
			}

			Object.keys( source ).forEach( function ( key ) {
				target[ key ] = source[ key ];
			} );
		} );

		return target;
	}
}() );
