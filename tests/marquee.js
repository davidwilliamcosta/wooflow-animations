/**
 * Teste do marquee: `node tests/marquee.js`.
 *
 * Carrega o engine-marquee.js de verdade num DOM de mentira e dirige os
 * quadros um a um. É o único lugar onde se confere o que o HTML não mostra:
 * ONDE a esteira está depois de N quadros, quantas cópias ela precisa, se o
 * laço PARA quando não há mais o que mover, e o que o arrasto faz com o clique.
 *
 * O que este arquivo guarda (CLAUDE.md, regra 31):
 *  - o sentido do movimento mora no `pos()`, não no deslocamento — arrasto,
 *    inércia e rolagem somam no mesmo eixo qualquer que seja a direção;
 *  - o `requestAnimationFrame` se apaga sozinho quando o elemento sai da tela,
 *    quando está congelado e quando o amortecimento da rolagem converge;
 *  - o clique que sucede um arrasto não pode abrir o link;
 *  - a cópia da esteira não repete `id`, não é lida duas vezes e não herda o
 *    pré-esconde do filho.
 *
 * @package WFAN
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const ENGINE = process.argv[ 2 ] || path.join( __dirname, '..', 'assets', 'js', 'frontend', 'engine-marquee.js' );

let falhas = 0;
let total = 0;

function ok( condicao, rotulo, detalhe ) {
	total++;

	if ( condicao ) {
		console.log( '  ok   ' + rotulo );
		return;
	}

	falhas++;
	console.log( '  FALHA ' + rotulo + ( detalhe ? ' — ' + detalhe : '' ) );
}

function perto( a, b, folga ) {
	return Math.abs( a - b ) <= ( folga === undefined ? 0.6 : folga );
}

/** Um nó mínimo: o bastante para o motor montar, clonar e desmontar. */
function criar( env, tipo, nome ) {
	const classes = new Set( nome ? nome.split( ' ' ) : [] );
	const attrs = {};
	const ouvintes = {};

	const no = {
		nodeType: tipo,
		nodeValue: '',
		parentNode: null,
		childNodes: [],
		style: {
			props: {},
			setProperty: function ( k, v ) { this.props[ k ] = v; },
			removeProperty: function ( k ) { delete this.props[ k ]; }
		},
		classList: {
			add: function () { [].slice.call( arguments ).forEach( ( c ) => classes.add( c ) ); },
			remove: function () { [].slice.call( arguments ).forEach( ( c ) => classes.delete( c ) ); },
			contains: ( c ) => classes.has( c )
		},
		setAttribute: ( k, v ) => { attrs[ k ] = String( v ); },
		getAttribute: ( k ) => ( k in attrs ? attrs[ k ] : null ),
		removeAttribute: ( k ) => { delete attrs[ k ]; },
		hasAttribute: ( k ) => k in attrs,
		addEventListener: ( t, fn ) => { ( ouvintes[ t ] = ouvintes[ t ] || [] ).push( fn ); },
		removeEventListener: ( t, fn ) => {
			ouvintes[ t ] = ( ouvintes[ t ] || [] ).filter( ( f ) => f !== fn );
		},
		setPointerCapture: function () { env.captura++; },
		get children() { return this.childNodes.filter( ( n ) => 1 === n.nodeType ); },
		get firstChild() { return this.childNodes[ 0 ] || null; },
		get className() { return [ ...classes ].join( ' ' ); },
		set className( v ) { classes.clear(); String( v ).split( ' ' ).filter( Boolean ).forEach( ( c ) => classes.add( c ) ); },
		get clientWidth() { return env.vitrine; },
		appendChild: function ( n ) {
			if ( n.parentNode ) { n.parentNode.removeChild( n ); }
			n.parentNode = this;
			this.childNodes.push( n );
			return n;
		},
		insertBefore: function ( n, ref ) {
			if ( n.parentNode ) { n.parentNode.removeChild( n ); }
			n.parentNode = this;
			const i = this.childNodes.indexOf( ref );
			this.childNodes.splice( i < 0 ? this.childNodes.length : i, 0, n );
			return n;
		},
		removeChild: function ( n ) {
			this.childNodes.splice( this.childNodes.indexOf( n ), 1 );
			n.parentNode = null;
			return n;
		},
		cloneNode: function () {
			const c = criar( env, tipo, [ ...classes ].join( ' ' ) );
			Object.keys( attrs ).forEach( ( k ) => c.setAttribute( k, attrs[ k ] ) );
			c.nodeValue = this.nodeValue;
			this.childNodes.forEach( ( f ) => c.appendChild( f.cloneNode( true ) ) );
			return c;
		},
		querySelectorAll: function ( sel ) {
			const achados = [];
			( function andar( n ) {
				n.childNodes.forEach( ( f ) => {
					if ( 1 === f.nodeType ) {
						if ( ( '[id]' === sel && f.hasAttribute( 'id' ) ) || ( '.wfan-pending' === sel && f.classList.contains( 'wfan-pending' ) ) ) {
							achados.push( f );
						}
						andar( f );
					}
				} );
			}( this ) );
			return achados;
		},
		getBoundingClientRect: function () {
			if ( classes.has( 'wfan-mq-group' ) ) {
				return { width: env.largura, top: 0, height: 0 };
			}
			return env.rect;
		},
		dispatch: function ( t, ev ) {
			const e = Object.assign( {
				type: t,
				pointerType: 'mouse',
				pointerId: 1,
				button: 0,
				timeStamp: env.agora,
				prevented: false,
				stopped: false,
				preventDefault: function () { this.prevented = true; },
				stopPropagation: function () { this.stopped = true; }
			}, ev || {} );
			( ouvintes[ t ] || [] ).forEach( ( fn ) => fn( e ) );
			return e;
		}
	};

	return no;
}

