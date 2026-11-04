/**
 * Teste do contador: `node tests/counter.js`.
 *
 * Carrega o engine-css.js de verdade num DOM de mentira e dirige os quadros um
 * a um. O que ele guarda (CLAUDE.md, regra 32):
 *  - o número escrito no HTML é o FINAL: terminada a contagem — ou desfeita —
 *    o texto volta a ser exatamente o original, que é o que robô e buscador leem;
 *  - o formato do texto é preservado nos valores intermediários ("1.250,5",
 *    "1,250", prefixo e sufixo);
 *  - o primeiro valor mostrado é o de partida, na mesma hora, antes de pintar;
 *  - o `requestAnimationFrame` se apaga ao terminar.
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const ENGINE = process.argv[ 2 ] || path.join( __dirname, '..', 'assets', 'js', 'frontend', 'engine-css.js' );

let falhas = 0;

function ok( condicao, rotulo, detalhe ) {
	if ( condicao ) {
		console.log( '  ok   ' + rotulo );
		return;
	}

	falhas++;
	console.log( '  FALHA ' + rotulo + ( detalhe ? ' — ' + detalhe : '' ) );
}

/** Um elemento com um único nó de texto (ou vários, para o caso do prefixo em <span>). */
function ambiente() {
	const quadros = [];
	let id = 0;
	let registrado = null;

	const env = {
		quadros,
		window: null,
		document: {
			createTreeWalker( raiz ) {
				const lista = raiz.textos.slice();
				return { nextNode: () => lista.shift() || null };
			}
		},
		requestAnimationFrame( fn ) { quadros.push( { id: ++id, fn } ); return id; },
		cancelAnimationFrame( n ) {
			const i = quadros.findIndex( ( q ) => q.id === n );
			if ( i > -1 ) { quadros.splice( i, 1 ); }
		},
		passo( agora ) {
			const q = quadros.shift();
			if ( q ) { q.fn( agora ); }
			return !! q;
		}
	};

	env.window = {
		wfan: {
			util: { cssEase: () => 'cubic-bezier(0, 0, 1, 1)' },
			register( nome, motor ) { registrado = motor; }
		}
	};

	vm.runInNewContext( fs.readFileSync( ENGINE, 'utf8' ), Object.assign( env, { wfanCssBuild: null } ), { filename: 'engine-css.js' } );
	env.motor = registrado;

	return env;
}

function elemento( ...textos ) {
	const el = { textos: [] };
	textos.forEach( ( t ) => el.textos.push( { nodeValue: t, parentNode: { nodeName: 'SPAN' } } ) );
	return el;
}

function spec( extra ) {
	return Object.assign( { preset: 'counter', duration: 1000, delay: 0, easing: 'linear', counter: { from: 0, to: null } }, extra || {} );
}

console.log( 'contador' );

// 1. Termina no texto original e mostra a partida de imediato.
{
	const env = ambiente();
	const el = elemento( '450' );
	env.motor.play( el, spec(), [ el ] );

	ok( '0' === el.textos[ 0 ].nodeValue, 'o primeiro valor é o de partida, já no play()', el.textos[ 0 ].nodeValue );
	env.passo( 0 );
	env.passo( 500 );
	ok( '225' === el.textos[ 0 ].nodeValue, 'no meio da curva linear está na metade', el.textos[ 0 ].nodeValue );
	env.passo( 1000 );
	ok( '450' === el.textos[ 0 ].nodeValue, 'ao fim o texto é o ORIGINAL', el.textos[ 0 ].nodeValue );
	ok( 0 === env.quadros.length, 'e o laço se apaga' );
}

// 2. Formato pt-BR com milhar e decimal; prefixo e sufixo ficam.
{
	const env = ambiente();
	const el = elemento( 'R$ 1.250,50 mil' );
	env.motor.play( el, spec(), [ el ] );

	ok( 'R$ 0,00 mil' === el.textos[ 0 ].nodeValue, 'prefixo, sufixo e casas decimais preservados', el.textos[ 0 ].nodeValue );
	env.passo( 0 );
	env.passo( 500 );
	ok( 'R$ 625,25 mil' === el.textos[ 0 ].nodeValue, 'o separador decimal do texto é usado na contagem', el.textos[ 0 ].nodeValue );
	env.passo( 2000 );
	ok( 'R$ 1.250,50 mil' === el.textos[ 0 ].nodeValue, 'e o final volta idêntico', el.textos[ 0 ].nodeValue );
}

