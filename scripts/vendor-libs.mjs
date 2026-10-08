#!/usr/bin/env node
/**
 * Baixa as bibliotecas de terceiros para assets/lib/, nas versões fixadas no
 * package.json (campo dwVendor).
 *
 * Não precisa de npm install: usa só o fetch do Node (18+). assets/lib/ não vai
 * para o git — este script é o que reconstrói a pasta. Ver CLAUDE.md, regra 9.
 *
 * Uso: npm run vendor
 */

import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

const here = dirname( fileURLToPath( import.meta.url ) );
const root = resolve( here, '..' );
const libDir = join( root, 'assets', 'lib' );

const pkg = JSON.parse( await readFile( join( root, 'package.json' ), 'utf8' ) );
const vendor = pkg.dwVendor || {};

let failures = 0;
const manifest = [];

for ( const [ name, spec ] of Object.entries( vendor ) ) {
	for ( const [ target, template ] of Object.entries( spec.files ) ) {
		const url = template.replace( '{v}', spec.version );
		const dest = join( libDir, target );

		process.stdout.write( `${ name }@${ spec.version } → assets/lib/${ target } … ` );

		try {
			const response = await fetch( url );

			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}

			const body = Buffer.from( await response.arrayBuffer() );

			if ( body.length < 1024 ) {
				throw new Error( `arquivo suspeito de ${ body.length } bytes` );
			}

			await mkdir( dirname( dest ), { recursive: true } );
			await writeFile( dest, body );

			console.log( `ok (${ Math.round( body.length / 1024 ) } KB)` );

			manifest.push( {
				lib: name,
				version: spec.version,
				license: spec.license,
				file: target,
				bytes: body.length,
				url,
			} );
		} catch ( error ) {
			failures++;
			console.log( `FALHOU: ${ error.message }` );
		}
	}
}

if ( manifest.length ) {
	await writeFile(
		join( libDir, 'manifest.json' ),
		JSON.stringify( { generated: new Date().toISOString(), libs: manifest }, null, '\t' ) + '\n'
	);
}

if ( failures ) {
	console.error( `\n${ failures } arquivo(s) não baixaram. O plugin funciona sem as bibliotecas, mas só com o motor próprio.` );
	process.exit( 1 );
}

console.log( '\nTudo em assets/lib/.' );
