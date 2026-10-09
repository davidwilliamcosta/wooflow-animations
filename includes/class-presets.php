<?php
/**
 * Catálogo de presets de animação.
 *
 * Cada preset declara o motor que exige (`engine`) — é o servidor quem decide,
 * nunca o navegador, porque é isso que permite enfileirar só a biblioteca usada
 * na página. Ver CLAUDE.md, regra 5.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Presets {

	/**
	 * Cache do catálogo já filtrado.
	 *
	 * @var array<string,array>|null
	 */
	private static $cache = null;

	/**
	 * Grupos do painel, na ordem em que aparecem.
	 *
	 * @return array<string,string>
	 */
	public static function groups() {
		return [
			'entrada' => __( 'Entrada', 'wooflow-animations' ),
			'texto'   => __( 'Texto', 'wooflow-animations' ),
			'scroll'  => __( 'Scroll', 'wooflow-animations' ),
			'enfase'  => __( 'Ênfase', 'wooflow-animations' ),
			'svg'     => __( 'SVG', 'wooflow-animations' ),
			'lottie'  => __( 'Lottie', 'wooflow-animations' ),
		];
	}

	/**
	 * Gatilhos disponíveis. Os nomes seguem o vocabulário do módulo nativo
	 * `interactions` do Elementor 4, para que migrar depois seja trivial.
	 *
	 * @return array<string,string>
	 */
	public static function triggers() {
		return [
			'scroll-in'    => __( 'Ao entrar na tela', 'wooflow-animations' ),
			'load'         => __( 'Ao carregar a página', 'wooflow-animations' ),
			'scroll-out'   => __( 'Ao sair da tela', 'wooflow-animations' ),
			'scroll-scrub' => __( 'Travado no scroll', 'wooflow-animations' ),
			'hover'        => __( 'No hover', 'wooflow-animations' ),
			'click'        => __( 'No clique', 'wooflow-animations' ),
		];
	}

	/**
	 * Curvas de easing. A chave é o nome GSAP; o valor carrega o equivalente
	 * em cubic-bezier, usado pelo motor de CSS.
	 *
	 * @return array<string,array{label:string,bezier:string}>
	 */
	public static function easings() {
		return [
			'power2.out'  => [
				'label'  => __( 'Suave na saída (padrão)', 'wooflow-animations' ),
				'bezier' => 'cubic-bezier(0.33, 1, 0.68, 1)',
			],
			'power2.in'   => [
				'label'  => __( 'Suave na entrada', 'wooflow-animations' ),
				'bezier' => 'cubic-bezier(0.32, 0, 0.67, 0)',
			],
			'power2.inOut' => [
				'label'  => __( 'Suave nas duas pontas', 'wooflow-animations' ),
				'bezier' => 'cubic-bezier(0.65, 0, 0.35, 1)',
			],
			'power4.out'  => [
				'label'  => __( 'Freada forte', 'wooflow-animations' ),
				'bezier' => 'cubic-bezier(0.22, 1, 0.36, 1)',
			],
			'back.out(1.7)' => [
				'label'  => __( 'Com repique', 'wooflow-animations' ),
				'bezier' => 'cubic-bezier(0.34, 1.56, 0.64, 1)',
			],
			'elastic.out(1, 0.5)' => [
				'label'  => __( 'Elástico', 'wooflow-animations' ),
				'bezier' => 'cubic-bezier(0.68, -0.55, 0.27, 1.55)',
			],
			'none'        => [
				'label'  => __( 'Linear', 'wooflow-animations' ),
				'bezier' => 'linear',
			],
		];
	}

	/**
	 * O catálogo.
	 *
	 * Campos de cada preset:
	 *  - label    rótulo no painel
	 *  - group    grupo do painel
	 *  - engine   css | gsap | anime | lottie
	 *  - params   controles extras que o preset usa
	 *  - triggers gatilhos aceitos (vazio = todos)
	 *  - css_only efeito de estado, resolvido só em CSS: não carrega motor nenhum
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$presets = [

			// Entrada — motor próprio, sem biblioteca nenhuma.
			'fade'        => [ 'label' => __( 'Fade', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'stagger' ] ],
			'fade-up'     => [ 'label' => __( 'Fade subindo', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'distance', 'stagger' ] ],
			'fade-down'   => [ 'label' => __( 'Fade descendo', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'distance', 'stagger' ] ],
			'fade-left'   => [ 'label' => __( 'Fade da esquerda', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'distance', 'stagger' ] ],
			'fade-right'  => [ 'label' => __( 'Fade da direita', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'distance', 'stagger' ] ],
			'slide-up'    => [ 'label' => __( 'Deslizar subindo', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'distance', 'stagger' ] ],
			'slide-down'  => [ 'label' => __( 'Deslizar descendo', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'distance', 'stagger' ] ],
			'slide-left'  => [ 'label' => __( 'Deslizar da esquerda', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'distance', 'stagger' ] ],
			'slide-right' => [ 'label' => __( 'Deslizar da direita', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'distance', 'stagger' ] ],
			'zoom-in'     => [ 'label' => __( 'Zoom para dentro', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'scale_from', 'stagger' ] ],
			'zoom-out'    => [ 'label' => __( 'Zoom para fora', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'scale_from', 'stagger' ] ],
			'blur-in'     => [ 'label' => __( 'Desfoque', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'blur', 'stagger' ] ],
			'rotate-in'   => [ 'label' => __( 'Girar', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'rotate', 'stagger' ] ],
			'flip-x'      => [ 'label' => __( 'Virar na horizontal', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'stagger' ] ],
			'flip-y'      => [ 'label' => __( 'Virar na vertical', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'stagger' ] ],
			'mask-up'     => [ 'label' => __( 'Cortina de baixo', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'stagger' ] ],
			'mask-left'   => [ 'label' => __( 'Cortina da esquerda', 'wooflow-animations' ), 'group' => 'entrada', 'engine' => 'css', 'params' => [ 'stagger' ] ],

			// Texto — divisor próprio, sem depender do SplitText.
			'text-lines'  => [ 'label' => __( 'Revelar por linha', 'wooflow-animations' ), 'group' => 'texto', 'engine' => 'css', 'params' => [ 'distance', 'stagger_each' ], 'split' => 'lines' ],
			'text-words'  => [ 'label' => __( 'Revelar por palavra', 'wooflow-animations' ), 'group' => 'texto', 'engine' => 'css', 'params' => [ 'distance', 'stagger_each' ], 'split' => 'words' ],
			'text-chars'  => [ 'label' => __( 'Revelar por letra', 'wooflow-animations' ), 'group' => 'texto', 'engine' => 'css', 'params' => [ 'distance', 'stagger_each' ], 'split' => 'chars' ],
			'text-mask'   => [ 'label' => __( 'Cortina por linha', 'wooflow-animations' ), 'group' => 'texto', 'engine' => 'css', 'params' => [ 'stagger_each' ], 'split' => 'lines' ],

			// Scroll — aqui entra o GSAP, e só aqui.
			'parallax-y'    => [ 'label' => __( 'Parallax vertical', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'distance', 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
			'parallax-x'    => [ 'label' => __( 'Parallax horizontal', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'distance', 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
			'scrub-fade'    => [ 'label' => __( 'Fade travado no scroll', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
			'scrub-scale'   => [ 'label' => __( 'Escala travada no scroll', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'scale_from', 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
			'scrub-rotate'  => [ 'label' => __( 'Giro travado no scroll', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'rotate', 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
			'scrub-reveal'  => [ 'label' => __( 'Cortina travada no scroll', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
			'pin'           => [ 'label' => __( 'Fixar na tela', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
			'progress-bar'  => [ 'label' => __( 'Barra de progresso', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
			'counter'       => [ 'label' => __( 'Contador numérico', 'wooflow-animations' ), 'group' => 'scroll', 'engine' => 'gsap', 'params' => [ 'counter_to' ], 'triggers' => [ 'scroll-in', 'load' ] ],

			// Ênfase — loops curtos, motor próprio.
			'pulse'  => [ 'label' => __( 'Pulsar', 'wooflow-animations' ), 'group' => 'enfase', 'engine' => 'css', 'params' => [ 'loop' ] ],
			'float'  => [ 'label' => __( 'Flutuar', 'wooflow-animations' ), 'group' => 'enfase', 'engine' => 'css', 'params' => [ 'distance', 'loop' ] ],
			'shake'  => [ 'label' => __( 'Tremer', 'wooflow-animations' ), 'group' => 'enfase', 'engine' => 'css', 'params' => [ 'distance', 'loop' ] ],
			'wobble' => [ 'label' => __( 'Balançar', 'wooflow-animations' ), 'group' => 'enfase', 'engine' => 'css', 'params' => [ 'rotate', 'loop' ] ],
			'glow'   => [ 'label' => __( 'Brilhar', 'wooflow-animations' ), 'group' => 'enfase', 'engine' => 'css', 'params' => [ 'loop' ] ],

			// Inversão de cor no hover: estado, não linha do tempo. Sai inteiro
			// pelos `selectors` dos controles de cor, sem JS nenhum — daí o
			// `css_only`. Ver CLAUDE.md, regra 18.
			'hover-invert' => [
				'label'    => __( 'Inverter cores no hover', 'wooflow-animations' ),
				'group'    => 'enfase',
				'engine'   => 'css',
				'params'   => [ 'hover_colors' ],
				'triggers' => [ 'hover' ],
				'css_only' => true,
			],

			// SVG — é aqui que o Anime.js ganha o lugar dele.
			'svg-draw'   => [ 'label' => __( 'Desenhar traço', 'wooflow-animations' ), 'group' => 'svg', 'engine' => 'anime', 'params' => [ 'stagger_each' ], 'triggers' => [ 'scroll-in', 'load', 'hover', 'click' ] ],

			// Lottie.
			'lottie-play'  => [ 'label' => __( 'Tocar animação Lottie', 'wooflow-animations' ), 'group' => 'lottie', 'engine' => 'lottie', 'params' => [ 'lottie_url', 'lottie_loop', 'lottie_speed' ], 'triggers' => [ 'scroll-in', 'load', 'hover', 'click' ] ],
			'lottie-scrub' => [ 'label' => __( 'Lottie travado no scroll', 'wooflow-animations' ), 'group' => 'lottie', 'engine' => 'lottie', 'params' => [ 'lottie_url', 'scrub_end' ], 'triggers' => [ 'scroll-scrub' ] ],
		];

		/**
		 * Permite adicionar, remover ou ajustar presets.
		 *
		 * @param array<string,array> $presets Catálogo.
		 */
		$presets = apply_filters( 'wfan_presets', $presets );

		foreach ( $presets as $id => $preset ) {
			$presets[ $id ] = wp_parse_args(
				$preset,
				[
					'label'    => $id,
					'group'    => 'entrada',
					'engine'   => 'css',
					'params'   => [],
					'triggers' => [],
					'split'    => '',
					'css_only' => false,
				]
			);
		}

		self::$cache = $presets;

		return $presets;
	}

	/**
	 * @param string $id Id do preset.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();

		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Motor exigido pelo preset.
	 *
	 * @param string $id Id do preset.
	 * @return string
	 */
	public static function engine_for( $id ) {
		$preset = self::get( $id );

		return $preset ? $preset['engine'] : 'css';
	}

	/**
	 * Ids de preset que usam um determinado parâmetro. Usado para montar as
	 * `condition` dos controles — um controle só aparece se o preset o usa.
	 *
	 * @param string $param Nome do parâmetro.
	 * @return string[]
	 */
	public static function ids_with_param( $param ) {
		$ids = [];

		foreach ( self::all() as $id => $preset ) {
			if ( in_array( $param, $preset['params'], true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * O preset é resolvido só em CSS?
	 *
	 * @param string $id Id do preset.
	 * @return bool
	 */
	public static function is_css_only( $id ) {
		$preset = self::get( $id );

		return $preset ? ! empty( $preset['css_only'] ) : false;
	}

	/**
	 * Ids dos presets resolvidos só em CSS. Usado para tirar do painel os
	 * controles que só o JS sabe obedecer — duração, espera, curva, motor e
	 * "desligar no celular" não chegam a lugar nenhum num efeito de estado.
	 *
	 * @return string[]
	 */
	public static function ids_css_only() {
		$ids = [];

		foreach ( self::all() as $id => $preset ) {
			if ( ! empty( $preset['css_only'] ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Ids de preset que aceitam um gatilho.
	 *
	 * @param string $trigger Gatilho.
	 * @return string[]
	 */
	public static function ids_with_trigger( $trigger ) {
		$ids = [];

		foreach ( self::all() as $id => $preset ) {
			if ( ! $preset['triggers'] || in_array( $trigger, $preset['triggers'], true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Opções no formato que o controle `select` do Elementor espera.
	 *
	 * @return array<string,string>
	 */
	public static function options() {
		$options = [ '' => __( 'Nenhuma', 'wooflow-animations' ) ];

		foreach ( self::all() as $id => $preset ) {
			$options[ $id ] = $preset['label'];
		}

		return $options;
	}

	/**
	 * Catálogo no formato consumido pelo editor (controle visual).
	 *
	 * @return array
	 */
	public static function for_js() {
		$out = [];

		foreach ( self::all() as $id => $preset ) {
			$out[] = [
				'id'       => $id,
				'label'    => $preset['label'],
				'group'    => $preset['group'],
				'engine'   => $preset['engine'],
				'params'   => $preset['params'],
				'triggers' => $preset['triggers'] ? $preset['triggers'] : array_keys( self::triggers() ),
			];
		}

		return $out;
	}
}
