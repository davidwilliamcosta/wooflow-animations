<?php
/**
 * Barra fixa com fundo e sombra progressivos.
 *
 * Quarto efeito global do plugin, ao lado do Lenis, do blur e do botão de
 * voltar ao topo: ligado nos Ajustes, não entra no catálogo de presets, não
 * passa pelo core.js e não esconde conteúdo nenhum.
 *
 * A regra 24 do CLAUDE.md não se aplica aqui, pelo mesmo motivo do botão: o
 * enfileiramento sai do `wp_enqueue_scripts`, que roda em toda requisição e
 * antes de o Elementor tirar o retrato da fila de scripts, e `active()` lê uma
 * option, não ajuste de elemento. Não há nada a recuperar do cache de
 * elementos, e um `require_*()` aqui arrastaria o `wfan-frontend` junto.
 *
 * O que separa este módulo dos outros três é de onde vem a cor: ela não é um
 * ajuste nosso. Ver CLAUDE.md, regra 27.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Header {

	/**
	 * Classe que o usuário põe no container da barra, em
	 * Avançado > Classes CSS. O header.js procura por ela.
	 *
	 * @var string
	 */
	const CLASS_NAME = 'wfan-header';

	/**
	 * Classe que desliga o fundo controlado pelo script, só pelo tempo de ler
	 * a cor original do elemento. Tem de bater com o CSS e com o JS.
	 *
	 * @var string
	 */
	const OFF_CLASS = 'wfan-header--off';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
	}

	/**
	 * @return bool
	 */
	public static function active() {
		if ( ! WFAN_Settings::is_on( 'hdr_enable' ) ) {
			return false;
		}

		// Nunca no editor: a barra passaria a ser `position:fixed` por cima
		// do canvas, e a alça do container ficaria inalcançável.
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return false;
		}

		/**
		 * Permite desligar a barra em contextos específicos.
		 *
		 * @param bool $active Se o efeito deve ser carregado.
		 */
		return (bool) apply_filters( 'wfan_header_active', true );
	}

	/**
	 * Seletor da barra. Um filtro, e não um campo de texto nos ajustes: a
	 * tela só tem toggle, número, escolha e cor, e abrir um tipo de campo
	 * novo por um valor que quase ninguém troca não se paga.
	 *
	 * @return string
	 */
	public static function selector() {
		$selector = (string) apply_filters( 'wfan_header_selector', '.' . self::CLASS_NAME );

		return '' !== trim( $selector ) ? $selector : '.' . self::CLASS_NAME;
	}

	/**
	 * Medidas que o CSS precisa saber de antemão. As que mudam a cada quadro
	 * — `--wfan-hdr-bg` e `--wfan-hdr-p` — são escritas pelo JS no próprio
	 * elemento, e não aparecem aqui.
	 *
	 * @return string
	 */
	public static function inline_css() {
		$css = '.' . self::CLASS_NAME . '{--wfan-hdr-z:' . (int) WFAN_Settings::get( 'hdr_z', 999 ) . ';'
			. '--wfan-hdr-sh:' . ( (int) WFAN_Settings::get( 'hdr_shadow_op', 6 ) / 100 ) . '}';

		if ( WFAN_Settings::is_on( 'hdr_fixed' ) ) {
			$css .= '.' . self::CLASS_NAME . '{position:fixed;top:0;left:0;width:100%;z-index:var(--wfan-hdr-z)}';
			$css .= '.admin-bar .' . self::CLASS_NAME . '{top:32px}';
			$css .= '@media screen and (max-width:782px){.admin-bar .' . self::CLASS_NAME . '{top:46px}}';
		}

		return $css;
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::active() ) {
			return;
		}

		wp_enqueue_style( 'wfan-header' );
		wp_add_inline_style( 'wfan-header', self::inline_css() );

		wp_enqueue_script( 'wfan-header' );

		/*
		 * Regra 17: tipado, nunca por wp_localize_script. `shadow` e
		 * `reduced` são testados por veracidade no header.js, e "0" é
		 * verdadeiro no JavaScript — a sombra desligada chegaria ligada.
		 */
		WFAN_Assets::localize(
			'wfan-header',
			'wfanHeader',
			[
				'selector' => self::selector(),
				'distance' => (int) WFAN_Settings::get( 'hdr_distance', 280 ),
				'smooth'   => (int) WFAN_Settings::get( 'hdr_smooth', 220 ),
				'shadow'   => WFAN_Settings::is_on( 'hdr_shadow' ),
				'reduced'  => WFAN_Settings::is_on( 'respect_reduced' ),
				'debug'    => WFAN_Settings::is_on( 'debug' ),
			]
		);
	}
}
