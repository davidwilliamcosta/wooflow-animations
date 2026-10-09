<?php
/**
 * Assets e dados do editor.
 *
 * O script antigo era injetado inline em `elementor/editor/footer` e esperava o
 * editor num setInterval. Agora é arquivo enfileirado com cache-bust, e quem
 * avisa que o editor subiu é o evento `elementor/init`.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Editor {

	/** Ação ajax do ▶ Testar. O mesmo nome é usado no panel.js. */
	const PREVIEW_ACTION = 'wfan_preview_spec';

	/** Quantos elementos o ▶ Testar aceita de uma vez. */
	const PREVIEW_MAX = 20;

	public function __construct() {
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'scripts' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'styles' ] );
		add_action( 'wp_ajax_' . self::PREVIEW_ACTION, [ $this, 'ajax_preview_spec' ] );
	}

	/**
	 * Devolve ao painel o contrato que o elemento teria no front-end.
	 *
	 * Existe porque no canvas do editor o wrapper é do Backbone do Elementor e
	 * não carrega o nosso `data-wfan` — o `print_element()` do PHP só roda no
	 * front-end e na carga inicial do preview. Sem isto, o ▶ Testar não teria o
	 * que ler, e a alternativa seria redecidir motor e gatilho em JavaScript.
	 *
	 * Nonce e capability são os mesmos do resto do painel: é a mesma tela, para
	 * quem já pode editar o documento.
	 *
	 * @return void
	 */
	public function ajax_preview_spec() {
		check_ajax_referer( WFAN_Library::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão.', 'wooflow-animations' ) ], 403 );
		}

		$items = isset( $_POST['items'] ) ? json_decode( wp_unslash( $_POST['items'] ), true ) : null;

		if ( ! is_array( $items ) ) {
			wp_send_json_error( [ 'message' => __( 'Não foi possível concluir.', 'wooflow-animations' ) ], 400 );
		}

		$render = new WFAN_Render();
		$out    = [];

		foreach ( array_slice( $items, 0, self::PREVIEW_MAX ) as $item ) {
			// `sanitize_html_class` e não `sanitize_key`: o id volta para dentro
			// de um seletor no preview, e `sanitize_key` deixaria tudo
			// minúsculo — id com maiúscula nunca mais seria encontrado.
			$id = isset( $item['id'] ) ? sanitize_html_class( (string) $item['id'] ) : '';

			if ( '' === $id || ! isset( $item['settings'] ) ) {
				continue;
			}

			// Nada de pré-esconde no editor: o elemento tem de continuar
			// visível enquanto se edita.
			$spec = $render->spec( WFAN_Library::sanitize_settings( $item['settings'] ), false );

			if ( $spec ) {
				$out[] = [
					'id'   => $id,
					'spec' => $spec['payload'],
				];
			}
		}

		if ( ! $out ) {
			wp_send_json_error( [ 'message' => __( 'Escolha uma animação primeiro.', 'wooflow-animations' ) ], 400 );
		}

		wp_send_json_success( [ 'items' => $out ] );
	}

	/**
	 * @return void
	 */
	public function styles() {
		wp_enqueue_style(
			'wfan-editor',
			WFAN_Assets::url( 'assets/css/editor.css' ),
			[],
			WFAN_Assets::ver( 'assets/css/editor.css' )
		);
	}

	/**
	 * @return void
	 */
	public function scripts() {
		$files = [
			'wfan-copy-paste'    => 'assets/js/editor/copy-paste.js',
			'wfan-control-picker' => 'assets/js/editor/control-picker.js',
			'wfan-panel'         => 'assets/js/editor/panel.js',
		];

		$previous = [ 'jquery' ];

		foreach ( $files as $handle => $rel ) {
			wp_enqueue_script(
				$handle,
				WFAN_Assets::url( $rel ),
				$previous,
				WFAN_Assets::ver( $rel ),
				true
			);

			$previous = [ $handle ];
		}

		WFAN_Assets::localize( 'wfan-copy-paste', 'wfanEditor', $this->data() );
	}

	/**
	 * @return array
	 */
	private function data() {
		return [
			'presets'  => WFAN_Presets::for_js(),
			'groups'   => WFAN_Presets::groups(),
			'triggers' => WFAN_Presets::triggers(),
			'library'  => WFAN_Library::all(),
			'debug'    => WFAN_Settings::is_on( 'debug' ),
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( WFAN_Library::NONCE ),
			'previewAction' => self::PREVIEW_ACTION,
			'keys'     => [
				'prefix'       => WFAN_Keys::P,
				'ours'         => WFAN_Keys::all_ours(),
				'preset'       => WFAN_Keys::ours( 'preset' ),
				'trigger'      => WFAN_Keys::ours( 'trigger' ),
				'nativeWidget' => WFAN_Keys::NATIVE_WIDGET,
				'nativeBlock'  => WFAN_Keys::NATIVE_BLOCK,
				'version'      => WFAN_Keys::PAYLOAD_VERSION,
			],
			'i18n'     => [
				'copyTitle'    => __( 'Copiar animação', 'wooflow-animations' ),
				'pasteTitle'   => __( 'Colar animação', 'wooflow-animations' ),
				'copied'       => __( 'Animação copiada.', 'wooflow-animations' ),
				'copiedEmpty'  => __( 'Elemento sem animação copiado — colar vai limpar a animação do destino.', 'wooflow-animations' ),
				'nothingYet'   => __( 'Nenhuma animação copiada ainda.', 'wooflow-animations' ),
				/* translators: %d: quantidade de elementos */
				'pastedOne'    => __( 'Animação colada em %d elemento.', 'wooflow-animations' ),
				/* translators: %d: quantidade de elementos */
				'pastedMany'   => __( 'Animação colada em %d elementos.', 'wooflow-animations' ),
				'historyPaste' => __( 'Colar animação', 'wooflow-animations' ),
				'askName'      => __( 'Nome para esta animação:', 'wooflow-animations' ),
				'saved'        => __( 'Animação salva na biblioteca.', 'wooflow-animations' ),
				'savedTitle'   => __( 'Minhas animações', 'wooflow-animations' ),
				'deleteOne'    => __( 'Apagar', 'wooflow-animations' ),
				'noPreset'     => __( 'Escolha uma animação primeiro.', 'wooflow-animations' ),
				'selectOne'    => __( 'Selecione um elemento primeiro.', 'wooflow-animations' ),
				'playing'      => __( 'Reproduzindo no preview…', 'wooflow-animations' ),
				'error'        => __( 'Não foi possível concluir.', 'wooflow-animations' ),
			],
		];
	}
}
