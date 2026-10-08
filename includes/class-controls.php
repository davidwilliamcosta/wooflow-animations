<?php
/**
 * Injeta a seção "DW Animações" nos elementos do Elementor.
 *
 * São cinco tipos, não quatro: `common-optimized` é o elemento usado quando o
 * experimento de markup otimizado está ligado, e esquecer dele faz os controles
 * sumirem sem erro nenhum. Ver CLAUDE.md, regra 2.
 *
 * @package DW_Anim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class DW_Anim_Controls {

	/**
	 * Tipos de elemento que recebem a seção.
	 *
	 * @var string[]
	 */
	const ELEMENT_TYPES = [ 'common', 'common-optimized', 'container', 'section', 'column' ];

	public function __construct() {
		foreach ( self::ELEMENT_TYPES as $type ) {
			add_action( "elementor/element/{$type}/section_effects/after_section_end", [ $this, 'inject' ], 10, 2 );
		}
	}

	/**
	 * Elemento atômico (Elementor 4, experimento e_atomic_elements) tem o módulo
	 * `interactions` nativo. Os dois juntos brigam, então aqui a gente sai fora.
	 *
	 * @param \Elementor\Controls_Stack $element Elemento.
	 * @return bool
	 */
	private function is_atomic( $element ) {
		return method_exists( $element, 'get_props_schema' );
	}

	/**
	 * Ids de preset cujo motor é o informado.
	 *
	 * @param string $engine Motor.
	 * @return string[]
	 */
	private function ids_with_engine( $engine ) {
		$ids = [];

		foreach ( DW_Anim_Presets::all() as $id => $preset ) {
			if ( $engine === $preset['engine'] ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * @param \Elementor\Controls_Stack $element Elemento que recebe os controles.
	 * @param array                    $args    Argumentos da seção anterior.
	 * @return void
	 */
	public function inject( $element, $args ) {
		unset( $args );

		if ( $this->is_atomic( $element ) ) {
			return;
		}

		$k = static function ( $suffix ) {
			return DW_Anim_Keys::ours( $suffix );
		};

		// A chave da animação nativa muda conforme o tipo de elemento.
		$native = in_array( $element->get_name(), [ 'common', 'common-optimized' ], true )
			? DW_Anim_Keys::NATIVE_WIDGET['name']
			: DW_Anim_Keys::NATIVE_BLOCK['name'];

		$element->start_controls_section(
			$k( 'section' ),
			[
				'label' => __( 'DW Animações', 'dw-copiar-animacao' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			]
		);

		$element->add_control(
			$k( 'native_warning' ),
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => '<div class="elementor-control-field-description dw-anim-warning">'
					. esc_html__( 'Este elemento também tem a animação de entrada nativa do Elementor ligada. As duas vão rodar juntas — desligue uma delas.', 'dw-copiar-animacao' )
					. '</div>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				'condition'       => [
					$k( 'preset' ) . '!' => '',
					$native . '!'       => '',
				],
			]
		);

		$element->add_control(
			$k( 'preset' ),
			[
				'label'   => __( 'Animação', 'dw-copiar-animacao' ),
				'type'    => 'dw-anim-picker',
				'default' => '',
			]
		);

		$element->add_control(
			$k( 'actions' ),
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => '<div class="dw-anim-actions">'
					. '<button type="button" class="dw-anim-btn dw-anim-play" data-dw-action="play">' . esc_html__( '▶ Testar', 'dw-copiar-animacao' ) . '</button>'
					. '<button type="button" class="dw-anim-btn" data-dw-action="copy">' . esc_html__( 'Copiar', 'dw-copiar-animacao' ) . '</button>'
					. '<button type="button" class="dw-anim-btn" data-dw-action="paste">' . esc_html__( 'Colar', 'dw-copiar-animacao' ) . '</button>'
					. '<button type="button" class="dw-anim-btn" data-dw-action="save">' . esc_html__( 'Salvar na biblioteca', 'dw-copiar-animacao' ) . '</button>'
					. '</div>',
			]
		);

		$element->add_control(
			$k( 'trigger' ),
			[
				'label'     => __( 'Gatilho', 'dw-copiar-animacao' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'scroll-in',
				'options'   => DW_Anim_Presets::triggers(),
				'condition' => [ $k( 'preset' ) . '!' => '' ],
			]
		);

		$element->add_control(
			$k( 'viewport' ),
			[
				'label'      => __( 'Disparar quando estiver visível (%)', 'dw-copiar-animacao' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%' ],
				'range'      => [ '%' => [ 'min' => 0, 'max' => 100, 'step' => 5 ] ],
				'default'    => [ 'unit' => '%', 'size' => 20 ],
				'condition'  => [
					$k( 'preset' ) . '!' => '',
					$k( 'trigger' )      => [ 'scroll-in', 'scroll-out' ],
				],
			]
		);

		$element->add_control(
			$k( 'once' ),
			[
				'label'        => __( 'Animar só uma vez', 'dw-copiar-animacao' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => [
					$k( 'preset' ) . '!' => '',
					$k( 'trigger' )      => [ 'scroll-in', 'scroll-out' ],
				],
			]
		);

		$element->add_control(
			$k( 'duration' ),
			[
				'label'      => __( 'Duração (ms)', 'dw-copiar-animacao' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 4000, 'step' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 800 ],
				'condition'  => [
					$k( 'preset' ) . '!'  => '',
					$k( 'trigger' ) . '!' => 'scroll-scrub',
				],
			]
		);

		$element->add_control(
			$k( 'delay' ),
			[
				'label'      => __( 'Espera antes de começar (ms)', 'dw-copiar-animacao' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 3000, 'step' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 0 ],
				'condition'  => [
					$k( 'preset' ) . '!'  => '',
					$k( 'trigger' ) . '!' => 'scroll-scrub',
				],
			]
		);

		$easings = [];

		foreach ( DW_Anim_Presets::easings() as $name => $data ) {
			$easings[ $name ] = $data['label'];
		}

		$element->add_control(
			$k( 'easing' ),
			[
				'label'     => __( 'Curva', 'dw-copiar-animacao' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'power2.out',
				'options'   => $easings,
				'condition' => [
					$k( 'preset' ) . '!'  => '',
					$k( 'trigger' ) . '!' => 'scroll-scrub',
				],
			]
		);

		$element->add_control(
			$k( 'distance' ),
			[
				'label'      => __( 'Distância (px)', 'dw-copiar-animacao' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 400, 'step' => 5 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 40 ],
				'condition'  => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'distance' ) ],
			]
		);

		$element->add_control(
			$k( 'scale_from' ),
			[
				'label'     => __( 'Escala inicial', 'dw-copiar-animacao' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 0.1,
				'max'       => 3,
				'step'      => 0.05,
				'default'   => 0.85,
				'condition' => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'scale_from' ) ],
			]
		);

		$element->add_control(
			$k( 'blur' ),
			[
				'label'      => __( 'Desfoque (px)', 'dw-copiar-animacao' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40, 'step' => 1 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 10 ],
				'condition'  => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'blur' ) ],
			]
		);

		$element->add_control(
			$k( 'rotate' ),
			[
				'label'      => __( 'Rotação (graus)', 'dw-copiar-animacao' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'deg' ],
				'range'      => [ 'deg' => [ 'min' => -180, 'max' => 180, 'step' => 1 ] ],
				'default'    => [ 'unit' => 'deg', 'size' => 12 ],
				'condition'  => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'rotate' ) ],
			]
		);

		$element->add_control(
			$k( 'counter_to' ),
			[
				'label'       => __( 'Contar até', 'dw-copiar-animacao' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 100,
				'description' => __( 'O número de partida é o que já está escrito no elemento.', 'dw-copiar-animacao' ),
				'condition'   => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'counter_to' ) ],
			]
		);

		$element->add_control(
			$k( 'scrub_end' ),
			[
				'label'     => __( 'Terminar quando', 'dw-copiar-animacao' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'out',
				'options'   => [
					'center' => __( 'o elemento chegar ao centro da tela', 'dw-copiar-animacao' ),
					'top'    => __( 'o topo do elemento chegar ao topo da tela', 'dw-copiar-animacao' ),
					'out'    => __( 'o elemento sair da tela', 'dw-copiar-animacao' ),
				],
				'condition' => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'scrub_end' ) ],
			]
		);

		$element->add_control(
			$k( 'pin' ),
			[
				'label'        => __( 'Fixar o elemento durante a animação', 'dw-copiar-animacao' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'condition'    => [
					$k( 'preset' ) . '!' => '',
					$k( 'trigger' )      => 'scroll-scrub',
				],
			]
		);

		$element->add_control(
			$k( 'loop' ),
			[
				'label'        => __( 'Repetir sem parar', 'dw-copiar-animacao' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'loop' ) ],
			]
		);

		$element->add_control(
			$k( 'lottie_url' ),
			[
				'label'       => __( 'Arquivo Lottie (.json)', 'dw-copiar-animacao' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => 'https://exemplo.com/animacao.json',
				'description' => __( 'Envie o .json pela Biblioteca de Mídia e cole a URL aqui.', 'dw-copiar-animacao' ),
				'condition'   => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'lottie_url' ) ],
			]
		);

		$element->add_control(
			$k( 'lottie_loop' ),
			[
				'label'        => __( 'Repetir o Lottie', 'dw-copiar-animacao' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'lottie_loop' ) ],
			]
		);

		$element->add_control(
			$k( 'lottie_speed' ),
			[
				'label'     => __( 'Velocidade do Lottie', 'dw-copiar-animacao' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 0.1,
				'max'       => 3,
				'step'      => 0.1,
				'default'   => 1,
				'condition' => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'lottie_speed' ) ],
			]
		);

		$element->add_control(
			$k( 'stagger' ),
			[
				'label'        => __( 'Animar os filhos em cascata', 'dw-copiar-animacao' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => __( 'Em vez de animar o elemento inteiro, anima um filho depois do outro. É o que dá vida a grades, listas e loops.', 'dw-copiar-animacao' ),
				'condition'    => [ $k( 'preset' ) => DW_Anim_Presets::ids_with_param( 'stagger' ) ],
			]
		);

		$element->add_control(
			$k( 'stagger_target' ),
			[
				'label'       => __( 'Filhos a animar', 'dw-copiar-animacao' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '> *',
				'placeholder' => '> *',
				'description' => __( 'Seletor CSS relativo ao elemento. O padrão pega os filhos diretos.', 'dw-copiar-animacao' ),
				'condition'   => [ $k( 'stagger' ) => 'yes' ],
			]
		);

		$cascade_terms = [
			'relation' => 'or',
			'terms'    => [
				[
					'name'  => $k( 'stagger' ),
					'value' => 'yes',
				],
				[
					'name'     => $k( 'preset' ),
					'operator' => 'in',
					'value'    => DW_Anim_Presets::ids_with_param( 'stagger_each' ),
				],
			],
		];

		$element->add_control(
			$k( 'stagger_each' ),
			[
				'label'      => __( 'Intervalo entre cada um (ms)', 'dw-copiar-animacao' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 500, 'step' => 10 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 80 ],
				'conditions' => $cascade_terms,
			]
		);

		$element->add_control(
			$k( 'stagger_from' ),
			[
				'label'      => __( 'Começar pelo', 'dw-copiar-animacao' ),
				'type'       => Controls_Manager::SELECT,
				'default'    => 'start',
				'options'    => [
					'start'  => __( 'primeiro', 'dw-copiar-animacao' ),
					'center' => __( 'meio', 'dw-copiar-animacao' ),
					'end'    => __( 'último', 'dw-copiar-animacao' ),
					'random' => __( 'aleatório', 'dw-copiar-animacao' ),
				],
				'conditions' => $cascade_terms,
			]
		);

		$element->add_control(
			$k( 'off_mobile' ),
			[
				'label'        => __( 'Desligar no celular', 'dw-copiar-animacao' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'separator'    => 'before',
				'condition'    => [ $k( 'preset' ) . '!' => '' ],
			]
		);

		$element->add_control(
			$k( 'engine' ),
			[
				'label'       => __( 'Motor', 'dw-copiar-animacao' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'auto',
				'options'     => [
					'auto'  => __( 'Automático (recomendado)', 'dw-copiar-animacao' ),
					'css'   => __( 'Próprio, sem biblioteca', 'dw-copiar-animacao' ),
					'gsap'  => __( 'GSAP', 'dw-copiar-animacao' ),
					'anime' => __( 'Anime.js', 'dw-copiar-animacao' ),
				],
				'description' => __( 'No automático, cada animação usa o motor mais leve que dá conta. Só mude se souber por quê.', 'dw-copiar-animacao' ),
				'condition'   => [ $k( 'preset' ) => $this->ids_with_engine( 'css' ) ],
			]
		);

		$element->end_controls_section();
	}
}
