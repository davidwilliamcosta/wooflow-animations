<?php
/**
 * Escreve o contrato do front-end no wrapper do elemento.
 *
 * Sai de `elementor/frontend/before_render` + atributo `_wrapper`, que funciona
 * para widget, container, section e column. Nunca de um filtro em the_content.
 * Ver CLAUDE.md, regra 4.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Render {

	public function __construct() {
		add_action( 'elementor/frontend/before_render', [ $this, 'before_render' ], 10, 1 );
	}

	/**
	 * Lê um controle SLIDER (array com size/unit) ou um número cru.
	 *
	 * @param array      $settings Ajustes do elemento.
	 * @param string     $key      Chave.
	 * @param int|float  $fallback Valor padrão.
	 * @return float
	 */
	private function num( $settings, $key, $fallback ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return (float) $fallback;
		}

		$value = $settings[ $key ];

		if ( is_array( $value ) ) {
			$value = isset( $value['size'] ) ? $value['size'] : $fallback;
		}

		if ( '' === $value || null === $value ) {
			return (float) $fallback;
		}

		return (float) $value;
	}

	/**
	 * @param array  $settings Ajustes do elemento.
	 * @param string $key      Chave.
	 * @return bool
	 */
	private function on( $settings, $key ) {
		return isset( $settings[ $key ] ) && 'yes' === $settings[ $key ];
	}

	/**
	 * @param \Elementor\Element_Base $element Elemento em renderização.
	 * @return bool
	 */
	private function is_atomic( $element ) {
		return method_exists( $element, 'get_props_schema' );
	}

	/**
	 * O contrato completo de um elemento, do jeito que o navegador o veria.
	 *
	 * Fica separado do `before_render()` porque o ▶ Testar do editor precisa do
	 * mesmo resultado: no canvas, o wrapper é construído pelo Backbone do
	 * Elementor (`BaseElementView.attributes()` devolve só `data-id`,
	 * `data-element_type` e `data-model-cid`), e o `print_element()` — que é
	 * quem dispara o nosso gancho — só roda no front-end e na carga inicial do
	 * preview. Sem este caminho, o editor teria de redecidir motor e gatilho em
	 * JavaScript, e quem decide isso é o servidor (ver CLAUDE.md, seção 3).
	 *
	 * @param array $settings Ajustes do elemento.
	 * @param bool  $hide     Permite a classe de pré-esconde. Falso no editor.
	 * @return array{payload:array,classes:string[],engine:string,css_only:bool}|null
	 */
	public function spec( $settings, $hide = true ) {
		$k = static function ( $suffix ) {
			return WFAN_Keys::ours( $suffix );
		};

		$preset_id = isset( $settings[ $k( 'preset' ) ] ) ? (string) $settings[ $k( 'preset' ) ] : '';

		if ( '' === $preset_id ) {
			return null;
		}

		$preset = WFAN_Presets::get( $preset_id );

		if ( ! $preset ) {
			return null;
		}

		$engine = $this->resolve_engine( $preset, $settings, $k );

		if ( ! $engine ) {
			return null;
		}

		$trigger = $this->resolve_trigger( $preset, $settings, $k );
		$classes = [ 'wfan' ];

		// Preset nativo nunca recebe o pré-esconde: quem esconde é a classe
		// `elementor-invisible` do próprio Elementor, e duas redes de esconder
		// no mesmo elemento só dariam conteúdo invisível para sempre se uma
		// delas falhasse.
		if ( ! empty( $preset['native'] ) ) {
			$classes[] = WFAN_Controls::NATIVE_CLASS;
		} elseif ( $hide && $this->hides_element( $preset, $trigger ) ) {
			$classes[] = 'wfan-pending';

			if ( 0 === strpos( $preset_id, 'mask' ) || 'text-mask' === $preset_id ) {
				$classes[] = 'wfan-mask';
			}
		}

		return [
			'payload'  => $this->payload( $preset_id, $preset, $engine, $trigger, $settings, $k ),
			'classes'  => $classes,
			'engine'   => $engine,
			'css_only' => ! empty( $preset['css_only'] ),
			'native'   => (string) $preset['native'],
		];
	}

	/**
	 * @param \Elementor\Element_Base $element Elemento em renderização.
	 * @return void
	 */
	public function before_render( $element ) {
		if ( ! method_exists( $element, 'add_render_attribute' ) || $this->is_atomic( $element ) ) {
			return;
		}

		// ANTES de qualquer get_settings_for_display(): o Elementor guarda o
		// resultado em `parsed_active_settings` na primeira chamada, e o
		// `add_render_attributes()` — que é quem lê `_animation` para pôr a
		// classe `elementor-invisible` e o `data-settings` — lê desse cache.
		// Escrever a animação nativa depois do cache quente não chega a lugar
		// nenhum. Ver CLAUDE.md, regra 21.
		$native = $this->apply_native( $element );

		$settings = $element->get_settings_for_display();

		// Antes do `return` do preset: o fundo animado não é animação e existe
		// com ou sem uma escolhida no mesmo elemento — inclusive uma nativa.
		WFAN_Overlay::attach( $element, $settings );

		if ( $native ) {
			return;
		}

		$spec = $this->spec( $settings, ! $this->is_preview() );

		if ( ! $spec ) {
			return;
		}

		$element->add_render_attribute(
			'_wrapper',
			[
				'class'        => $spec['classes'],
				'data-wfan' => wp_json_encode( $spec['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			]
		);

		// Preset de estado não precisa de JS: o efeito inteiro sai no CSS que o
		// Elementor gera pelos `selectors` dos controles. Só a folha do
		// front-end entra, por causa da rede de prefers-reduced-motion.
		if ( $spec['css_only'] ) {
			WFAN_Plugin::assets()->require_style();

			return;
		}

		WFAN_Plugin::assets()->require_engine( $spec['engine'] );

		$this->maybe_disable_native( $element );
	}

	/**
	 * Entrega o elemento à animação de entrada do próprio Elementor.
	 *
	 * O preset do grupo "Nativas" não escreve `data-wfan`, não pede motor e não
	 * põe um byte de JavaScript nosso na página: tudo o que ele faz é preencher
	 * `_animation` (ou `animation`, no bloco) antes de o Elementor montar os
	 * atributos do wrapper. Daí em diante quem esconde, observa a tela e anima
	 * é o `GlobalHandler` do Elementor.
	 *
	 * Lê os ajustes crus de propósito: `get_settings_for_display()` guarda o
	 * resultado em cache na primeira chamada, e o que for escrito depois dela
	 * não chega ao `add_render_attributes()`.
	 *
	 * @param \Elementor\Element_Base $element Elemento em renderização.
	 * @return bool Se este elemento foi entregue ao motor nativo.
	 */
	private function apply_native( $element ) {
		if ( ! method_exists( $element, 'set_settings' ) ) {
			return false;
		}

		$settings = $element->get_settings();

		if ( ! is_array( $settings ) ) {
			return false;
		}

		$key       = WFAN_Keys::ours( 'preset' );
		$preset_id = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		$name      = '' === $preset_id ? '' : WFAN_Presets::native_name( $preset_id );

		if ( '' === $name ) {
			return false;
		}

		$native = WFAN_Keys::native_for( $element->get_type() );

		$element->set_settings( $native['name'], $name );
		$element->set_settings( $native['delay'], (int) $this->num( $settings, WFAN_Keys::ours( 'delay' ), 0 ) );

		// "Desligar no celular" existe no nativo pela versão responsiva da
		// chave: com 'none' no celular o handler do Elementor tira a classe
		// `elementor-invisible` e não anima nada. O corte é o breakpoint do
		// Elementor, não o `mobile_bp` dos nossos ajustes — é o preço de deixar
		// a decisão com ele.
		if ( $this->on( $settings, WFAN_Keys::ours( 'off_mobile' ) ) || WFAN_Settings::is_on( 'off_mobile' ) ) {
			$element->set_settings( $native['mobile'], 'none' );
		}

		$element->add_render_attribute( '_wrapper', [ 'class' => [ 'wfan', WFAN_Controls::NATIVE_CLASS ] ] );

		WFAN_Plugin::assets()->require_native( $name );

		return true;
	}

	/**
	 * @return bool
	 */
	private function is_preview() {
		return class_exists( '\Elementor\Plugin' )
			&& \Elementor\Plugin::$instance->preview->is_preview_mode();
	}

	/**
	 * O motor é decidido aqui, no servidor. O navegador só obedece — é isso que
	 * permite enfileirar uma biblioteca só quando ela é realmente usada.
	 *
	 * @param array    $preset   Preset.
	 * @param array    $settings Ajustes.
	 * @param callable $k        Resolvedor de chave.
	 * @return string Motor, ou string vazia se a animação deve ser ignorada.
	 */
	private function resolve_engine( $preset, $settings, $k ) {
		$engine   = $preset['engine'];
		$override = isset( $settings[ $k( 'engine' ) ] ) ? (string) $settings[ $k( 'engine' ) ] : 'auto';

		// Preset resolvido só em CSS não tem motor para trocar. O controle nem
		// aparece para ele, mas um copiar/colar vindo de outro preset traz o
		// valor junto — e uma biblioteca enfileirada à toa é o que a regra 5
		// existe para evitar.
		if ( ! empty( $preset['css_only'] ) ) {
			return 'css';
		}

		if ( 'css' === $engine && in_array( $override, [ 'gsap', 'anime' ], true ) ) {
			$engine = $override;
		}

		if ( WFAN_Settings::lib_allowed( $engine ) ) {
			return $engine;
		}

		// Biblioteca bloqueada nos ajustes: o que o motor próprio consegue fazer,
		// ele faz; o resto não anima (e o conteúdo fica visível).
		return 'css' === $preset['engine'] ? 'css' : '';
	}

	/**
	 * @param array    $preset   Preset.
	 * @param array    $settings Ajustes.
	 * @param callable $k        Resolvedor de chave.
	 * @return string
	 */
	private function resolve_trigger( $preset, $settings, $k ) {
		$trigger = isset( $settings[ $k( 'trigger' ) ] ) ? (string) $settings[ $k( 'trigger' ) ] : 'scroll-in';
		$allowed = $preset['triggers'] ? $preset['triggers'] : array_keys( WFAN_Presets::triggers() );

		if ( in_array( $trigger, $allowed, true ) ) {
			return $trigger;
		}

		return (string) reset( $allowed );
	}

	/**
	 * Presets que começam com o elemento invisível precisam da classe de
	 * pré-esconde; os de ênfase, scroll e Lottie, não.
	 *
	 * @param array  $preset  Preset.
	 * @param string $trigger Gatilho resolvido.
	 * @return bool
	 */
	private function hides_element( $preset, $trigger ) {
		if ( ! in_array( $preset['group'], [ 'entrada', 'texto' ], true ) ) {
			return false;
		}

		return in_array( $trigger, [ 'scroll-in', 'load' ], true );
	}

	/**
	 * @param string   $preset_id Id do preset.
	 * @param array    $preset    Preset.
	 * @param string   $engine    Motor resolvido.
	 * @param string   $trigger   Gatilho resolvido.
	 * @param array    $settings  Ajustes.
	 * @param callable $k         Resolvedor de chave.
	 * @return array
	 */
	private function payload( $preset_id, $preset, $engine, $trigger, $settings, $k ) {
		$payload = [
			'v'   => WFAN_Keys::ATTR_VERSION,
			'p'   => $preset_id,
			'eng' => $engine,
			'tr'  => [
				't'     => $trigger,
				'vp'    => (int) $this->num( $settings, $k( 'viewport' ), 20 ),
				'once'  => $this->on( $settings, $k( 'once' ) ) ? 1 : 0,
				'start' => isset( $settings[ $k( 'scrub_start' ) ] ) ? (string) $settings[ $k( 'scrub_start' ) ] : 'enter',
				'end'   => isset( $settings[ $k( 'scrub_end' ) ] ) ? (string) $settings[ $k( 'scrub_end' ) ] : 'out',
				'pct'   => (int) $this->num( $settings, $k( 'scrub_pct' ), 100 ),
				'pin'   => $this->on( $settings, $k( 'pin' ) ) ? 1 : 0,
			],
			'd'   => (int) $this->num( $settings, $k( 'duration' ), 800 ),
			'dl'  => (int) $this->num( $settings, $k( 'delay' ), 0 ),
			'e'   => isset( $settings[ $k( 'easing' ) ] ) ? (string) $settings[ $k( 'easing' ) ] : 'power2.out',
		];

		// Animação do próprio Elementor. No front-end este payload nem chega a
		// ser escrito — o elemento sai com `_animation` preenchido e mais nada
		// —, mas o ▶ Testar do editor precisa dele: lá o wrapper é do Backbone
		// e não tem animação nativa nenhuma para tocar (regra 19).
		if ( ! empty( $preset['native'] ) ) {
			$payload['nat'] = (string) $preset['native'];
		}

		// Avisa o core.js de que este elemento já está resolvido em CSS: sem
		// isso ele amarraria o gatilho e mandaria o motor tocar um preset que o
		// motor não conhece.
		//
		// A duração vem do controle próprio: o genérico está escondido neste
		// preset e, escondido, o Elementor devolve `null` para ele
		// (`get_active_settings()`), o que daria os 800 ms de fábrica. Quem
		// precisa do número certo é o ▶ Testar, para segurar o estado o tempo
		// da transição.
		if ( ! empty( $preset['css_only'] ) ) {
			$payload['co'] = 1;
			$payload['d']  = (int) $this->num( $settings, $k( 'hover_dur' ), 300 );
		}

		$params = $preset['params'];

		if ( in_array( 'distance', $params, true ) ) {
			$payload['dist'] = (int) $this->num( $settings, $k( 'distance' ), 40 );
		}

		if ( in_array( 'scale_from', $params, true ) ) {
			$payload['scale'] = (float) $this->num( $settings, $k( 'scale_from' ), 0.85 );
		}

		if ( in_array( 'blur', $params, true ) ) {
			$payload['blur'] = (int) $this->num( $settings, $k( 'blur' ), 10 );
		}

		if ( in_array( 'dim', $params, true ) ) {
			$payload['dim'] = (float) $this->num( $settings, $k( 'dim' ), 0.2 );
		}

		// Linha fixa na tela em que cada pedaço acende. Só quem a declara é que
		// a recebe, e quem a recebe ignora o par "começar/terminar quando": o
		// percurso passa a ser o do próprio elemento cruzando essa linha.
		if ( in_array( 'line', $params, true ) ) {
			$payload['line']  = (int) $this->num( $settings, $k( 'scrub_line' ), 80 );
			$payload['speed'] = (int) $this->num( $settings, $k( 'scrub_speed' ), 65 );
		}

		if ( in_array( 'rotate', $params, true ) ) {
			$payload['rot'] = (int) $this->num( $settings, $k( 'rotate' ), 12 );
		}

		// "Virar cartão no scroll". A origem passa por lista fechada porque
		// vai crua para o `transform-origin`, e o estado também: qualquer
		// outro valor vira o padrão do efeito de referência.
		if ( in_array( 'card', $params, true ) ) {
			$state  = isset( $settings[ $k( 'card_state' ) ] ) ? (string) $settings[ $k( 'card_state' ) ] : 'out';
			$origin = isset( $settings[ $k( 'card_origin' ) ] ) ? (string) $settings[ $k( 'card_origin' ) ] : '';

			$payload['card'] = [
				'st'  => 'in' === $state ? 'in' : 'out',
				'sc'  => round( min( 100, max( 50, $this->num( $settings, $k( 'card_scale' ), 90 ) ) ) / 100, 3 ),
				'r'   => (int) max( 0, $this->num( $settings, $k( 'card_radius' ), 54 ) ),
				'o'   => in_array( $origin, [ 'bottom center', 'center center', 'top center' ], true ) ? $origin : 'bottom center',
				'len' => (int) max( 20, $this->num( $settings, $k( 'card_length' ), 150 ) ),
			];
		}

		if ( in_array( 'counter_to', $params, true ) ) {
			$payload['to'] = (float) $this->num( $settings, $k( 'counter_to' ), 100 );
		}

		if ( in_array( 'loop', $params, true ) ) {
			$payload['loop'] = $this->on( $settings, $k( 'loop' ) ) ? 1 : 0;
		}

		if ( $preset['split'] ) {
			$payload['sp'] = $preset['split'];
		}

		$cascade = $this->on( $settings, $k( 'stagger' ) );

		if ( $cascade || $preset['split'] ) {
			$payload['st'] = [
				'sel'  => $cascade && ! empty( $settings[ $k( 'stagger_target' ) ] ) ? (string) $settings[ $k( 'stagger_target' ) ] : '> *',
				'each' => (int) $this->num( $settings, $k( 'stagger_each' ), 80 ),
				'from' => isset( $settings[ $k( 'stagger_from' ) ] ) ? (string) $settings[ $k( 'stagger_from' ) ] : 'start',
				'kids' => $cascade ? 1 : 0,
			];
		}

		if ( in_array( 'lottie_url', $params, true ) ) {
			$payload['lt'] = [
				'url'   => isset( $settings[ $k( 'lottie_url' ) ] ) ? esc_url_raw( (string) $settings[ $k( 'lottie_url' ) ] ) : '',
				'loop'  => $this->on( $settings, $k( 'lottie_loop' ) ) ? 1 : 0,
				'speed' => (float) $this->num( $settings, $k( 'lottie_speed' ), 1 ),
			];
		}

		if ( $this->on( $settings, $k( 'off_mobile' ) ) ) {
			$payload['om'] = 1;
		}

		/**
		 * Último ajuste no contrato enviado ao navegador.
		 *
		 * @param array  $payload   Dados.
		 * @param string $preset_id Id do preset.
		 * @param array  $settings  Ajustes do elemento.
		 */
		return apply_filters( 'wfan_payload', $payload, $preset_id, $settings );
	}

	/**
	 * Com o ajuste ligado, apaga a animação nativa do elemento que já tem uma
	 * animação WooFlow — senão as duas rodam juntas no mesmo elemento.
	 *
	 * @param \Elementor\Element_Base $element Elemento.
	 * @return void
	 */
	private function maybe_disable_native( $element ) {
		if ( ! WFAN_Settings::is_on( 'disable_native' ) || ! method_exists( $element, 'set_settings' ) ) {
			return;
		}

		$native = 'widget' === $element->get_type()
			? WFAN_Keys::NATIVE_WIDGET
			: WFAN_Keys::NATIVE_BLOCK;

		foreach ( [ 'name', 'tablet', 'mobile' ] as $slot ) {
			$element->set_settings( $native[ $slot ], '' );
		}
	}
}
