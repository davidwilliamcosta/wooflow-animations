<?php
/**
 * Blur progressivo global.
 *
 * Uma faixa fixa na borda da viewport, com camadas de backdrop-filter
 * mascaradas em degradê. Não é animação: não tem gatilho, não passa pelo
 * core.js e não esconde conteúdo nenhum — por isso vive num módulo próprio,
 * como o Lenis, e fora do catálogo de presets.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Blur {

	/**
	 * Desfoque relativo de cada camada. A última é a mais forte e serve de
	 * escala: o valor escolhido nos Ajustes é o dela, e as outras seguem na
	 * mesma proporção.
	 *
	 * @var int[]
	 */
	const LAYERS = [ 1, 3, 7 ];

	/**
	 * Altura da barra de administração no front-end: desktop e, abaixo de
	 * 782px, a barra alta que o WordPress usa no celular.
	 *
	 * @var int[]
	 */
	const ADMIN_BAR = [ 32, 46 ];

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
		add_action( 'wp_footer', [ $this, 'render' ], 100 );
	}

	/**
	 * @return bool
	 */
	public static function active() {
		if ( ! WFAN_Settings::is_on( 'blur_enable' ) ) {
			return false;
		}

		// Nunca no editor: a faixa cobriria justamente o que se está editando.
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return false;
		}

		/**
		 * Permite desligar o blur em contextos específicos.
		 *
		 * @param bool $active Se a faixa deve ser impressa.
		 */
		return (bool) apply_filters( 'wfan_blur_active', true );
	}

	/**
	 * Bordas em que a faixa é impressa.
	 *
	 * @return string[]
	 */
	public static function edges() {
		$pos = (string) WFAN_Settings::get( 'blur_pos', 'bottom' );

		if ( 'both' === $pos ) {
			return [ 'top', 'bottom' ];
		}

		return [ 'top' === $pos ? 'top' : 'bottom' ];
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::active() ) {
			return;
		}

		wp_enqueue_style( 'wfan-blur' );
		wp_add_inline_style( 'wfan-blur', self::inline_css() );
	}

	/**
	 * Medidas do efeito, como variáveis CSS.
	 *
	 * Inline porque são configuráveis: um arquivo com os valores dentro
	 * precisaria ser regravado a cada vez que os Ajustes mudassem.
	 *
	 * @return string
	 */
	public static function inline_css() {
		$height   = (int) WFAN_Settings::get( 'blur_height', 20 );
		$strength = (float) WFAN_Settings::get( 'blur_strength', 7 );
		$peak     = (float) self::LAYERS[ count( self::LAYERS ) - 1 ];
		$scale    = $peak > 0 ? $strength / $peak : 1.0;

		$vars = [
			'--wfan-blur-h' => $height . 'vh',
			'--wfan-blur-z' => (string) (int) WFAN_Settings::get( 'blur_z', 999 ),
		];

		foreach ( self::LAYERS as $i => $weight ) {
			$vars[ '--wfan-blur-' . ( $i + 1 ) ] = round( $weight * $scale, 2 ) . 'px';
		}

		$css = '';

		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		$css = '.wfan-blur{' . $css . '}';

		/*
		 * A barra de administração é fixa no topo e tem z-index muito mais
		 * alto, então uma faixa no topo passaria por baixo dela e ficaria
		 * cortada. Só quem vê a barra paga o deslocamento.
		 */
		if ( is_admin_bar_showing() && in_array( 'top', self::edges(), true ) ) {
			$css .= '.wfan-blur--top{--wfan-blur-offset:' . (int) self::ADMIN_BAR[0] . 'px}';
			$css .= '@media screen and (max-width:782px){.wfan-blur--top{--wfan-blur-offset:' . (int) self::ADMIN_BAR[1] . 'px}}';
		}

		// backdrop-filter em tela cheia é caro justamente onde a GPU é fraca.
		if ( WFAN_Settings::is_on( 'blur_off_mobile' ) ) {
			$css .= '@media (max-width:' . (int) WFAN_Settings::get( 'mobile_bp', 767 ) . 'px){.wfan-blur{display:none}}';
		}

		return $css;
	}

	/**
	 * A faixa é decoração pura: `aria-hidden` e sem foco, para não aparecer
	 * em leitor de tela nem na navegação por teclado.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! self::active() ) {
			return;
		}

		$layers = count( self::LAYERS );

		foreach ( self::edges() as $edge ) {
			printf( '<div class="wfan-blur wfan-blur--%s" aria-hidden="true">', esc_attr( $edge ) );

			for ( $i = 1; $i <= $layers; $i++ ) {
				printf( '<div class="wfan-blur__layer wfan-blur__layer--%d"></div>', (int) $i );
			}

			echo '</div>';
		}
	}
}