/**
 * Sobe o motor sobre um widget com `itens` filhos.
 *
 * @param {Object} cfg      Bloco `marquee` já normalizado, como o core.js o entrega.
 * @param {Object} opcoes   vitrine, largura (do grupo), observador (falso = sem IntersectionObserver).
 */
function montar( cfg, opcoes ) {
	opcoes = Object.assign( { vitrine: 1000, largura: 300, observador: false }, opcoes || {} );

	const env = {
		vitrine: opcoes.vitrine,
		largura: opcoes.largura,
		rect: { top: 300, height: 100, width: opcoes.vitrine },
		agora: 0,
		captura: 0,
		fila: [],
		proximo: 1
	};

	const janela = criar( env, 9, '' );
	const aoVivo = new Map();
	let engine = null;

	const sandbox = {
		console: console,
		Math: Math,
		window: janela,
		document: { createElement: () => criar( env, 1, '' ), hidden: false, documentElement: { clientHeight: 800 } }
	};

	janela.innerHeight = 800;
	janela.wfan = { register: ( n, e ) => { engine = e; } };
	janela.requestAnimationFrame = ( fn ) => {
		const id = env.proximo++;
		aoVivo.set( id, fn );
		env.fila.push( id );
		return id;
	};
	janela.cancelAnimationFrame = ( id ) => { aoVivo.delete( id ); };
	env.timers = [];
	janela.setTimeout = ( fn ) => { env.timers.push( fn ); return env.timers.length; };

	if ( opcoes.observador ) {
		env.ios = [];
		janela.IntersectionObserver = function ( fn ) {
			env.ios.push( fn );
			this.observe = function () {};
			this.disconnect = function () {};
		};
		sandbox.IntersectionObserver = janela.IntersectionObserver;
	}

	vm.createContext( sandbox );
	vm.runInContext( fs.readFileSync( ENGINE, 'utf8' ), sandbox, { filename: 'engine-marquee.js' } );

	// Um widget do Elementor: elemento > .elementor-widget-container > itens.
	const el = criar( env, 1, 'elementor-element elementor-widget' );
	const miolo = criar( env, 1, 'elementor-widget-container' );
	const item = criar( env, 1, 'elementor-heading-title' );
	const texto = criar( env, 3, '' );
	texto.nodeValue = 'Marquee';
	item.appendChild( texto );
	item.setAttribute( 'id', 'titulo' );
	item.classList.add( 'wfan-pending' );
	miolo.appendChild( item );
	el.appendChild( miolo );

	env.el = el;
	env.miolo = miolo;
	env.janela = janela;
	env.spec = { marquee: Object.assign( {
		mode: 'auto', dir: -1, speed: 100, force: 1, gap: 48,
		edge: true, slow: false, freeze: false, drag: false
	}, cfg ) };

	env.bind = () => engine.bind( el, env.spec );
	env.reset = () => engine.reset( el, env.spec );
	env.track = () => miolo.childNodes.find( ( n ) => n.classList.contains( 'wfan-mq-track' ) );

	// Roda `n` quadros de `ms` cada. Devolve quantos realmente existiam.
	env.quadros = ( n, ms ) => {
		let rodados = 0;

		for ( let i = 0; i < n; i++ ) {
			const id = env.fila.shift();

			if ( undefined === id ) {
				break;
			}

			const fn = aoVivo.get( id );
			aoVivo.delete( id );

			if ( ! fn ) {
				i--;
				continue;
			}

			env.agora += ms || 16;
			fn( env.agora );
			rodados++;
		}

		return rodados;
	};

	// Espera o laço se apagar (ou estoura o limite). Devolve se apagou.
	env.ate_parar = ( limite ) => {
		for ( let i = 0; i < ( limite || 600 ); i++ ) {
			if ( ! env.quadros( 1, 16 ) ) {
				return true;
			}
		}
		return false;
	};

	env.x = () => {
		const m = /translate3d\((-?[\d.]+)px/.exec( env.track().style.transform || '' );
		return m ? parseFloat( m[ 1 ] ) : NaN;
	};

	return env;
}

console.log( 'Montagem' );
{
	const t = montar( {} );
	t.bind();
	const track = t.track();

	ok( !! track, 'o conteúdo ganha uma esteira dentro do miolo do widget' );
	ok( t.miolo.classList.contains( 'wfan-mq' ), 'o miolo recebe a classe que o CSS usa para cortar' );
	ok( t.miolo.style.props[ '--wfan-mq-gap' ] === '48px', 'o espaço entre repetições vai em custom property' );

	const grupos = track.childNodes;

	// vitrine 1000, grupo 300 → ceil(1000/300)+1 = 5 grupos: o original e 4 cópias
	ok( 5 === grupos.length, 'borda a borda: cópias até cobrir a vitrine mais uma volta', 'grupos=' + grupos.length );

	const copia = grupos[ 1 ];
	ok( 'true' === copia.getAttribute( 'aria-hidden' ), 'a cópia sai da leitura de tela' );
	ok( copia.hasAttribute( 'inert' ), 'a cópia sai da ordem de tabulação' );
	ok( ! copia.querySelectorAll( '[id]' ).length, 'a cópia não repete id' );
	ok( ! copia.querySelectorAll( '.wfan-pending' ).length, 'a cópia não herda o pré-esconde do filho' );
	ok( grupos[ 0 ].querySelectorAll( '[id]' ).length === 1, 'o original mantém o id' );

	t.reset();
	ok( ! t.track(), 'reset desmonta a esteira' );
	ok( t.miolo.childNodes.length === 1 && t.miolo.childNodes[ 0 ].getAttribute( 'id' ) === 'titulo', 'reset devolve o filho original ao lugar' );
	ok( ! t.miolo.classList.contains( 'wfan-mq' ), 'reset tira a classe' );

	t.bind();
	t.bind();
	ok( t.miolo.childNodes.length === 1, 'montar duas vezes não aninha esteira (o ▶ Testar chama de novo)' );
}

console.log( 'Sem borda a borda' );
{
	const t = montar( { edge: false } );
	t.bind();
	ok( 1 === t.track().childNodes.length, 'uma cópia só: o texto atravessa a vitrine uma vez' );
	ok( perto( t.x(), 0 ), 'o marquee automático abre com o texto dentro da vitrine, não esperando fora', 'x=' + t.x() );
}

console.log( 'Automático' );
{
	const t = montar( { speed: 100 } );
	t.bind();
	t.quadros( 1, 16 ); // primeiro quadro: dt de fábrica
	const x0 = t.x();
	t.quadros( 62, 16 ); // ≈ 1 s
	const andou = x0 - t.x();
	ok( andou > 90 && andou < 110, 'anda ~100 px em 1 s para a esquerda', 'andou=' + andou );
}
{
	// 4 s a 100 px/s = 400 px; a volta é o grupo (300) → 400 mod 300 = 100
	const t = montar( { speed: 100 } );
	t.bind();
	t.quadros( 1, 16 );
	for ( let i = 0; i < 4 * 62; i++ ) { t.quadros( 1, 16 ); }
	const x = t.x();
	ok( x <= 0 && x > -300, 'a esteira dá a volta sem sair do intervalo (-W, 0]', 'x=' + x );
}
{
	const t = montar( { speed: 100, dir: 1 } );
	t.bind();
	t.quadros( 1, 16 );
	const x0 = t.x();
	t.quadros( 62, 16 );
	ok( t.x() - x0 > 90, 'direção invertida anda para a direita', 'delta=' + ( t.x() - x0 ) );
	ok( t.x() <= 0 && t.x() >= -300, 'e continua dentro do intervalo' );
}

console.log( 'Congelado' );
{
	const t = montar( { freeze: true } );
	t.bind();
	t.quadros( 3, 16 );
	const x0 = t.x();
	ok( t.ate_parar(), 'congelado, o laço se apaga: nenhum quadro eterno' );
	ok( perto( t.x(), x0 ), 'e a esteira não se mexe' );
}

console.log( 'Desacelerar no hover' );
{
	const t = montar( { slow: true, speed: 100 } );
	t.bind();
	t.quadros( 1, 16 );
	t.miolo.dispatch( 'pointerenter', { pointerType: 'mouse' } );
	t.quadros( 90, 16 ); // deixa a velocidade assentar
	const x0 = t.x();
	t.quadros( 62, 16 );
	const andou = Math.abs( t.x() - x0 );
	ok( andou > 15 && andou < 26, 'com o mouse em cima anda a ~20% da velocidade', 'andou=' + andou );

	t.miolo.dispatch( 'pointerleave', {} );
	t.quadros( 120, 16 );
	const x1 = t.x();
	t.quadros( 62, 16 );
	ok( Math.abs( t.x() - x1 ) > 90 || Math.abs( t.x() - x1 ) > 270, 'ao sair, volta à velocidade cheia', 'delta=' + ( t.x() - x1 ) );
}
{
	const t = montar( { slow: true, speed: 100 } );
	t.bind();
	t.quadros( 1, 16 );
	t.miolo.dispatch( 'pointerenter', { pointerType: 'touch' } );
	t.quadros( 90, 16 );
	const x0 = t.x();
	t.quadros( 62, 16 );
	ok( Math.abs( t.x() - x0 ) > 90, 'o toque no celular não deixa o marquee preso na velocidade lenta' );
}

console.log( 'Fora da tela' );
{
	const t = montar( {}, { observador: true } );
	t.bind();
	t.quadros( 3, 16 );
	t.ios[ 0 ]( [ { isIntersecting: false } ] );
	t.quadros( 2, 16 );
	ok( t.ate_parar(), 'fora da tela o laço se apaga: não se gasta quadro com o que não se vê' );
	t.ios[ 0 ]( [ { isIntersecting: true } ] );
	ok( t.quadros( 1, 16 ) === 1, 'voltando à tela, o laço acorda' );
}

console.log( 'Pela rolagem' );
{
	// Elemento com o centro no centro da tela (800/2 = 400): top = 350, altura 100.
	const t = montar( { mode: 'scroll', force: 1 } );
	t.rect = t.rect;
	t.rect.top = 350;
	t.bind();
	t.quadros( 1, 16 );
	ok( perto( t.x(), 0 ), 'centro no centro da tela: a esteira está na origem', 'x=' + t.x() );

	// Rolou 200 px para baixo: o elemento subiu 200.
	t.rect.top = 150;
	t.janela.dispatch( 'scroll', {} );
	ok( t.ate_parar(), 'o amortecimento converge e o laço se apaga' );
	ok( perto( t.x(), -200, 1 ), 'rolou 200 px: a esteira andou 200 px para a esquerda', 'x=' + t.x() );

	t.rect.top = 550;
	t.janela.dispatch( 'scroll', {} );
	t.ate_parar();
	// u = -200 → mod 300 = 100 → pos = -100
	ok( perto( t.x(), -100, 1 ), 'rolando de volta, a esteira volta junto', 'x=' + t.x() );
}
{
	const t = montar( { mode: 'scroll', force: 2 } );
	t.rect.top = 150;
	t.bind();
	t.ate_parar();
	// 200 px de rolagem × 2 = 400 → mod 300 = 100 → pos = -100
	ok( perto( t.x(), -100, 1 ), 'a intensidade multiplica o percurso', 'x=' + t.x() );
}
{
	// O primeiro quadro parte do ponto certo: não varre a esteira desde o zero.
	const t = montar( { mode: 'scroll', force: 1 } );
	t.rect.top = 150; // u alvo = 200
	t.bind();
	t.quadros( 1, 16 );
	ok( perto( t.x(), -200, 1 ), 'abre direto na posição da rolagem, sem varrer desde o zero', 'x=' + t.x() );
}
{
	// Sem borda a borda: cruza a vitrine uma vez, de ponta a ponta.
	const t = montar( { mode: 'scroll', edge: false, dir: -1 } );
	t.rect.top = 800; // elemento aparecendo no pé da tela → p = 0
	t.bind();
	t.ate_parar();
	ok( perto( t.x(), 1000, 1 ), 'elemento entrando pelo pé: o texto está à direita da vitrine', 'x=' + t.x() );

	t.rect.top = -100; // elemento saiu pelo topo → p = 1
	t.janela.dispatch( 'scroll', {} );
	t.ate_parar();
	ok( t.x() < -290, 'elemento saindo pelo topo: o texto já cruzou e saiu pela esquerda', 'x=' + t.x() );
}

console.log( 'Arrastar' );
{
	const t = montar( { drag: true, freeze: true, dir: -1 } );
	t.bind();
	t.quadros( 1, 16 );
	ok( t.miolo.classList.contains( 'wfan-mq--drag' ), 'arrastável: a classe do cursor entra' );
	const x0 = t.x();

	t.agora = 1000;
	t.miolo.dispatch( 'pointerdown', { clientX: 500, timeStamp: 1000 } );
	t.miolo.dispatch( 'pointermove', { clientX: 502, timeStamp: 1016 } );
	ok( 0 === t.captura && perto( t.x(), x0 ), 'abaixo do limiar ainda é um clique: sem captura do ponteiro', 'captura=' + t.captura );

	t.miolo.dispatch( 'pointermove', { clientX: 540, timeStamp: 1032 } );
	ok( 1 === t.captura, 'passou do limiar: o ponteiro é capturado' );
	t.miolo.dispatch( 'pointermove', { clientX: 600, timeStamp: 1048 } );
	// arrastou 100 px (de 500 a 600; o primeiro movimento de 2 px já foi consumido pelo limiar? não: lastX só anda depois)
	// 100 px para a direita; a esteira é cíclica (volta de 300), então -200 é o mesmo lugar.
	const volta = ( ( ( t.x() - x0 ) % 300 ) + 300 ) % 300;
	ok( perto( volta, 100, 1 ), 'arrastar 100 px para a direita leva a esteira 100 px para a direita', 'volta=' + volta );

	t.miolo.dispatch( 'pointerup', { clientX: 600, timeStamp: 1400 } );
	const clique = t.miolo.dispatch( 'click', {} );
	ok( clique.prevented && clique.stopped, 'o clique logo após o arrasto é engolido: não abre o link' );
	t.timers.forEach( ( fn ) => fn() );
	const depois = t.miolo.dispatch( 'click', {} );
	ok( ! depois.prevented, 'e só esse: o clique seguinte passa' );
}
{
	const t = montar( { drag: true, freeze: true } );
	t.bind();
	t.miolo.dispatch( 'pointerdown', { clientX: 500, timeStamp: 1000 } );
	t.miolo.dispatch( 'pointerup', { clientX: 500, timeStamp: 1050 } );
	const clique = t.miolo.dispatch( 'click', {} );
	ok( ! clique.prevented, 'sem arrasto, o clique passa normalmente' );
}
{
	const t = montar( { drag: true, freeze: true, dir: 1 } );
	t.bind();
	t.quadros( 1, 16 );
	const x0 = t.x();
	t.miolo.dispatch( 'pointerdown', { clientX: 500, timeStamp: 1000 } );
	t.miolo.dispatch( 'pointermove', { clientX: 540, timeStamp: 1016 } );
	t.miolo.dispatch( 'pointermove', { clientX: 600, timeStamp: 1032 } );
	const volta2 = ( ( ( t.x() - x0 ) % 300 ) + 300 ) % 300;
	ok( perto( volta2, 100, 1 ), 'com a direção invertida, o conteúdo ainda segue o dedo', 'volta=' + volta2 );
	t.miolo.dispatch( 'pointerup', { clientX: 600, timeStamp: 1033 } );
	ok( t.ate_parar(), 'a inércia decai e o laço se apaga' );
}
{
	const t = montar( { drag: false } );
	t.bind();
	ok( ! t.miolo.classList.contains( 'wfan-mq--drag' ), 'sem "arrastável" não há classe nem cursor de mão' );
}

console.log( 'Nada para correr' );
{
	const t = montar( {} );
	t.miolo.childNodes.length = 0;
	t.bind();
	ok( ! t.track(), 'miolo vazio: não monta esteira' );
}

console.log( '\n' + ( total - falhas ) + '/' + total + ' verificações' );
process.exit( falhas ? 1 : 0 );
