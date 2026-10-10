<?php
/**
 * Registry de assets.
 *
 * Único lugar do plugin autorizado a enfileirar biblioteca. Nada de GSAP em
 * página que não usa scroll travado. Ver CLAUDE.md, regra 5.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Assets {

	/** Tempo, em ms, após o qual o conteúdo é revelado mesmo sem o JS ter rodado. */
	const FAILSAFE_MS = 3000;

	/**
	 * Motores exigidos pelos elementos já renderizados nesta requisição.
	 *
	 * @var array<string,bool>
	 */
	private $needed = [];

	/**
	 * Algum elemento desta página pediu a folha do front-end sem motor nenhum.
	 *
	 * @var bool
	 */
	private $styles = false;

	/**
	 * Já saiu algum elemento com animação nesta página?
	 *
	 * @var bool
	 */
	private $used = false;

	/**
	 * Algum elemento desta página ligou o fundo animado.
	 *
	 * @var bool
	 */
	private $overlay = false;

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register' ], 5 );
		add_action( 'wp_head', [ $this, 'print_prehide' ], 1 );
		add_action( 'wp_footer', [ $this, 'enqueue_needed' ], 5 );

		// No preview do editor os widgets são re-renderizados por Ajax, então o
		// enfileiramento sob demanda não acompanha: lá carregamos tudo de uma vez.
		add_action( 'elementor/preview/enqueue_scripts', [ $this, 'enqueue_all_for_preview' ] );
	}

	/**
	 * Caminho de arquivo para cache-bust por filemtime().
	 *
	 * @param string $rel Caminho relativo à raiz do plugin.
	 * @return string
	 */
	public static function ver( $rel ) {
		$path = WFAN_DIR . $rel;

		return file_exists( $path ) ? (string) filemtime( $path ) : WFAN_VER;
	}

	/**
	 * @param string $rel Caminho relativo à raiz do plugin.
	 * @return string
	 */
	public static function url( $rel ) {
		return WFAN_URL . $rel;
	}

	/**
	 * Entrega uma configuração ao JS preservando os tipos.
	 *
	 * `wp_localize_script()` converte todo escalar do primeiro nível em string,
	 * e `"0"` é verdadeiro no JavaScript: um toggle desligado chegaria ligado no
	 * navegador. Ver CLAUDE.md, regra 17.
	 *
	 * @param string $handle Handle do script, já registrado.
	 * @param string $object Nome da variável global.
	 * @param array  $data   Dados.
	 * @return void
	 */
	public static function localize( $handle, $object, $data ) {
		// Os HEX garantem que nenhum rótulo vindo do banco (nome salvo na
		// biblioteca, por exemplo) feche o <script> em que o JSON é impresso.
		$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		wp_add_inline_script( $handle, 'var ' . $object . ' = ' . $json . ';', 'before' );
	}

	/**
	 * @return void
	 */
	public function register() {
		$libs = [
			'wfan-gsap'           => 'assets/lib/gsap/gsap.min.js',
			'wfan-scrolltrigger'  => 'assets/lib/gsap/ScrollTrigger.min.js',
			'wfan-anime'          => 'assets/lib/anime/anime.min.js',
			'wfan-lenis'          => 'assets/lib/lenis/lenis.min.js',
			'wfan-lottie-lib'     => 'assets/lib/lottie/lottie.min.js',
		];

		foreach ( $libs as $handle => $rel ) {
			$deps = 'wfan-scrolltrigger' === $handle ? [ 'wfan-gsap' ] : [];

			wp_register_script( $handle, self::url( $rel ), $deps, self::ver( $rel ), true );
		}

		wp_register_style( 'wfan-frontend', self::url( 'assets/css/frontend.css' ), [], self::ver( 'assets/css/frontend.css' ) );

		// Efeito global, independente de haver elemento animado na página:
		// quem enfileira é o WFAN_Blur, se estiver ligado nos Ajustes.
		wp_register_style( 'wfan-blur', self::url( 'assets/css/blur.css' ), [], self::ver( 'assets/css/blur.css' ) );

		wp_register_script( 'wfan-core', self::url( 'assets/js/frontend/core.js' ), [], self::ver( 'assets/js/frontend/core.js' ), true );
		self::localize( 'wfan-core', 'wfanConfig', $this->config() );

		$engines = [
			'css'    => [ 'assets/js/frontend/engine-css.js', [ 'wfan-core' ] ],
			'gsap'   => [ 'assets/js/frontend/engine-gsap.js', [ 'wfan-core', 'wfan-scrolltrigger' ] ],
			'anime'  => [ 'assets/js/frontend/engine-anime.js', [ 'wfan-core', 'wfan-anime' ] ],
			'lottie' => [ 'assets/js/frontend/engine-lottie.js', [ 'wfan-core' ] ],
		];

		foreach ( $engines as $engine => $spec ) {
			wp_register_script( 'wfan-engine-' . $engine, self::url( $spec[0] ), $spec[1], self::ver( $spec[0] ), true );
		}

		wp_register_script( 'wfan-lenis-boot', self::url( 'assets/js/frontend/lenis-boot.js' ), [ 'wfan-lenis' ], self::ver( 'assets/js/frontend/lenis-boot.js' ), true );

		// Fundo animado: não depende do core nem de biblioteca nenhuma, e um
		// elemento pode tê-lo sem ter animação alguma.
		wp_register_script( 'wfan-overlay', self::url( 'assets/js/frontend/overlay.js' ), [], self::ver( 'assets/js/frontend/overlay.js' ), true );
		self::localize( 'wfan-overlay', 'wfanOverlayConfig', $this->overlay_config() );
	}

	/**
	 * Configuração do fundo animado. Vai tipada pelo mesmo caminho do core, e
	 * pela mesma razão: `cfg.reduced` e `cfg.offMobile` são testados por
	 * veracidade no overlay.js. Ver CLAUDE.md, regra 17.
	 *
	 * @return array
	 */
	private function overlay_config() {
		return [
			'reduced'   => WFAN_Settings::is_on( 'respect_reduced' ),
			'offMobile' => WFAN_Settings::is_on( 'off_mobile' ),
			'mobileBp'  => (int) WFAN_Settings::get( 'mobile_bp', 767 ),
			'debug'     => WFAN_Settings::is_on( 'debug' ),
			'editor'    => $this->is_preview(),
			'hostClass' => WFAN_Overlay::HOST_CLASS,
			'canvas'    => WFAN_Overlay::CANVAS_CLASS,
			'vars'      => WFAN_Overlay::VARS,
		];
	}

	/**
	 * Configuração entregue ao core.js.
	 *
	 * @return array
	 */
	private function config() {
		$bezier = [];

		foreach ( WFAN_Presets::easings() as $name => $data ) {
			$bezier[ $name ] = $data['bezier'];
		}

		// Booleano de verdade, nunca 1/0: o core.js testa estes valores por
		// veracidade. Ver CLAUDE.md, regra 17.
		return [
			'reduced'  => WFAN_Settings::is_on( 'respect_reduced' ),
			'offMobile' => WFAN_Settings::is_on( 'off_mobile' ),
			'mobileBp' => (int) WFAN_Settings::get( 'mobile_bp', 767 ),
			'debug'    => WFAN_Settings::is_on( 'debug' ),
			'editor'   => $this->is_preview(),
			'bezier'   => $bezier,
			'failsafe' => self::FAILSAFE_MS,
		];
	}

	/**
	 * @return bool
	 */
	private function is_preview() {
		return class_exists( '\Elementor\Plugin' )
			&& \Elementor\Plugin::$instance->preview->is_preview_mode();
	}

	/**
	 * Um elemento renderizado pediu este motor.
	 *
	 * @param string $engine css | gsap | anime | lottie.
	 * @return void
	 */
	public function require_engine( $engine ) {
		$this->used = true;

		if ( ! WFAN_Settings::lib_allowed( $engine ) ) {
			return;
		}

		$this->needed[ $engine ] = true;
	}

	/**
	 * Um elemento renderizado pediu só a folha do front-end.
	 *
	 * É o caso dos presets resolvidos em CSS (`css_only`): o efeito sai no CSS
	 * que o Elementor gera por elemento, e a única coisa que falta é a rede de
	 * `prefers-reduced-motion` do frontend.css. Nenhum byte de JS entra.
	 *
	 * @return void
	 */
	public function require_style() {
		$this->used   = true;
		$this->styles = true;
	}

	/**
	 * Um elemento renderizado ligou o fundo animado.
	 *
	 * A folha do front-end vem junto porque é ela que posiciona o canvas e
	 * traduz as custom properties em opacidade, mistura e cor.
	 *
	 * @return void
	 */
	public function require_overlay() {
		$this->used    = true;
		$this->styles  = true;
		$this->overlay = true;
	}

	/**
	 * @return bool
	 */
	public function is_used() {
		return $this->used;
	}

	/**
	 * CSS e bootstrap que precisam estar no <head>, antes de qualquer pintura.
	 *
	 * Sai em toda página (são ~400 bytes) porque no <head> ainda não se sabe se
	 * a página tem animação — e um elemento escondido esperando um CSS que vem
	 * depois é exatamente o flash que se quer evitar.
	 *
	 * @return void
	 */
	public function print_prehide() {
		if ( $this->is_preview() ) {
			return;
		}

		?>
<style id="wfan-prehide">html.wfan-js .wfan-pending{opacity:0!important}html.wfan-js .wfan-pending.wfan-mask{clip-path:inset(0 0 100% 0)}@media(prefers-reduced-motion:reduce){html.wfan-js .wfan-pending{opacity:1!important;clip-path:none!important}}</style>
<script id="wfan-boot">document.documentElement.className+=" wfan-js";setTimeout(function(){if(!window.wfanLoaded){document.documentElement.classList.remove("wfan-js")}},<?php echo (int) self::FAILSAFE_MS; ?>);</script>
<noscript><style>html.wfan-js .wfan-pending{opacity:1!important;clip-path:none!important}</style></noscript>
		<?php
	}

	/**
	 * @return void
	 */
	public function enqueue_needed() {
		if ( ! $this->needed && ! $this->styles ) {
			return;
		}

		wp_enqueue_style( 'wfan-frontend' );

		if ( $this->overlay ) {
			wp_enqueue_script( 'wfan-overlay' );
		}

		if ( ! $this->needed ) {
			return;
		}

		wp_enqueue_script( 'wfan-core' );

		foreach ( array_keys( $this->needed ) as $engine ) {
			if ( 'lottie' === $engine ) {
				$this->enqueue_lottie_engine();
				continue;
			}

			wp_enqueue_script( 'wfan-engine-' . $engine );
		}
	}

	/**
	 * O Elementor Pro já registra o lottie-web sob o handle `lottie`.
	 * Reusar é obrigatório: duas cópias da mesma biblioteca na página.
	 *
	 * @return void
	 */
	private function enqueue_lottie_engine() {
		$handle = WFAN_Lottie::handle();

		wp_enqueue_script( $handle );

		global $wp_scripts;

		if ( isset( $wp_scripts->registered['wfan-engine-lottie'] ) ) {
			$deps = $wp_scripts->registered['wfan-engine-lottie']->deps;

			if ( ! in_array( $handle, $deps, true ) ) {
				$wp_scripts->registered['wfan-engine-lottie']->deps[] = $handle;
			}
		}

		wp_enqueue_script( 'wfan-engine-lottie' );
	}

	/**
	 * No preview do editor, carrega todos os motores permitidos de uma vez.
	 *
	 * @return void
	 */
	public function enqueue_all_for_preview() {
		$this->register();

		wp_enqueue_style( 'wfan-frontend' );
		wp_enqueue_script( 'wfan-core' );
		wp_enqueue_script( 'wfan-engine-css' );

		// O fundo animado precisa estar lá antes de o usuário ligar o toggle:
		// no editor não há segunda chance de enfileirar.
		wp_enqueue_script( 'wfan-overlay' );

		foreach ( [ 'gsap', 'anime' ] as $engine ) {
			if ( WFAN_Settings::lib_allowed( $engine ) ) {
				wp_enqueue_script( 'wfan-engine-' . $engine );
			}
		}

		if ( WFAN_Settings::lib_allowed( 'lottie' ) ) {
			$this->enqueue_lottie_engine();
		}
	}
}
