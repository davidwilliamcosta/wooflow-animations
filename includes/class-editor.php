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

	public function __construct() {
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'scripts' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'styles' ] );
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

		wp_localize_script( 'wfan-copy-paste', 'wfanEditor', $this->data() );
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
			'debug'    => WFAN_Settings::is_on( 'debug' ) ? 1 : 0,
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( WFAN_Library::NONCE ),
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
