<?php
/**
 * Popup nascendo de um círculo no ponto em que foi aberto.
 *
 * Quinto efeito global do plugin. Como o Lenis, o blur, o botão de voltar ao
 * topo e a barra fixa: ligado nos Ajustes, fora do catálogo de presets, sem
 * passar pelo core.js, enfileirado no `wp_enqueue_scripts` (a regra 24 trata
 * de descoberta em tempo de render, e aqui a decisão é uma option).
 *
 * Duas coisas separam este módulo dos outros quatro, e as duas estão na
 * regra 28 do CLAUDE.md: ele depende de um evento que só o Elementor Pro
 * dispara, e a origem do círculo não é configurada — é o último clique.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Popup {

	/**
	 * Classe que o JS põe no modal enquanto ele está aberto. Tem de bater
	 * com o popup.css.
	 *
	 * @var string
	 */
	const OPEN_CLASS = 'wfan-pop-open';

	/**
	 * Classe opcional, posta pelo usuário nos itens do menu que devem entrar
	 * escalonados depois que o círculo abre.
	 *
	 * @var string
	 */
	const ITEM_CLASS = 'wfan-popup-item';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
	}

	/**
	 * O efeito inteiro pende dos eventos jQuery `elementor/popup/show` e
	 * `elementor/popup/hide`, que são do Elementor Pro. Sem o Pro não há
	 * popup nenhum na página, e carregar a folha e o script seria peso por
	 * nada.
	 *
	 * @return bool
	 */
	public static function available() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	/**
	 * @return bool
	 */
	public static function active() {
		if ( ! self::available() || ! WFAN_Settings::is_on( 'pop_enable' ) ) {
			return false;
		}

		// Nunca no editor: o popup ali é montado para edição, e um
		// clip-path zerado esconderia o que o usuário está editando.
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return false;
		}

		/**
		 * Permite desligar o efeito em contextos específicos.
		 *
		 * @param bool $active Se o efeito deve ser carregado.
		 */
		return (bool) apply_filters( 'wfan_popup_active', true );
	}

	/**
	 * Ids de popup que recebem o efeito. Array vazio = todos.
	 *
	 * É um filtro, e não um campo nos ajustes: a tela só tem toggle, número,
	 * escolha e cor, e o caso comum — um mega menu só — já funciona sem
	 * configurar nada.
	 *
	 * @return int[]
	 */
	public static function ids() {
		/**
		 * Restringe o efeito a certos popups.
		 *
		 * @param int[] $ids Ids de popup. Vazio aplica a todos.
		 */
		$ids = (array) apply_filters( 'wfan_popup_ids', [] );

		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * A cor da sobreposição, em rgba, montada do hex dos ajustes com a
	 * opacidade de cada ponta do degradê.
	 *
	 * @param string $key Chave da opacidade: pop_near ou pop_far.
	 * @return string
	 */
	private static function overlay_rgba( $key ) {
		$hex = sanitize_hex_color( (string) WFAN_Settings::get( 'pop_overlay', '#0e1016' ) );
		$hex = $hex ? ltrim( $hex, '#' ) : '0e1016';

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$alpha = max( 0, min( 100, (int) WFAN_Settings::get( $key, 30 ) ) ) / 100;

		return sprintf(
			'rgba(%d,%d,%d,%s)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) ?: '0'
		);
	}

	/**
	 * Os parâmetros que não mudam de um popup para outro. Os que mudam a cada
	 * abertura — origem e raio do círculo — são escritos pelo JS no modal.
	 *
	 * Tudo em custom property, pelo mesmo motivo da regra 20: o Elementor
	 * reescreve o que quiser em volta, e a variável sobrevive.
	 *
	 * @return string
	 */
	public static function inline_css() {
		$vars = [
			'--wfan-pop-open'  => (int) WFAN_Settings::get( 'pop_open', 850 ) . 'ms',
			'--wfan-pop-close' => (int) WFAN_Settings::get( 'pop_close', 520 ) . 'ms',
			'--wfan-pop-near'  => self::overlay_rgba( 'pop_near' ),
			'--wfan-pop-far'   => self::overlay_rgba( 'pop_far' ),
			'--wfan-pop-blur'  => WFAN_Settings::is_on( 'pop_blur_on' )
				? (int) WFAN_Settings::get( 'pop_blur', 10 ) . 'px'
				: '0px',
		];

		$css = '';

		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		/*
		 * O seletor é o id do modal, e não a nossa classe: as variáveis
		 * precisam estar lá ANTES de o JS entrar em cena, porque a transição
		 * de fechamento usa a duração e ela vale desde o primeiro quadro.
		 */
		$css = '[id^="elementor-popup-modal-"]{' . $css . '}';

		/*
		 * Em tela pequena o backdrop-filter custa caro: ele desfoca a página
		 * inteira a cada quadro da abertura. A variável zerada tira o
		 * desfoque sem tirar a sobreposição.
		 */
		if ( WFAN_Settings::is_on( 'pop_off_mobile' ) ) {
			$css .= '@media (max-width:' . (int) WFAN_Settings::get( 'mobile_bp', 767 ) . 'px){'
				. '[id^="elementor-popup-modal-"]{--wfan-pop-blur:0px}}';
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

		wp_enqueue_style( 'wfan-popup' );
		wp_add_inline_style( 'wfan-popup', self::inline_css() );

		wp_enqueue_script( 'wfan-popup' );

		/*
		 * Regra 17: tipado, nunca por wp_localize_script. `reduced` e
		 * `offMobile` são testados por veracidade no popup.js, e "0" é
		 * verdadeiro no JavaScript.
		 */
		WFAN_Assets::localize(
			'wfan-popup',
			'wfanPopup',
			[
				'ids'       => self::ids(),
				'open'      => (int) WFAN_Settings::get( 'pop_open', 850 ),
				'offMobile' => WFAN_Settings::is_on( 'pop_off_mobile' ),
				'mobileBp'  => (int) WFAN_Settings::get( 'mobile_bp', 767 ),
				'reduced'   => WFAN_Settings::is_on( 'respect_reduced' ),
				'debug'     => WFAN_Settings::is_on( 'debug' ),
			]
		);
	}
}