// 3. Milhar com vírgula (en-US) e milhar sem decimal.
{
	const env = ambiente();
	const el = elemento( '+ de 12,000' );
	env.motor.play( el, spec(), [ el ] );
	env.passo( 0 );
	env.passo( 500 );
	ok( '+ de 6,000' === el.textos[ 0 ].nodeValue, '"12,000" é milhar, não decimal', el.textos[ 0 ].nodeValue );

	const b = ambiente();
	const e2 = elemento( '3,5' );
	b.motor.play( e2, spec(), [ e2 ] );
	b.passo( 0 );
	b.passo( 500 );
	ok( '1,8' === e2.textos[ 0 ].nodeValue || '1,7' === e2.textos[ 0 ].nodeValue, '"3,5" é decimal', e2.textos[ 0 ].nodeValue );
}

// 4. Só o nó que tem o número é tocado.
{
	const env = ambiente();
	const el = elemento( '+', 'de ', '450' );
	env.motor.play( el, spec(), [ el ] );

	ok( '+' === el.textos[ 0 ].nodeValue && 'de ' === el.textos[ 1 ].nodeValue, 'nós sem número não são tocados' );
	ok( '0' === el.textos[ 2 ].nodeValue, 'o nó do número é o que conta', el.textos[ 2 ].nodeValue );
}

// 5. Número final vindo do painel; partida diferente.
{
	const env = ambiente();
	const el = elemento( '999' );
	env.motor.play( el, spec( { counter: { from: 100, to: 200 } } ), [ el ] );
	env.passo( 0 );
	env.passo( 500 );
	ok( '150' === el.textos[ 0 ].nodeValue, 'conta de 100 até o número do painel', el.textos[ 0 ].nodeValue );
	env.passo( 1000 );
	ok( '999' === el.textos[ 0 ].nodeValue, 'e termina no texto, que é o que o HTML diz', el.textos[ 0 ].nodeValue );
}

// 6. reset() devolve o original no meio da contagem e cancela o laço.
{
	const env = ambiente();
	const el = elemento( '450' );
	env.motor.play( el, spec(), [ el ] );
	env.passo( 0 );
	env.passo( 300 );
	env.motor.reset( el, spec(), [ el ] );

	ok( '450' === el.textos[ 0 ].nodeValue, 'reset no meio devolve o número final', el.textos[ 0 ].nodeValue );
	ok( 0 === env.quadros.length, 'e cancela o quadro pendente' );

	env.motor.play( el, spec(), [ el ] );
	ok( 1 === env.quadros.length, 'tocar de novo não empilha laços' );
	env.motor.play( el, spec(), [ el ] );
	ok( 1 === env.quadros.length, 'nem tocar duas vezes seguidas', String( env.quadros.length ) );
}

// 7. Sem número, nada acontece; partida igual ao final, nada acontece.
{
	const env = ambiente();
	const vazio = elemento( 'sem número' );
	env.motor.play( vazio, spec(), [ vazio ] );
	ok( 'sem número' === vazio.textos[ 0 ].nodeValue && 0 === env.quadros.length, 'texto sem número fica como está' );

	const igual = elemento( '0' );
	env.motor.play( igual, spec(), [ igual ] );
	ok( 0 === env.quadros.length, 'partida igual ao final não gasta quadros' );
}

// 8. Espera: durante o delay continua na partida.
{
	const env = ambiente();
	const el = elemento( '100' );
	env.motor.play( el, spec( { delay: 400 } ), [ el ] );
	env.passo( 0 );
	env.passo( 200 );
	ok( '0' === el.textos[ 0 ].nodeValue, 'na espera, o valor é o de partida', el.textos[ 0 ].nodeValue );
	env.passo( 900 );
	ok( '50' === el.textos[ 0 ].nodeValue, 'depois da espera a contagem anda', el.textos[ 0 ].nodeValue );
}

console.log( falhas ? '\n' + falhas + ' falha(s)' : '\ntudo certo' );
process.exit( falhas ? 1 : 0 );
