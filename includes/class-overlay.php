<?php
/**
 * Fundo animado por elemento — o "Overlay Animated" do Uncode.
 *
 * Não é preset: não tem gatilho, não tem fim, não esconde conteúdo e convive
 * com uma animação de entrada no mesmo elemento. Por isso vive fora do catálogo
 * e tem seção própria no painel, como o Lenis e o blur têm módulo próprio.
 *
 * O desenho é um <canvas> de resolução baixa (a tela estica e o borrão é de
 * graça) pintado pixel a pixel com ruído simplex 3D: as duas cores se misturam
 * pelo valor do ruído e o alfa também sai dele, que é o que faz as manchas
 * respirarem. O terceiro eixo é o tempo.
 *
 * REGRA DO MÓDULO: todo parâmetro viaja em custom property escrita pelo
 * Elementor a partir dos `selectors` — nunca num atributo do wrapper. É o que
 * mantém o fundo vivo no editor: o canvas do editor reconstrói o wrapper pelo
 * Backbone e perde qualquer atributo do PHP (ver CLAUDE.md, regra 19), mas o
 * CSS que o Elementor gera por elemento sobrevive a cada re-render e ainda é
 * reescrito a cada mexida no controle, sem ida ao servidor. Quem decide
 * continua sendo o servidor; o JavaScript só lê o que já está no CSS.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class WFAN_Overlay {

	/** Id da seção própria no painel. Não é chave de dado. */
	const SECTION = 'ovl_section';

	/** Classe do wrapper: é por ela que o overlay.js acha os elementos. */
	const HOST_CLASS = 'wfan-ovl';

	/** Classe do canvas que o overlay.js cria dentro do elemento. */
	const CANVAS_CLASS = 'wfan-ovl-canvas';

	/**
	 * Nomes das custom properties. Mapa único: o overlay.js lê exatamente estes
	 * nomes e o smoke test confere os dois lados.
	 *
	 * @var array<string,string>
	 */
	const VARS = [
		'on'      => '--wfan-ovl',
		'c1'      => '--wfan-ovl-c1',
		'c2'      => '--wfan-ovl-c2',
		'speed'   => '--wfan-ovl-sp',
		'size'    => '--wfan-ovl-sz',
		'opacity' => '--wfan-ovl-op',
		'blend'   => '--wfan-ovl-bl',
	];

	/**
	 * Padrões do painel. Os mesmos valores aparecem no fallback de cada `var()`
	 * do frontend.css e do overlay.js, para quando o CSS do documento ainda não
	 * tiver sido gerado.
	 *
	 * @var array<string,string|int>
	 */
	const DEFAULTS = [
		'c1'      => '#115CFA',
		'c2'      => '#6442FF',
		'speed'   => '0.25',
		'size'    => '0.70',
		// 100 e não os 85 do original: lá o overlay sempre pousa sobre uma foto,
		// com mistura; aqui ele pode cair num bloco de cor chapada, onde o alfa
		// do próprio ruído (média de ~29 em 255) já deixa o efeito discreto.
		'opacity' => 100,
		'blend'   => 'normal',
	];

	/**
	 * Voltas do ruído por segundo. O valor da opção é o número que vai para o
	 * CSS, e nenhum deles pode parecer inteiro: o PHP converteria a chave '1' em
	 * int 1, e `{{VALUE}}` sairia sem as casas decimais.
	 *
	 * @return array<string,string>
	 */
	public static function speeds() {
		return [
			'0.04' => __( 'Lenta', 'wooflow-animations' ),
			'0.25' => __( 'Média', 'wooflow-animations' ),
			'0.60' => __( 'Rápida', 'wooflow-animations' ),
		];
	}

	/**
	 * Frequência espacial do ruído: quanto menor o número, maiores as manchas.
	 *
	 * @return array<string,string>
	 */
	public static function sizes() {
		return [
			'0.35' => __( 'Grandes', 'wooflow-animations' ),
			'0.70' => __( 'Médias', 'wooflow-animations' ),
			'1.20' => __( 'Pequenas', 'wooflow-animations' ),
		];
	}

	/**
	 * Modos de mistura oferecidos. `normal` é o padrão e não mistura nada.
	 *
	 * @return array<string,string>
	 */
	public static function blends() {
		return [
			'normal'      => __( 'Normal', 'wooflow-animations' ),
			'multiply'    => __( 'Multiplicar', 'wooflow-animations' ),
			'screen'      => __( 'Tela', 'wooflow-animations' ),
			'overlay'     => __( 'Sobrepor', 'wooflow-animations' ),
			'soft-light'  => __( 'Luz suave', 'wooflow-animations' ),
			'color-dodge' => __( 'Subexposição', 'wooflow-animations' ),
			'luminosity'  => __( 'Luminosidade', 'wooflow-animations' ),
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
	 * Uma declaração por controle de cor, sempre com o nome da propriedade antes
	 * do primeiro `:`. Com uma cor global escolhida, o Elementor troca tudo
	 * depois desse `:` pelo valor global (`Base::add_control_rules()`) — o que
	 * aqui dá justamente `--wfan-ovl-c1:var(--e-global-color-x)`, mas levaria
	 * junto qualquer declaração vizinha. Ver CLAUDE.md, regra 18.
	 *
	 * @param string $slot Chave em VARS.
	 * @param string $value Placeholder do Elementor ({{VALUE}} ou {{SIZE}}%).
	 * @return array<string,string>
	 */
	private static function sel( $slot, $value ) {
		return [ '{{WRAPPER}}' => self::VARS[ $slot ] . ': ' . $value . ';' ];
	}

	/**
	 * Seção própria, registrada logo depois da seção de animação e no mesmo
	 * ponto de injeção — o guard por nome de stack da WFAN_Controls já cobre as
	 * duas (ver CLAUDE.md, regra 2).
	 *
	 * @param \Elementor\Controls_Stack $element Elemento que recebe os controles.
	 * @param string                    $tab     Aba onde a seção aparece.
	 * @return void
	 */
	public static function add_controls( $element, $tab ) {
		$on = [ self::k( 'ovl' ) => 'yes' ];

		$element->start_controls_section(
			WFAN_Keys::P . self::SECTION,
			[
				'label' => __( 'WooFlow — Fundo animado', 'wooflow-animations' ),
				'tab'   => $tab,
			]
		);

		$element->add_control(
			self::k( 'ovl' ),
			[
				'label'        => __( 'Fundo animado', 'wooflow-animations' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => __( 'Um degradê de ruído que se move sem parar, por cima do fundo do elemento e atrás do conteúdo. Pode ser usado junto com uma animação de entrada.', 'wooflow-animations' ),
				'selectors'    => self::sel( 'on', '1' ),
			]
		);

		$element->add_control(
			self::k( 'ovl_c1' ),
			[
				'label'     => __( 'Cor 1', 'wooflow-animations' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => self::DEFAULTS['c1'],
				'selectors' => self::sel( 'c1', '{{VALUE}}' ),
				'condition' => $on,
			]
		);

		$element->add_control(
			self::k( 'ovl_c2' ),
			[
				'label'       => __( 'Cor 2', 'wooflow-animations' ),
				'type'        => Controls_Manager::COLOR,
				'default'     => self::DEFAULTS['c2'],
				'description' => __( 'Repita a cor 1 aqui para um fundo de uma cor só, que respira pela transparência.', 'wooflow-animations' ),
				'selectors'   => self::sel( 'c2', '{{VALUE}}' ),
				'condition'   => $on,
			]
		);

		$element->add_control(
			self::k( 'ovl_speed' ),
			[
				'label'     => __( 'Velocidade', 'wooflow-animations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => self::DEFAULTS['speed'],
				'options'   => self::speeds(),
				'selectors' => self::sel( 'speed', '{{VALUE}}' ),
				'condition' => $on,
			]
		);

		$element->add_control(
			self::k( 'ovl_size' ),
			[
				'label'     => __( 'Tamanho das manchas', 'wooflow-animations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => self::DEFAULTS['size'],
				'options'   => self::sizes(),
				'selectors' => self::sel( 'size', '{{VALUE}}' ),
				'condition' => $on,
			]
		);

		$element->add_control(
			self::k( 'ovl_opacity' ),
			[
				'label'      => __( 'Opacidade', 'wooflow-animations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%' ],
				'range'      => [ '%' => [ 'min' => 0, 'max' => 100, 'step' => 5 ] ],
				'default'    => [ 'unit' => '%', 'size' => self::DEFAULTS['opacity'] ],
				'selectors'  => self::sel( 'opacity', '{{SIZE}}%' ),
				'condition'  => $on,
			]
		);

		$element->add_control(
			self::k( 'ovl_blend' ),
			[
				'label'       => __( 'Mistura com o fundo', 'wooflow-animations' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => self::DEFAULTS['blend'],
				'options'     => self::blends(),
				'description' => __( 'Com uma imagem de fundo, “Multiplicar” escurece e “Tela” clareia. Sem imagem, deixe em “Normal”.', 'wooflow-animations' ),
				'selectors'   => self::sel( 'blend', '{{VALUE}}' ),
				'condition'   => $on,
			]
		);

		$element->end_controls_section();
	}

	/**
	 * Marca o elemento para o overlay.js e pede o script.
	 *
	 * A classe é só o seletor rápido do front-end: os parâmetros vêm todos do
	 * CSS. No editor ela se perde no primeiro re-render, e é por isso que o
	 * overlay.js reconhece o elemento pela custom property, não pela classe.
	 *
	 * @param \Elementor\Element_Base $element  Elemento em renderização.
	 * @param array                   $settings Ajustes do elemento.
	 * @return bool Se o fundo animado está ligado neste elemento.
	 */
	public static function attach( $element, $settings ) {
		$key = self::k( 'ovl' );

		if ( ! isset( $settings[ $key ] ) || 'yes' !== $settings[ $key ] ) {
			return false;
		}

		$element->add_render_attribute( '_wrapper', [ 'class' => [ self::HOST_CLASS ] ] );

		WFAN_Plugin::assets()->require_overlay();

		return true;
	}
}
