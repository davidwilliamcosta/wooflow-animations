<?php
/**
 * Injeta a seção "WooFlow Animations" na PRIMEIRA aba de cada elemento.
 *
 * São duas estratégias, por um motivo medido: os controles de `common` são
 * anexados ao FIM do stack de cada widget (`Widget_Base::get_stack()`), então
 * uma seção registrada lá nunca alcança o topo da primeira aba. Registrar em
 * cada widget resolveria a posição, mas são 367 tipos de widget numa instalação
 * com o Pro — 2 MB a mais na configuração do editor, para 5,8 KB de controles.
 *
 * Então: bloco (container, seção, coluna) recebe a seção no topo da aba Layout,
 * antes da primeira seção nativa; widget recebe uma vez só, via `common`, na aba
 * Conteúdo, logo abaixo dos controles próprios dele.
 *
 * Ver CLAUDE.md, regra 2.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class WFAN_Controls {

	/**
	 * Stacks de widget, onde a seção entra uma vez só e vale para todos.
	 *
	 * @var string[]
	 */
	const WIDGET_STACKS = [ 'common', 'common-optimized' ];

	/**
	 * Primeira seção de cada bloco — é antes dela que a nossa entra, para ficar
	 * no topo da aba Layout. Ids confirmados no Elementor 4.3.4.
	 *
	 * @var array<string,string>
	 */
	const BLOCK_FIRST_SECTION = [
		'container' => 'section_layout_container',
		'section'   => 'section_layout',
		'column'    => 'layout',
	];

	/**
	 * Alvos da inversão de cor no hover, relativos ao elemento.
	 *
	 * Quem escreve essas regras é o próprio Elementor, pelos `selectors` dos
	 * controles de cor — e não um CSS estático do plugin — porque o que decide
	 * aqui é especificidade: a cor de cada widget sai em
	 * `.elementor-{post} .elementor-element.elementor-element-{id} …` (0,3,0) e
	 * só um seletor montado sobre `{{WRAPPER}}:hover` ganha dela sem
	 * `!important`. Quanto acima de 0,3,0 é preciso estar, e por quê, está em
	 * SPECIFICITY_BUMP.
	 *
	 * @var array<string,string[]>
	 */
	/**
	 * Degraus de especificidade colados em todo seletor da inversão.
	 *
	 * `:nth-child(n)` casa com qualquer elemento, em qualquer posição: não muda
	 * nada do que o seletor pega, só o peso dele. Está aqui porque empate de
	 * especificidade é decidido pela ORDEM no arquivo — e a ordem das nossas
	 * regras MUDA conforme o contexto, o que fazia o defeito parecer que não
	 * existia:
	 *
	 *  - no front-end, `Controls_Manager::add_control_to_stack()` desvia todo
	 *    controle com `selectors` para um balde à parte
	 *    (`Performance::should_optimize_controls()`), e `get_controls()` o
	 *    anexa no FIM da lista (`$controls += $stack['style_controls']`). As
	 *    nossas regras saem por último e ganham qualquer empate;
	 *  - no editor e na requisição que SALVA a página — que é quem gera o
	 *    arquivo CSS que a página publicada carrega — não há desvio nenhum, e
	 *    os controles saem na ordem da pilha. A nossa seção está no topo da
	 *    primeira aba (regra 2), então as nossas regras saem PRIMEIRO.
	 *
	 * Medido no Elementor 4.3.4, num container com cor de fundo: a nossa
	 * `{{WRAPPER}}:hover{background-color}` (0,4,0) saía antes da cor de fundo
	 * do próprio container,
	 * `{{WRAPPER}}:not(.elementor-motion-effects-element-type-background)`
	 * (0,4,0 também) — e perdia o empate. O fundo não invertia em quadro
	 * nenhum, enquanto o título (0,5,0) invertia: texto branco sobre fundo que
	 * continuava branco.
	 *
	 * Dois degraus, e não um, porque um só empataria de novo com o que o
	 * Elementor escreve por widget com uma classe a mais — a cor do ícone sai
	 * em `{{WRAPPER}}.elementor-view-default .elementor-icon` (0,5,0).
	 */
	const SPECIFICITY_BUMP = ':nth-child(n):nth-child(n)';

	/**
	 * Classe que liga a inversão sem mouse nenhum.
	 *
	 * Existe porque `:hover` não se simula: o ▶ Testar não teria o que fazer, e
	 * o botão ficaria mentindo. Toda regra de cor sai nos dois estados, e o
	 * core.js só precisa pendurar esta classe no elemento. Serve também para
	 * deixar um item já invertido — o "ativo" de uma lista.
	 */
	const HOVER_PREVIEW_CLASS = 'wfan-hv-on';

	/**
	 * Classe do elemento entregue à animação de entrada do Elementor.
	 *
	 * Serve de âncora para a duração e a curva exatas, que o painel nativo não
	 * oferece: a regra nasce em `{{WRAPPER}}.wfan-native.animated` e ganha de
	 * `.animated{animation-duration:1.25s}` do Elementor sem `!important`.
	 * Presa ao preset, e não ao `{{WRAPPER}}` sozinho, para não vazar para a
	 * animação de entrada que o usuário tenha escolhido à mão.
	 *
	 * O mesmo nome é usado pelo core.js (▶ Testar) e pelo resgate do
	 * `WFAN_Assets::print_prehide()`.
	 */
	const NATIVE_CLASS = 'wfan-native';

	const HOVER_TARGETS = [
		'bg'     => [ '' ],
		'title'  => [ '.elementor-heading-title' ],
		'accent' => [ '> .elementor-widget-heading .elementor-heading-title' ],
		'text'   => [
			'.elementor-widget-text-editor',
			'.elementor-widget-text-editor p',
			'.elementor-widget-text-editor li',
		],
		'icon'   => [
			'.elementor-icon',
			'svg',
			'svg *',
		],
	];

	/**
	 * O que cada cor escreve.
	 *
	 * O ícone precisa de `fill` além de `color`: ícone de fonte obedece à cor
	 * herdada, mas SVG com `fill` próprio no arquivo ignora `color` — e um
	 * `fill` no `<svg>` não alcança um `<path fill="…">`, que é presentation
	 * attribute e ganha da herança. Daí o `svg *`.
	 *
	 * @var array<string,string>
	 */
	const HOVER_PROPERTY = [
		'bg'     => 'background-color: {{VALUE}};',
		'title'  => 'color: {{VALUE}};',
		'accent' => 'color: {{VALUE}};',
		'text'   => 'color: {{VALUE}};',
		'icon'   => 'color: {{VALUE}}; fill: {{VALUE}};',
	];

	public function __construct() {
		foreach ( self::WIDGET_STACKS as $stack ) {
			add_action( "elementor/element/{$stack}/section_effects/after_section_end", [ $this, 'inject_widget' ], 10, 2 );
		}

		foreach ( self::BLOCK_FIRST_SECTION as $type => $section ) {
			add_action( "elementor/element/{$type}/{$section}/before_section_start", [ $this, 'inject_block' ], 10, 2 );
		}
	}

	/**
	 * Widget: aba Conteúdo, que é a primeira dele.
	 *
	 * @param \Elementor\Controls_Stack $element Elemento.
	 * @param array                    $args    Argumentos da seção vizinha.
	 * @return void
	 */
	public function inject_widget( $element, $args ) {
		unset( $args );

		$this->inject( $element, Controls_Manager::TAB_CONTENT );
	}

	/**
	 * Container, seção e coluna: topo da aba Layout, que é a primeira deles.
	 *
	 * @param \Elementor\Controls_Stack $element Elemento.
	 * @param array                    $args    Argumentos da seção vizinha.
	 * @return void
	 */
	public function inject_block( $element, $args ) {
		unset( $args );

		$this->inject( $element, Controls_Manager::TAB_LAYOUT );
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
	 * O stack que está sendo montado agora já tem a nossa seção?
	 *
	 * Com o experimento `e_optimized_markup` ligado, o stack `common-optimized`
	 * dispara também os ganchos de `common`
	 * (`Controls_Stack::should_manually_trigger_common_action()`), então o mesmo
	 * stack chegaria aqui duas vezes e os ids de controle seriam registrados em
	 * dobro.
	 *
	 * A resposta vem do próprio stack, e não de uma lista nossa de "já feitos".
	 * O Elementor APAGA e remonta o stack de um tipo dentro da mesma requisição
	 * quando ele foi montado num `responsive_control_duplication_mode` mais
	 * baixo que o pedido agora (`Controls_Manager::should_clean_stack()`) — e a
	 * geração do CSS da página pede `on`, enquanto o render do front-end monta em
	 * `off`. Um container renderizado antes (cabeçalho, item de loop) deixava a
	 * lista marcada, a remontagem saía sem a nossa seção e o `post-<id>.css`
	 * nascia sem nenhuma regra nossa: a inversão no hover e a duração das
	 * animações nativas sumiam do site publicado, intermitente conforme a
	 * ordem da requisição que regenerou o arquivo.
	 *
	 * `get_control_from_stack()` lê o stack sem limpar nem remontar — ao contrário
	 * de `get_controls()`, que chamado aqui dentro entraria em recursão.
	 *
	 * Ver CLAUDE.md, regra 2.
	 *
	 * @param \Elementor\Controls_Stack $element Elemento.
	 * @return bool
	 */
	private function is_injected( $element ) {
		$found = \Elementor\Plugin::$instance->controls_manager->get_control_from_stack(
			$element->get_unique_name(),
			WFAN_Keys::ours( 'section' )
		);

		return is_array( $found );
	}

	/**
	 * Ids de preset cujo motor é o informado. Os resolvidos só em CSS ficam de
	 * fora: trocar o motor deles não muda nada, e a opção só confundiria.
	 *
	 * @param string $engine Motor.
	 * @return string[]
	 */
	private function ids_with_engine( $engine ) {
		$ids = [];

		foreach ( WFAN_Presets::all() as $id => $preset ) {
			if ( $engine === $preset['engine'] && empty( $preset['css_only'] ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Monta um seletor do Elementor a partir dos alvos relativos.
	 *
	 * Em estado de hover o seletor sai duas vezes, uma por `:hover` e outra pela
	 * classe de preview. Lista separada por vírgula, e não `:is()`: a mesma
	 * especificidade, sem depender de um seletor que navegador antigo descarta
	 * — e seletor inválido derruba a regra inteira, não só o ramo dele.
	 *
	 * Todo ramo — inclusive o do estado normal, que é onde a transição mora —
	 * nasce com os degraus de SPECIFICITY_BUMP, porque nenhuma destas regras
	 * pode depender de sair antes ou depois das do Elementor no arquivo.
	 *
	 * @param string[] $targets Alvos relativos ao elemento ('' = o próprio).
	 * @param bool     $hover   Prende o seletor ao estado invertido.
	 * @return string
	 */
	private function scoped( $targets, $hover = false ) {
		$roots = $hover
			? [ '{{WRAPPER}}:hover', '{{WRAPPER}}.' . self::HOVER_PREVIEW_CLASS ]
			: [ '{{WRAPPER}}' ];

		$out = [];

		foreach ( $roots as $root ) {
			$root .= self::SPECIFICITY_BUMP;

			foreach ( $targets as $target ) {
				$out[] = '' === $target ? $root : $root . ' ' . $target;
			}
		}

		return implode( ', ', $out );
	}

	/**
	 * Registra um controle nosso, decidindo o `render_type` por ele.
	 *
	 * Esta é a diferença entre um painel que responde e um que trava. Controle
	 * sem `render_type` declarado e que não seja de estilo faz o Elementor
	 * marcar `isContentChanged` e re-renderizar o WIDGET INTEIRO por Ajax a
	 * cada mexida (`BaseElementView.renderChanges()`) — arrastar o slider de
	 * duração disparava uma requisição por parada do mouse, e o canvas piscava
	 * em cima do preview que a gente acabara de tocar.
	 *
	 * Nenhum controle nosso muda o HTML do elemento: o contrato é escrito no
	 * servidor e, no canvas, o Elementor nem o lê (regra 19). Então:
	 *  - com `selectors`, `ui` — o Elementor só reescreve o CSS do elemento,
	 *    que é justamente o que o preset de estado e o fundo animado precisam;
	 *  - sem `selectors`, `none` — nada é re-renderizado, e quem mostra o
	 *    resultado é o preview automático do painel.
	 *
	 * É o mesmo que o Elementor faz com os controles da animação de entrada
	 * dele (`Control_Animation::get_default_settings()`).
	 *
	 * @param \Elementor\Controls_Stack $element Elemento.
	 * @param string                    $id      Id do controle.
	 * @param array                     $args    Argumentos.
	 * @return void
	 */
	private function add( $element, $id, $args ) {
		if ( ! isset( $args['render_type'] ) ) {
			$args['render_type'] = isset( $args['selectors'] ) ? 'ui' : 'none';
		}

		$element->add_control( $id, $args );
	}

	/**
	 * Controle de cor de um dos alvos da inversão no hover.
	 *
	 * A cor vai sozinha nos `selectors`: declaração estática junto viraria lixo
	 * se o usuário escolher uma cor global, porque o Elementor troca tudo depois
	 * do primeiro `:` pelo valor global (`Base::add_control_rules()`). A
	 * transição, por isso, é escrita só pelo controle de velocidade.
	 *
	 * @param \Elementor\Controls_Stack $element  Elemento.
	 * @param string                    $slot     Chave em HOVER_TARGETS.
	 * @param array                     $args     Rótulo, padrão e descrição.
	 * @return void
	 */
	private function add_hover_color( $element, $slot, $args ) {
		$this->add(
			$element,
			WFAN_Keys::ours( 'hover_' . $slot ),
			array_merge(
				[
					'type'      => Controls_Manager::COLOR,
					'selectors' => [
						$this->scoped( self::HOVER_TARGETS[ $slot ], true ) => self::HOVER_PROPERTY[ $slot ],
					],
					'condition' => [ WFAN_Keys::ours( 'preset' ) => WFAN_Presets::ids_with_param( 'hover_colors' ) ],
				],
				$args
			)
		);
	}

	/**
	 * @param \Elementor\Controls_Stack $element Elemento que recebe os controles.
	 * @param string                   $tab     Aba onde a seção aparece.
	 * @return void
	 */
	public function inject( $element, $tab ) {
		if ( $this->is_atomic( $element ) ) {
			return;
		}

		if ( $this->is_injected( $element ) ) {
			return;
		}

		/**
		 * Aba em que a seção WooFlow Animations aparece.
		 *
		 * @param string                   $tab     Aba padrão (a primeira do elemento).
		 * @param \Elementor\Controls_Stack $element Elemento.
		 */
		$tab = apply_filters( 'wfan_controls_tab', $tab, $element );

		$k = static function ( $suffix ) {
			return WFAN_Keys::ours( $suffix );
		};

		// Lista para as condições negativas dos controles que só o JS obedece:
		// sem preset, ou com um preset resolvido só em CSS, eles não aparecem.
		$timed = array_merge( [ '' ], WFAN_Presets::ids_css_only() );

		// Preset nativo é tocado pelo próprio Elementor: ponto de disparo e
		// "animar só uma vez" não são ajustáveis lá, e controle que não é
		// obedecido é configuração que não faz nada. Ver CLAUDE.md, regra 21.
		$native_ids = WFAN_Presets::ids_native();
		$own_engine = array_merge( $timed, $native_ids );

		// Marquee é contínuo: não tem começo, fim, curva nem ponto de disparo.
		// Duração, espera, curva, gatilho, "visível (%)" e "só uma vez" saem do
		// painel, e `off_mobile` continua valendo (o core.js é quem obedece).
		$mq_ids  = WFAN_Presets::ids_marquee();
		$no_line = array_merge( $timed, $mq_ids );
		$no_trig = array_merge( [ '' ], $native_ids, $mq_ids );

		// A chave da animação nativa muda conforme o tipo de elemento.
		$native = in_array( $element->get_name(), [ 'common', 'common-optimized' ], true )
			? WFAN_Keys::NATIVE_WIDGET['name']
			: WFAN_Keys::NATIVE_BLOCK['name'];

		$element->start_controls_section(
			$k( 'section' ),
			[
				'label' => __( 'WooFlow Animations', 'wooflow-animations' ),
				'tab'   => $tab,
			]
		);

		$this->add(
			$element,
			$k( 'native_warning' ),
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => '<div class="elementor-control-field-description wfan-warning">'
					. esc_html__( 'Este elemento também tem a animação de entrada nativa do Elementor ligada. As duas vão rodar juntas — desligue uma delas.', 'wooflow-animations' )
					. '</div>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				// Preset de estado não briga com a animação de entrada nativa,
				// e preset nativo *é* ela — nos dois casos o aviso seria alarme
				// falso.
				'condition'       => [
					$k( 'preset' ) . '!' => $own_engine,
					$native . '!'       => '',
				],
			]
		);

		$this->add(
			$element,
			$k( 'preset' ),
			[
				'label'   => __( 'Animação', 'wooflow-animations' ),
				'type'    => 'wfan-picker',
				'default' => '',
			]
		);

		$this->add(
			$element,
			$k( 'native_note' ),
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => '<div class="wfan-note">'
					. esc_html__( 'Animação do próprio Elementor: nenhum JavaScript do WooFlow entra na página. Ela ocupa o campo Avançado → Efeitos de movimento → Animação de entrada deste elemento.', 'wooflow-animations' )
					. '</div>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => [ $k( 'preset' ) => $native_ids ],
			]
		);

		$this->add(
			$element,
			$k( 'actions' ),
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => '<div class="wfan-actions">'
					. '<button type="button" class="wfan-btn wfan-play" data-wfan-action="play">' . esc_html__( '▶ Testar', 'wooflow-animations' ) . '</button>'
					. '<button type="button" class="wfan-btn" data-wfan-action="copy">' . esc_html__( 'Copiar', 'wooflow-animations' ) . '</button>'
					. '<button type="button" class="wfan-btn" data-wfan-action="paste">' . esc_html__( 'Colar', 'wooflow-animations' ) . '</button>'
					. '<button type="button" class="wfan-btn" data-wfan-action="save">' . esc_html__( 'Salvar na biblioteca', 'wooflow-animations' ) . '</button>'
					. '</div>',
			]
		);

		$this->add(
			$element,
			$k( 'trigger' ),
			[
				'label'     => __( 'Gatilho', 'wooflow-animations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'scroll-in',
				'options'   => WFAN_Presets::triggers(),
				'condition' => [ $k( 'preset' ) . '!' => array_merge( [ '' ], $mq_ids ) ],
			]
		);

		$this->add(
			$element,
			$k( 'viewport' ),
			[
				'label'      => __( 'Disparar quando estiver visível (%)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%' ],
				'range'      => [ '%' => [ 'min' => 0, 'max' => 100, 'step' => 5 ] ],
				'default'    => [ 'unit' => '%', 'size' => 20 ],
				'condition'  => [
					$k( 'preset' ) . '!' => $no_trig,
					$k( 'trigger' )      => [ 'scroll-in', 'scroll-out' ],
				],
			]
		);

		$this->add(
			$element,
			$k( 'once' ),
			[
				'label'        => __( 'Animar só uma vez', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				// O nativo só toca uma vez, e não há como pedir o contrário.
				'condition'    => [
					$k( 'preset' ) . '!' => $no_trig,
					$k( 'trigger' )      => [ 'scroll-in', 'scroll-out' ],
				],
			]
		);

		$this->add(
			$element,
			$k( 'duration' ),
			[
				'label'      => __( 'Duração (ms)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 4000, 'step' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 800 ],
				// O nativo só oferece lento/normal/rápido no painel do
				// Elementor; aqui o número é exato, porque a declaração sai
				// no CSS que o próprio Elementor gera para este elemento. A
				// classe `.wfan-native` é o que prende a regra ao preset
				// nativo: sem ela, a duração vazaria para a animação de
				// entrada que o usuário tenha escolhido à mão.
				'selectors'  => [
					'{{WRAPPER}}.' . self::NATIVE_CLASS . '.animated' => 'animation-duration: {{SIZE}}ms;',
				],
				'condition'  => [
					$k( 'preset' ) . '!'  => $no_line,
					$k( 'trigger' ) . '!' => 'scroll-scrub',
				],
			]
		);

		$this->add(
			$element,
			$k( 'delay' ),
			[
				'label'      => __( 'Espera antes de começar (ms)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 3000, 'step' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 0 ],
				'condition'  => [
					$k( 'preset' ) . '!'  => $no_line,
					$k( 'trigger' ) . '!' => 'scroll-scrub',
				],
			]
		);

		$easings = [];
		$beziers = [];

		foreach ( WFAN_Presets::easings() as $name => $data ) {
			$easings[ $name ] = $data['label'];
			$beziers[ $name ] = $data['bezier'];
		}

		$this->add(
			$element,
			$k( 'easing' ),
			[
				'label'               => __( 'Curva', 'wooflow-animations' ),
				'type'                => Controls_Manager::SELECT,
				'default'             => 'power2.out',
				'options'             => $easings,
				// O valor gravado é o nome GSAP, que não diz nada ao CSS: o
				// dicionário é quem traduz para o cubic-bezier na hora de
				// escrever a regra do preset nativo.
				'selectors_dictionary' => $beziers,
				'selectors'           => [
					'{{WRAPPER}}.' . self::NATIVE_CLASS . '.animated' => 'animation-timing-function: {{VALUE}};',
				],
				'condition'           => [
					$k( 'preset' ) . '!'  => $no_line,
					$k( 'trigger' ) . '!' => 'scroll-scrub',
				],
			]
		);

		/*
		 * Inversão de cor no hover. É um estado, não uma linha do tempo: quem
		 * desenha é o CSS que o Elementor gera a partir destes `selectors`, e
		 * nenhum motor sai do rodapé por causa dele.
		 */
		$this->add(
			$element,
			$k( 'hover_dur' ),
			[
				'label'      => __( 'Velocidade da transição (ms)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 2000, 'step' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 300 ],
				'selectors'  => [
					$this->scoped(
						array_merge(
							self::HOVER_TARGETS['bg'],
							self::HOVER_TARGETS['title'],
							self::HOVER_TARGETS['text'],
							self::HOVER_TARGETS['icon']
						)
					) => 'transition-property: background-color, color, fill; transition-duration: {{SIZE}}ms; transition-timing-function: cubic-bezier(0.33, 1, 0.68, 1);',
				],
				'condition'  => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'hover_colors' ) ],
			]
		);

		$this->add_hover_color(
			$element,
			'bg',
			[
				'label'   => __( 'Cor de fundo no hover', 'wooflow-animations' ),
				'default' => '#1A1A1A',
			]
		);

		$this->add_hover_color(
			$element,
			'title',
			[
				'label'   => __( 'Cor dos títulos no hover', 'wooflow-animations' ),
				'default' => '#FFFFFF',
			]
		);

		$this->add_hover_color(
			$element,
			'accent',
			[
				'label'       => __( 'Cor do número ou rótulo', 'wooflow-animations' ),
				'description' => __( 'Pinta só os títulos que estão direto no bloco — é onde costuma ficar o número ou o rótulo do item. Deixe vazio se não houver.', 'wooflow-animations' ),
			]
		);

		$this->add_hover_color(
			$element,
			'text',
			[
				'label'   => __( 'Cor dos textos no hover', 'wooflow-animations' ),
				'default' => '#FFFFFF',
			]
		);

		$this->add_hover_color(
			$element,
			'icon',
			[
				'label'       => __( 'Cor dos ícones e SVG', 'wooflow-animations' ),
				'description' => __( 'Vale para ícone de fonte e para SVG, inclusive o que traz a cor dentro do arquivo.', 'wooflow-animations' ),
			]
		);

		$this->add(
			$element,
			$k( 'distance' ),
			[
				'label'      => __( 'Distância (px)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 400, 'step' => 5 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 40 ],
				'condition'  => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'distance' ) ],
			]
		);

		/*
		 * Sentido do parallax. De fábrica o elemento anda junto com a rolagem
		 * (desce no vertical, vai para a direita no horizontal); invertido, ele
		 * anda contra ela — é o que dá a sensação de camada mais próxima.
		 */
		$this->add(
			$element,
			$k( 'reverse' ),
			[
				'label'        => __( 'Inverter direção', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => __( 'Ligado, o elemento anda contra a rolagem: sobe no vertical, vai para a esquerda no horizontal.', 'wooflow-animations' ),
				'condition'    => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'reverse' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'scale_from' ),
			[
				'label'     => __( 'Escala inicial', 'wooflow-animations' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 0.1,
				'max'       => 3,
				'step'      => 0.05,
				'default'   => 0.85,
				'condition' => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'scale_from' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'blur' ),
			[
				'label'      => __( 'Desfoque (px)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 40, 'step' => 1 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 10 ],
				'condition'  => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'blur' ) ],
			]
		);

		/*
		 * Opacidade de partida da revelação travada no scroll. É um número
		 * puro de 0 a 1, e não um slider com unidade: `%` viraria 20 no
		 * payload e o GSAP animaria a opacidade de 20 até 1, ou seja, de
		 * opaco até opaco.
		 */
		$this->add(
			$element,
			$k( 'dim' ),
			[
				'label'       => __( 'Opacidade de partida', 'wooflow-animations' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ '' ],
				'range'       => [ '' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ] ],
				'default'     => [ 'unit' => '', 'size' => 0.2 ],
				'description' => __( 'Quanto do texto já se vê antes de a rolagem revelar.', 'wooflow-animations' ),
				'condition'   => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'dim' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'rotate' ),
			[
				'label'      => __( 'Rotação (graus)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'deg' ],
				'range'      => [ 'deg' => [ 'min' => -180, 'max' => 180, 'step' => 1 ] ],
				'default'    => [ 'unit' => 'deg', 'size' => 12 ],
				'condition'  => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'rotate' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'counter_to' ),
			[
				'label'       => __( 'Contar até', 'wooflow-animations' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 100,
				'description' => __( 'O número de partida é o que já está escrito no elemento.', 'wooflow-animations' ),
				'condition'   => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'counter_to' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'scrub_start' ),
			[
				'label'     => __( 'Começar quando', 'wooflow-animations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'enter',
				'options'   => [
					'enter'  => __( 'o elemento entrar na tela', 'wooflow-animations' ),
					'quarter' => __( 'o elemento estiver a um quarto da tela', 'wooflow-animations' ),
					'center' => __( 'o elemento chegar ao centro da tela', 'wooflow-animations' ),
					'top'    => __( 'o topo do elemento chegar ao topo da tela', 'wooflow-animations' ),
				],
				'condition' => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'scrub_end' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'scrub_end' ),
			[
				'label'     => __( 'Terminar quando', 'wooflow-animations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'out',
				'options'   => [
					'center' => __( 'o elemento chegar ao centro da tela', 'wooflow-animations' ),
					'top'    => __( 'o topo do elemento chegar ao topo da tela', 'wooflow-animations' ),
					'out'    => __( 'o elemento sair da tela', 'wooflow-animations' ),
					'pct'    => __( 'a página rolar uma porcentagem da tela', 'wooflow-animations' ),
				],
				'condition' => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'scrub_end' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'scrub_pct' ),
			[
				'label'       => __( 'Porcentagem da tela rolada', 'wooflow-animations' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ '%' ],
				'range'       => [ '%' => [ 'min' => 10, 'max' => 400, 'step' => 10 ] ],
				'default'     => [ 'unit' => '%', 'size' => 100 ],
				'description' => __( 'Contada a partir do ponto de início: 100% é uma tela inteira de rolagem até a animação se completar.', 'wooflow-animations' ),
				'condition'   => [
					$k( 'preset' )    => WFAN_Presets::ids_with_param( 'scrub_end' ),
					$k( 'scrub_end' ) => 'pct',
				],
			]
		);

		/*
		 * A revelação pedaço por pedaço não usa o par "começar/terminar
		 * quando": ela precisa que a frente de luz fique PARADA na tela
		 * enquanto o texto passa por ela, e isso só sai de um par de âncoras
		 * na mesma altura (`top <linha>` … `bottom <linha>`). Com o par
		 * genérico o percurso era outro — com o padrão de fábrica, "o
		 * elemento entrar na tela" até "o topo chegar ao topo", o texto
		 * terminava de acender quando o bloco ainda subia, antes de dar para
		 * ler. Daí um controle só, e a medida que importa: a altura da linha.
		 */
		$this->add(
			$element,
			$k( 'scrub_line' ),
			[
				'label'       => __( 'Onde a palavra acende', 'wooflow-animations' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ '%' ],
				'range'       => [ '%' => [ 'min' => 10, 'max' => 100, 'step' => 5 ] ],
				'default'     => [ 'unit' => '%', 'size' => 80 ],
				'description' => __( 'Altura da linha em que cada pedaço acende, medida do topo da tela. Em 80% o texto acende um pouco antes de chegar ao meio, subindo — quanto menor, mais cedo tudo já está aceso.', 'wooflow-animations' ),
				'condition'   => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'line' ) ],
			]
		);

		/*
		 * Velocidade da revelação: quanta rolagem o texto leva para acender
		 * por inteiro. 65 é o ponto em que o percurso vale a altura do
		 * elemento (a medida de antes deste controle); mais que isso acende
		 * com menos rolagem, menos que isso estica. É a escala do campo
		 * equivalente do Uncode, que a referência usa com 65 de fábrica.
		 */
		$this->add(
			$element,
			$k( 'scrub_speed' ),
			[
				'label'       => __( 'Velocidade da revelação', 'wooflow-animations' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ '' ],
				'range'       => [ '' => [ 'min' => 10, 'max' => 100, 'step' => 5 ] ],
				'default'     => [ 'unit' => '', 'size' => 65 ],
				'description' => __( 'Quanto maior, menos rolagem o texto precisa para acender por inteiro. Em 65 ele termina de acender quando a última linha chega à altura escolhida acima.', 'wooflow-animations' ),
				'condition'   => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'line' ) ],
			]
		);

		/*
		 * "Virar cartão no scroll". Percurso próprio, e não o par
		 * "começar/terminar quando": o efeito de referência começa quando o
		 * topo do bloco encosta no topo da tela (ao sair) ou quando ele
		 * aparece no pé da tela (ao entrar), e dura uma fração fixa da
		 * altura da tela — não uma âncora do próprio elemento, que numa
		 * seção alta esticaria o efeito por várias telas.
		 */
		$card = [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'card' ) ];

		$this->add(
			$element,
			$k( 'card_state' ),
			[
				'label'     => __( 'Quando acontece', 'wooflow-animations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'out',
				'options'   => [
					'out' => __( 'Ao sair: encolhe e arredonda', 'wooflow-animations' ),
					'in'  => __( 'Ao entrar: chega como cartão e se abre', 'wooflow-animations' ),
				],
				'condition' => $card,
			]
		);

		$this->add(
			$element,
			$k( 'card_scale' ),
			[
				'label'      => __( 'Escala do cartão', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%' ],
				'range'      => [ '%' => [ 'min' => 50, 'max' => 100, 'step' => 1 ] ],
				'default'    => [ 'unit' => '%', 'size' => 90 ],
				'condition'  => $card,
			]
		);

		$this->add(
			$element,
			$k( 'card_radius' ),
			[
				'label'       => __( 'Arredondamento dos cantos (px)', 'wooflow-animations' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 200, 'step' => 1 ] ],
				'default'     => [ 'unit' => 'px', 'size' => 54 ],
				'description' => __( 'Num container, o fundo, a sobreposição e o vídeo de fundo arredondam juntos.', 'wooflow-animations' ),
				'condition'   => $card,
			]
		);

		$this->add(
			$element,
			$k( 'card_origin' ),
			[
				'label'     => __( 'Encolher em direção a', 'wooflow-animations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'bottom center',
				'options'   => [
					'bottom center' => __( 'base (o topo desce)', 'wooflow-animations' ),
					'center center' => __( 'centro', 'wooflow-animations' ),
					'top center'    => __( 'topo (a base sobe)', 'wooflow-animations' ),
				],
				'condition' => $card,
			]
		);

		$this->add(
			$element,
			$k( 'card_length' ),
			[
				'label'       => __( 'Percurso (% da tela)', 'wooflow-animations' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ '%' ],
				'range'       => [ '%' => [ 'min' => 20, 'max' => 400, 'step' => 10 ] ],
				'default'     => [ 'unit' => '%', 'size' => 150 ],
				'description' => __( 'Quanta rolagem o efeito leva para se completar. 100% é uma tela inteira.', 'wooflow-animations' ),
				'condition'   => $card,
			]
		);

		/*
		 * Marquee. Tudo é lido pelo `engine-marquee.js`; nenhum controle aqui
		 * muda o HTML do elemento (regra 22), então o `add()` resolve o
		 * `render_type` sozinho. A velocidade e a intensidade da rolagem são
		 * controles separados porque cada preset usa um só deles.
		 */
		$mq = [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'mq' ) ];

		$this->add(
			$element,
			$k( 'mq_dir' ),
			[
				'label'     => __( 'Direção', 'wooflow-animations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'left',
				'options'   => [
					'left'  => __( 'Da direita para a esquerda', 'wooflow-animations' ),
					'right' => __( 'Da esquerda para a direita', 'wooflow-animations' ),
				],
				'condition' => $mq,
			]
		);

		$this->add(
			$element,
			$k( 'mq_speed' ),
			[
				'label'      => __( 'Velocidade (px por segundo)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 10, 'max' => 600, 'step' => 5 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 100 ],
				'condition'  => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'mq_speed' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'mq_force' ),
			[
				'label'       => __( 'Intensidade da rolagem (%)', 'wooflow-animations' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ '%' ],
				'range'       => [ '%' => [ 'min' => 10, 'max' => 400, 'step' => 5 ] ],
				'default'     => [ 'unit' => '%', 'size' => 100 ],
				'description' => __( '100% anda um pixel de marquee por pixel de rolagem. Só vale com "Preencher a largura toda" ligado; desligado, o texto cruza a tela uma vez enquanto o elemento a atravessa.', 'wooflow-animations' ),
				'condition'   => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'mq_force' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'mq_space' ),
			[
				'label'       => __( 'Espaço entre repetições (px)', 'wooflow-animations' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 400, 'step' => 4 ] ],
				'default'     => [ 'unit' => 'px', 'size' => 48 ],
				'description' => __( 'Vale também entre os itens, quando o marquee é de um bloco com vários filhos.', 'wooflow-animations' ),
				'condition'   => $mq,
			]
		);

		$this->add(
			$element,
			$k( 'mq_edge' ),
			[
				'label'        => __( 'Preencher a largura toda', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => __( 'Repete o conteúdo até cobrir a largura, sem buraco no loop. Desligado, ele atravessa o elemento uma vez e recomeça.', 'wooflow-animations' ),
				'condition'    => $mq,
			]
		);

		$this->add(
			$element,
			$k( 'mq_slow' ),
			[
				'label'        => __( 'Desacelerar ao passar o mouse', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => __( 'Útil quando há links no marquee e o usuário precisa clicar.', 'wooflow-animations' ),
				'condition'    => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'mq_slow' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'mq_freeze' ),
			[
				'label'        => __( 'Congelar (parado)', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => __( 'Mantém a repetição de borda a borda, sem movimento.', 'wooflow-animations' ),
				'condition'    => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'mq_freeze' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'mq_drag' ),
			[
				'label'        => __( 'Arrastável', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => __( 'O visitante pode arrastar o marquee com o mouse ou o dedo.', 'wooflow-animations' ),
				'condition'    => $mq,
			]
		);

		$this->add(
			$element,
			$k( 'pin' ),
			[
				'label'        => __( 'Fixar o elemento durante a animação', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'condition'    => [
					$k( 'preset' ) . '!' => '',
					$k( 'trigger' )      => 'scroll-scrub',
				],
			]
		);

		$this->add(
			$element,
			$k( 'loop' ),
			[
				'label'        => __( 'Repetir sem parar', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'loop' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'lottie_url' ),
			[
				'label'       => __( 'Arquivo Lottie (.json)', 'wooflow-animations' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => 'https://exemplo.com/animacao.json',
				'description' => __( 'Envie o .json pela Biblioteca de Mídia e cole a URL aqui.', 'wooflow-animations' ),
				'condition'   => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'lottie_url' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'lottie_loop' ),
			[
				'label'        => __( 'Repetir o Lottie', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'lottie_loop' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'lottie_speed' ),
			[
				'label'     => __( 'Velocidade do Lottie', 'wooflow-animations' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 0.1,
				'max'       => 3,
				'step'      => 0.1,
				'default'   => 1,
				'condition' => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'lottie_speed' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'stagger' ),
			[
				'label'        => __( 'Animar os filhos em cascata', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => __( 'Em vez de animar o elemento inteiro, anima um filho depois do outro. É o que dá vida a grades, listas e loops.', 'wooflow-animations' ),
				'condition'    => [ $k( 'preset' ) => WFAN_Presets::ids_with_param( 'stagger' ) ],
			]
		);

		$this->add(
			$element,
			$k( 'stagger_target' ),
			[
				'label'       => __( 'Filhos a animar', 'wooflow-animations' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '> *',
				'placeholder' => '> *',
				'description' => __( 'Seletor CSS relativo ao elemento. O padrão pega os filhos diretos.', 'wooflow-animations' ),
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
					'value'    => WFAN_Presets::ids_with_param( 'stagger_each' ),
				],
			],
		];

		$this->add(
			$element,
			$k( 'stagger_each' ),
			[
				'label'      => __( 'Intervalo entre cada um (ms)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 500, 'step' => 10 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 80 ],
				'conditions' => $cascade_terms,
			]
		);

		$this->add(
			$element,
			$k( 'stagger_from' ),
			[
				'label'      => __( 'Começar pelo', 'wooflow-animations' ),
				'type'       => Controls_Manager::SELECT,
				'default'    => 'start',
				'options'    => [
					'start'  => __( 'primeiro', 'wooflow-animations' ),
					'center' => __( 'meio', 'wooflow-animations' ),
					'end'    => __( 'último', 'wooflow-animations' ),
					'random' => __( 'aleatório', 'wooflow-animations' ),
				],
				'conditions' => $cascade_terms,
			]
		);

		$this->add(
			$element,
			$k( 'off_mobile' ),
			[
				'label'        => __( 'Desligar no celular', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'separator'    => 'before',
				// Quem obedece a este toggle é o core.js, que nem é carregado
				// por um preset resolvido só em CSS.
				'condition'    => [ $k( 'preset' ) . '!' => $timed ],
			]
		);

		$this->add(
			$element,
			$k( 'engine' ),
			[
				'label'       => __( 'Motor', 'wooflow-animations' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'auto',
				'options'     => [
					'auto'  => __( 'Automático (recomendado)', 'wooflow-animations' ),
					'css'   => __( 'Próprio, sem biblioteca', 'wooflow-animations' ),
					'gsap'  => __( 'GSAP', 'wooflow-animations' ),
					'anime' => __( 'Anime.js', 'wooflow-animations' ),
				],
				'description' => __( 'No automático, cada animação usa o motor mais leve que dá conta. Só mude se souber por quê.', 'wooflow-animations' ),
				'condition'   => [ $k( 'preset' ) => $this->ids_with_engine( 'css' ) ],
			]
		);

		$element->end_controls_section();

		// Seção irmã, no mesmo ponto de injeção e sob o mesmo guard: o fundo
		// animado não é preset e convive com uma animação de entrada no mesmo
		// elemento, então não cabe na seção de cima.
		WFAN_Overlay::add_controls( $element, $tab );
	}
}
