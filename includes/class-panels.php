<?php
/**
 * Painéis expansíveis — o "accordion horizontal" de seções de soluções.
 *
 * Liga-se num container pai; os filhos diretos dele viram painéis. Um fica
 * aberto e ocupa o espaço que sobra; os outros encolhem para uma faixa estreita
 * com o número, o título na vertical e um botão "+". Clicar (ou passar o
 * mouse, se escolhido) num fechado abre ele e fecha o anterior. Abaixo do ponto
 * de quebra, os painéis empilham e viram um accordion vertical comum.
 *
 * Não é preset: não tem gatilho de tela, não tem fim e convive com uma animação
 * de entrada no mesmo container. Por isso fica fora do catálogo, com seção
 * própria no painel, como o fundo animado (WFAN_Overlay).
 *
 * REGRA DO MÓDULO, a mesma do fundo animado: todo parâmetro viaja em custom
 * property escrita pelo Elementor a partir dos `selectors`, nunca num atributo
 * do wrapper. No canvas do editor o wrapper é reconstruído pelo Backbone e
 * perde qualquer atributo do PHP (CLAUDE.md, regra 19); o CSS por elemento
 * sobrevive e é reescrito a cada mexida no controle. Ver regra 33.
 *
 * Sem JavaScript nada muda: os filhos ficam lado a lado, como o Elementor os
 * desenhou, com todo o conteúdo à mostra. É o panels.js que fecha os painéis,
 * então não há pré-esconde nem tempo de segurança a respeitar (regra 8).
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class WFAN_Panels {

	/** Id da seção própria no painel. Não é chave de dado. */
	const SECTION = 'pnl_section';

	/** Classe do wrapper: é por ela que o panels.js acha os containers no front-end. */
	const HOST_CLASS = 'wfan-pnl';

	/** Único tipo de elemento que recebe a seção: painel é container filho. */
	const ELEMENT = 'container';

	/**
	 * Nomes das custom properties. Mapa único: o panels.js e o frontend.css
	 * leem exatamente estes nomes, e o smoke test confere os dois sentidos.
	 *
	 * @var array<string,string>
	 */
	const VARS = [
		'on'      => '--wfan-pnl',
		'first'   => '--wfan-pnl-first',
		'trigger' => '--wfan-pnl-tr',
		'closed'  => '--wfan-pnl-min',
		'bar'     => '--wfan-pnl-bar',
		'stack'   => '--wfan-pnl-bp',
		'number'  => '--wfan-pnl-num',
		'dur'     => '--wfan-pnl-dur',
		'ease'    => '--wfan-pnl-ease',
		'fg'      => '--wfan-pnl-fg',
		'shade'   => '--wfan-pnl-shade',
		'size'    => '--wfan-pnl-fs',
	];

	/**
	 * Largura do conteúdo do painel aberto, medida pelo panels.js e escrita
	 * inline no container. Não é controle: é o que faz o conteúdo de TODO
	 * painel — fechado inclusive — ser diagramado nessa largura e só cortado
	 * pela do painel. Sem ela, o texto escondido de um painel de 110 px quebra
	 * uma palavra por linha, fica altíssimo e estica a fileira inteira; e o do
	 * painel abrindo requebra a cada quadro. Regra 33.
	 */
	const MEASURE_VAR = '--wfan-pnl-cw';

	/**
	 * Padrões do painel. Os mesmos valores aparecem no fallback de cada `var()`
	 * do frontend.css e do panels.js, para quando o CSS do documento ainda não
	 * tiver sido gerado.
	 *
	 * @var array<string,string|int>
	 */
	const DEFAULTS = [
		'first'   => 1,
		'trigger' => 'click',
		'closed'  => 110,
		'bar'     => 72,
		'stack'   => '767',
		'dur'     => 700,
		'ease'    => 'smooth',
		'fg'      => '#FFFFFF',
		'shade'   => 'rgba(0, 0, 0, 0.35)',
		'size'    => 18,
	];

	/**
	 * @return array<string,string>
	 */
	public static function triggers() {
		return [
			'click' => __( 'Clique', 'wooflow-animations' ),
			'hover' => __( 'Passar o mouse', 'wooflow-animations' ),
		];
	}

	/**
	 * Largura da tela até a qual os painéis empilham. O valor da opção é o
	 * número que vai para o CSS, em px; zero quer dizer nunca.
	 *
	 * @return array<string,string>
	 */
	public static function stacks() {
		return [
			'767'  => __( 'No celular', 'wooflow-animations' ),
			'1024' => __( 'No tablet e no celular', 'wooflow-animations' ),
			'0'    => __( 'Nunca', 'wooflow-animations' ),
		];
	}

	/**
	 * Curvas da abertura. O valor gravado é o nome; o `selectors_dictionary`
	 * troca pelo que o CSS entende.
	 *
	 * @return array<string,array{label:string,css:string}>
	 */
	public static function easings() {
		return [
			'smooth' => [
				'label' => __( 'Suave', 'wooflow-animations' ),
				'css'   => 'cubic-bezier(0.65, 0, 0.35, 1)',
			],
			'snappy' => [
				'label' => __( 'Rápida no início', 'wooflow-animations' ),
				'css'   => 'cubic-bezier(0.16, 1, 0.3, 1)',
			],
			'linear' => [
				'label' => __( 'Constante', 'wooflow-animations' ),
				'css'   => 'linear',
			],
		];
	}

	/**
	 * @param string $suffix Sufixo listado em WFAN_Keys::OURS.
	 * @return string
	 */
	private static function k( $suffix ) {
		return WFAN_Keys::ours( $suffix );
	}

	/**
	 * Uma declaração por controle, com o nome da propriedade antes do primeiro
	 * `:` — com cor global, o Elementor troca tudo depois dele pelo valor
	 * global e levaria uma declaração vizinha junto (regra 18).
	 *
	 * @param string $slot  Chave em VARS.
	 * @param string $value Placeholder do Elementor.
	 * @return array<string,string>
	 */
	private static function sel( $slot, $value ) {
		return [ '{{WRAPPER}}' => self::VARS[ $slot ] . ': ' . $value . ';' ];
	}

	/**
	 * Registra um controle dos painéis. `ui` em todos, pelo mesmo motivo do
	 * fundo animado: nenhum deles muda o HTML, e sem isso cada mexida
	 * re-renderizaria o container inteiro por Ajax (regra 22).
	 *
	 * @param \Elementor\Controls_Stack $element Elemento.
	 * @param string                    $id      Id do controle.
	 * @param array                     $args    Argumentos.
	 * @return void
	 */
	private static function add( $element, $id, $args ) {
		$args['render_type'] = 'ui';

		$element->add_control( $id, $args );
	}

	/**
	 * Só o container recebe a seção: painel é container filho, e a seção num
	 * widget seria configuração que não faz nada.
	 *
	 * @param \Elementor\Controls_Stack $element Elemento.
	 * @return bool
	 */
	public static function accepts( $element ) {
		return self::ELEMENT === $element->get_name();
	}

	/**
	 * Seção própria, no mesmo ponto de injeção e sob o mesmo guard da seção de
	 * animação (regra 2).
	 *
	 * @param \Elementor\Controls_Stack $element Elemento que recebe os controles.
	 * @param string                    $tab     Aba onde a seção aparece.
	 * @return void
	 */
	public static function add_controls( $element, $tab ) {
		if ( ! self::accepts( $element ) ) {
			return;
		}

		$on = [ self::k( 'pnl' ) => 'yes' ];

		$element->start_controls_section(
			WFAN_Keys::P . self::SECTION,
			[
				'label' => __( 'WooFlow — Painéis expansíveis', 'wooflow-animations' ),
				'tab'   => $tab,
			]
		);

		self::add(
			$element,
			self::k( 'pnl' ),
			[
				'label'        => __( 'Painéis expansíveis', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => __( 'Cada container filho direto deste vira um painel. Um fica aberto; os outros viram uma faixa com o número, o título na vertical e um botão +. O título vem do primeiro Título de cada painel, ou do atributo data-wfan-label, se houver. Defina uma altura mínima neste container para os painéis não pularem ao abrir.', 'wooflow-animations' ),
				'selectors'    => self::sel( 'on', '1' ),
			]
		);

		self::add(
			$element,
			self::k( 'pnl_first' ),
			[
				'label'     => __( 'Painel aberto de início', 'wooflow-animations' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 12,
				'step'      => 1,
				'default'   => self::DEFAULTS['first'],
				'selectors' => self::sel( 'first', '{{VALUE}}' ),
				'condition' => $on,
			]
		);

		self::add(
			$element,
			self::k( 'pnl_trigger' ),
			[
				'label'       => __( 'Abrir com', 'wooflow-animations' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => self::DEFAULTS['trigger'],
				'options'     => self::triggers(),
				'description' => __( 'Em tela de toque e com os painéis empilhados, é sempre o clique.', 'wooflow-animations' ),
				'selectors'   => self::sel( 'trigger', '{{VALUE}}' ),
				'condition'   => $on,
			]
		);

		self::add(
			$element,
			self::k( 'pnl_closed' ),
			[
				'label'      => __( 'Largura do painel fechado', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 48, 'max' => 240, 'step' => 2 ] ],
				'default'    => [ 'unit' => 'px', 'size' => self::DEFAULTS['closed'] ],
				'selectors'  => self::sel( 'closed', '{{SIZE}}px' ),
				'condition'  => $on,
			]
		);

		self::add(
			$element,
			self::k( 'pnl_stack' ),
			[
				'label'       => __( 'Empilhar', 'wooflow-animations' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => self::DEFAULTS['stack'],
				'options'     => self::stacks(),
				'description' => __( 'Empilhados, os painéis viram um accordion vertical: o fechado é uma barra com o número e o título.', 'wooflow-animations' ),
				'selectors'   => self::sel( 'stack', '{{VALUE}}' ),
				'condition'   => $on,
			]
		);

		self::add(
			$element,
			self::k( 'pnl_bar' ),
			[
				'label'      => __( 'Altura da barra empilhada', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 44, 'max' => 160, 'step' => 2 ] ],
				'default'    => [ 'unit' => 'px', 'size' => self::DEFAULTS['bar'] ],
				'selectors'  => self::sel( 'bar', '{{SIZE}}px' ),
				'condition'  => array_merge( $on, [ self::k( 'pnl_stack' ) . '!' => '0' ] ),
			]
		);

		self::add(
			$element,
			self::k( 'pnl_number' ),
			[
				'label'        => __( 'Mostrar número', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'selectors'    => self::sel( 'number', '1' ),
				'condition'    => $on,
			]
		);

		self::add(
			$element,
			self::k( 'pnl_dur' ),
			[
				'label'      => __( 'Duração (ms)', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 2000, 'step' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => self::DEFAULTS['dur'] ],
				'selectors'  => self::sel( 'dur', '{{SIZE}}ms' ),
				'separator'  => 'before',
				'condition'  => $on,
			]
		);

		$curves = [];
		$labels = [];

		foreach ( self::easings() as $name => $easing ) {
			$curves[ $name ] = $easing['css'];
			$labels[ $name ] = $easing['label'];
		}

		self::add(
			$element,
			self::k( 'pnl_ease' ),
			[
				'label'                => __( 'Curva', 'wooflow-animations' ),
				'type'                 => Controls_Manager::SELECT,
				'default'              => self::DEFAULTS['ease'],
				'options'              => $labels,
				'selectors_dictionary' => $curves,
				'selectors'            => self::sel( 'ease', '{{VALUE}}' ),
				'condition'            => $on,
			]
		);

		self::add(
			$element,
			self::k( 'pnl_fg' ),
			[
				'label'     => __( 'Cor do título e do +', 'wooflow-animations' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => self::DEFAULTS['fg'],
				'selectors' => self::sel( 'fg', '{{VALUE}}' ),
				'separator' => 'before',
				'condition' => $on,
			]
		);

		self::add(
			$element,
			self::k( 'pnl_shade' ),
			[
				'label'       => __( 'Véu do painel fechado', 'wooflow-animations' ),
				'type'        => Controls_Manager::COLOR,
				'default'     => self::DEFAULTS['shade'],
				'description' => __( 'Escurece a foto de fundo do painel fechado para o título ser lido. Transparente para não escurecer.', 'wooflow-animations' ),
				'selectors'   => self::sel( 'shade', '{{VALUE}}' ),
				'condition'   => $on,
			]
		);

		self::add(
			$element,
			self::k( 'pnl_size' ),
			[
				'label'      => __( 'Tamanho do título', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 11, 'max' => 40, 'step' => 1 ] ],
				'default'    => [ 'unit' => 'px', 'size' => self::DEFAULTS['size'] ],
				'selectors'  => self::sel( 'size', '{{SIZE}}px' ),
				'condition'  => $on,
			]
		);

		$element->end_controls_section();
	}

	/**
	 * Marca o container para o panels.js e pede o script.
	 *
	 * A classe é só o seletor rápido do front-end: os parâmetros vêm todos do
	 * CSS, e no editor o panels.js reconhece o container pela custom property.
	 *
	 * @param \Elementor\Element_Base $element  Elemento em renderização.
	 * @param array                   $settings Ajustes do elemento.
	 * @return bool Se os painéis estão ligados neste elemento.
	 */
	public static function attach( $element, $settings ) {
		$key = self::k( 'pnl' );

		if ( ! self::accepts( $element ) || ! isset( $settings[ $key ] ) || 'yes' !== $settings[ $key ] ) {
			return false;
		}

		$element->add_render_attribute( '_wrapper', [ 'class' => [ self::HOST_CLASS ] ] );

		WFAN_Plugin::assets()->require_panels();

		return true;
	}
}
