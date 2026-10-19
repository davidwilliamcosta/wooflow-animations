<?php
/**
 * Botão "voltar ao topo" global.
 *
 * Terceiro efeito global do plugin, ao lado do Lenis e do blur: ligado nos
 * Ajustes, não entra no catálogo de presets, não passa pelo core.js e não
 * esconde conteúdo nenhum. A diferença que vale o módulo próprio é que este é
 * um **controle de verdade** — <button> focável, com nome acessível —, e não
 * decoração `aria-hidden` como a faixa de blur.
 *
 * A regra 24 do CLAUDE.md não se aplica aqui: o enfileiramento sai do
 * `wp_enqueue_scripts`, que roda em toda requisição e antes de o Elementor
 * tirar o retrato da fila de scripts, e a decisão não depende do documento —
 * `active()` lê uma option, não ajuste de elemento. Não há nada a recuperar do
 * cache de elementos, e por isso nada de `require_*()` nem `flush()`: eles
 * existem para carregar uma descoberta de tempo de render até o rodapé, e
 * usar um deles aqui arrastaria o `wfan-frontend` junto.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_BackToTop {

	/**
	 * Classe raiz. O backtotop.js procura por ela.
	 *
	 * @var string
	 */
	const CLASS_NAME = 'wfan-btt';

	/**
	 * Classe que o JS acende depois do limiar. Tem de bater com o CSS.
	 *
	 * @var string
	 */
	const VISIBLE_CLASS = 'is-visible';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
		add_action( 'wp_footer', [ $this, 'render' ], 100 );
	}

	/**
	 * @return bool
	 */
	public static function active() {
		if ( ! WFAN_Settings::is_on( 'btt_enable' ) ) {
			return false;
		}

		// Nunca no editor: um botão fixo no canto cobre a alça de um elemento.
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return false;
		}

		/**
		 * Permite desligar o botão em contextos específicos.
		 *
		 * @param bool $active Se o botão deve ser impresso.
		 */
		return (bool) apply_filters( 'wfan_backtotop_active', true );
	}

	/**
	 * Posições. A chave é o valor gravado; a ordem é a do painel.
	 *
	 * @return array<string,string>
	 */
	public static function positions() {
		return [
			'right'  => __( 'Canto direito', 'wooflow-animations' ),
			'left'   => __( 'Canto esquerdo', 'wooflow-animations' ),
			'center' => __( 'Centralizado no rodapé', 'wooflow-animations' ),
		];
	}

	/**
	 * Formas, e o raio de borda de cada uma.
	 *
	 * @return array<string,string>
	 */
	public static function shapes() {
		return [
			'circle'  => __( 'Círculo', 'wooflow-animations' ),
			'rounded' => __( 'Quadrado arredondado', 'wooflow-animations' ),
			'square'  => __( 'Quadrado', 'wooflow-animations' ),
		];
	}

	/**
	 * @return array<string,string>
	 */
	public static function icons() {
		return [
			'arrow'   => __( 'Seta', 'wooflow-animations' ),
			'chevron' => __( 'Chevron', 'wooflow-animations' ),
			'double'  => __( 'Chevron duplo', 'wooflow-animations' ),
		];
	}

	/**
	 * @return array<string,string>
	 */
	public static function entrances() {
		return [
			'fade'     => __( 'Aparecer', 'wooflow-animations' ),
			'slide-up' => __( 'Subir', 'wooflow-animations' ),
			'zoom'     => __( 'Crescer', 'wooflow-animations' ),
		];
	}

	/**
	 * Raio de borda de cada forma. Um valor para uma propriedade: é variável,
	 * e não três classes modificadoras idênticas.
	 *
	 * @var array<string,string>
	 */
	const RADIUS = [
		'circle'  => '50%',
		'rounded' => '12px',
		'square'  => '0',
	];

	/**
	 * Lê um campo de escolha já validado contra a lista do WFAN_Settings.
	 *
	 * @param string $key Chave do ajuste.
	 * @return string
	 */
	private static function choice( $key ) {
		$allowed = WFAN_Settings::selects()[ $key ] ?? [];
		$value   = (string) WFAN_Settings::get( $key, '' );

		return in_array( $value, $allowed, true ) ? $value : (string) ( $allowed[0] ?? '' );
	}

	/**
	 * @return string
	 */
	public static function position() {
		return self::choice( 'btt_pos' );
	}

	/**
	 * @return string
	 */
	public static function entrance() {
		return self::choice( 'btt_anim' );
	}

	/**
	 * @return string
	 */
	public static function shape() {
		return self::choice( 'btt_shape' );
	}

	/**
	 * O anel só existe na forma redonda: a máscara dele é um
	 * radial-gradient, e num quadrado ele sairia torto. Um anel de canto
	 * arredondado precisaria de outra técnica, incompatível com esta.
	 *
	 * @return bool
	 */
	public static function ring() {
		return WFAN_Settings::is_on( 'btt_ring' ) && 'circle' === self::shape();
	}

	/**
	 * Medidas e cores do botão, como variáveis CSS.
	 *
	 * Inline porque são configuráveis, como no blur. O sufixo de cada nome é
	 * de um só segmento (`-bgh`, e não `-bg-hover`) de propósito: a varredura
	 * de bijeção do smoke test quebra o nome no hífen, e um nome composto
	 * daria falso negativo por um não-motivo.
	 *
	 * @return string
	 */
	public static function inline_css() {
		$shape = self::shape();

		$vars = [
			'--wfan-btt-size' => (int) WFAN_Settings::get( 'btt_size', 48 ) . 'px',
			'--wfan-btt-x'    => (int) WFAN_Settings::get( 'btt_x', 24 ) . 'px',
			'--wfan-btt-y'    => (int) WFAN_Settings::get( 'btt_y', 24 ) . 'px',
			'--wfan-btt-r'    => self::RADIUS[ $shape ] ?? self::RADIUS['circle'],
			'--wfan-btt-bg'   => (string) WFAN_Settings::get( 'btt_bg', '#111827' ),
			'--wfan-btt-fg'   => (string) WFAN_Settings::get( 'btt_fg', '#ffffff' ),
			'--wfan-btt-bgh'  => (string) WFAN_Settings::get( 'btt_bg_hover', '#7c3aed' ),
			'--wfan-btt-z'    => (string) (int) WFAN_Settings::get( 'btt_z', 1000 ),
		];

		$css = '';

		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		$css = '.' . self::CLASS_NAME . '{' . $css . '}';

		/*
		 * `display:none` e não `opacity:0`: assim o botão também sai da ordem
		 * de foco, em vez de virar um ponto de parada invisível no teclado.
		 */
		if ( WFAN_Settings::is_on( 'btt_off_mobile' ) ) {
			$css .= '@media (max-width:' . (int) WFAN_Settings::get( 'mobile_bp', 767 ) . 'px){.' . self::CLASS_NAME . '{display:none}}';
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

		wp_enqueue_style( 'wfan-backtotop' );
		wp_add_inline_style( 'wfan-backtotop', self::inline_css() );

		wp_enqueue_script( 'wfan-backtotop' );

		/*
		 * Regra 17: tipado, nunca por wp_localize_script. `ring` e `reduced`
		 * são testados por veracidade no backtotop.js, e "0" é verdadeiro no
		 * JavaScript — o anel desligado chegaria ligado, com um setProperty
		 * por frame de scroll em toda página do site.
		 */
		WFAN_Assets::localize(
			'wfan-backtotop',
			'wfanBackToTop',
			[
				'threshold' => (int) WFAN_Settings::get( 'btt_threshold', 400 ),
				/*
				 * Em milissegundos e como inteiro, que é a unidade que o JS
				 * usa. Em segundos seria um float: `(float) '0.8'` sai do
				 * json_encode como 0.80000000000000004 — 0,8 não é
				 * representável em binário, e arredondar não muda isso —, e
				 * aquilo iria para o código-fonte de toda página do site.
				 */
				'duration'  => (int) round( (float) WFAN_Settings::get( 'btt_duration', 0.8 ) * 1000 ),
				'ring'      => self::ring(),
				'reduced'   => WFAN_Settings::is_on( 'respect_reduced' ),
				'debug'     => WFAN_Settings::is_on( 'debug' ),
			]
		);
	}

	/**
	 * Os três ícones, todos em traço e em `currentColor` — nunca em
	 * preenchimento. É a quarta consequência da regra 18: um `fill` no próprio
	 * <svg> não alcança um `<path fill="…">`, porque presentation attribute
	 * ganha da herança, e o ícone ficaria parado enquanto o resto muda de cor.
	 *
	 * @param string $id arrow | chevron | double.
	 * @return string
	 */
	public static function icon_svg( $id ) {
		$paths = [
			'arrow'   => '<path d="M12 19V5"/><path d="M5 12l7-7 7 7"/>',
			'chevron' => '<path d="M6 15l6-6 6 6"/>',
			'double'  => '<path d="M6 17l6-6 6 6"/><path d="M6 11l6-6 6 6"/>',
		];

		$d = isset( $paths[ $id ] ) ? $paths[ $id ] : $paths['arrow'];

		/*
		 * `focusable="false"` porque Edge e IE antigos põem SVG na ordem de
		 * tabulação: seriam dois pontos de parada para um só controle.
		 */
		return '<svg class="' . self::CLASS_NAME . '__icon" viewBox="0 0 24 24" fill="none"'
			. ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
			. ' aria-hidden="true" focusable="false">' . $d . '</svg>';
	}

	/**
	 * Ao contrário da faixa de blur, isto é um controle: <button> de verdade,
	 * focável na ordem natural e com nome acessível. Nada de <div> com
	 * onclick, nada de `aria-hidden` no botão e nada de `tabindex` — <button>
	 * já é focável, e um `title` repetindo o `aria-label` faria alguns
	 * leitores de tela anunciarem o nome duas vezes.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! self::active() ) {
			return;
		}

		$classes = [
			self::CLASS_NAME,
			self::CLASS_NAME . '--' . self::position(),
			self::CLASS_NAME . '--' . self::entrance(),
		];

		if ( self::ring() ) {
			$classes[] = self::CLASS_NAME . '--ring';
		}

		/**
		 * Rótulo acessível do botão: a única coisa que o leitor de tela tem
		 * para ler, já que o conteúdo é um ícone.
		 *
		 * @param string $label Texto do `aria-label`.
		 */
		$label = (string) apply_filters( 'wfan_backtotop_label', __( 'Voltar ao topo', 'wooflow-animations' ) );

		printf(
			'<button type="button" class="%1$s" aria-label="%2$s">%3$s%4$s</button>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $label ),
			self::ring() ? '<span class="' . esc_attr( self::CLASS_NAME . '__ring' ) . '" aria-hidden="true"></span>' : '',
			self::icon_svg( self::choice( 'btt_icon' ) )
		);
	}
}
