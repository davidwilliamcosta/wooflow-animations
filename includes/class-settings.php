<?php
/**
 * Ajustes globais, em Elementor → WooFlow Animations.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Settings {

	const OPTION = 'wfan_settings';
	const SLUG   = 'wfan-settings';

	/**
	 * Cache da option.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Slug do menu-pai da linha WooFlow, e o legado do Checkout.
	 *
	 * @var string[]
	 */
	const HUB_PARENTS = [ 'wooflow', 'wooflow-checkout' ];

	/**
	 * Pai usado quando nenhum plugin da família registrou o hub.
	 *
	 * @var string
	 */
	const FALLBACK_PARENT = 'elementor';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'in_admin_header', [ $this, 'suppress_external_notices' ], 999 );
		add_filter( 'admin_body_class', [ $this, 'body_class' ] );
		add_filter( 'plugin_action_links_' . WFAN_BASENAME, [ $this, 'action_link' ] );
	}

	/**
	 * Valores padrão. Toggles são gravados como '1'/'0' porque
	 * update_option( $chave, false ) não persiste quando a option não existe.
	 *
	 * @return array
	 */
	public static function defaults() {
		return [
			'lib_gsap'        => '1',
			'lib_anime'       => '1',
			'lib_lottie'      => '1',
			'lenis_enable'    => '0',
			'lenis_lerp'      => '0.1',
			'lenis_duration'  => '1.2',
			'lenis_wheel'     => '1',
			'lenis_touch'     => '0',
			'blur_enable'     => '0',
			'blur_pos'        => 'bottom',
			'blur_height'     => '20',
			'blur_strength'   => '7',
			'blur_z'          => '999',
			'blur_off_mobile' => '0',
			'off_mobile'      => '0',
			'mobile_bp'       => '767',
			'respect_reduced' => '1',
			'disable_native'  => '0',
			'debug'           => '0',
		];
	}

	/**
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, [] );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : [], self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * @param string $key     Chave.
	 * @param mixed  $default Valor se a chave não existir.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Lê um toggle aceitando as duas formas que já apareceram na prática:
	 * '1'/'0' do formulário e true/false/'yes'/'no' de código.
	 *
	 * @param string $key Chave.
	 * @return bool
	 */
	public static function is_on( $key ) {
		$value = self::get( $key, '0' );

		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( (string) $value, [ '1', 'yes', 'true', 'on' ], true );
	}

	/**
	 * Uma biblioteca só é enfileirada se estiver permitida aqui.
	 *
	 * @param string $lib gsap | anime | lottie.
	 * @return bool
	 */
	public static function lib_allowed( $lib ) {
		if ( ! in_array( $lib, [ 'gsap', 'anime', 'lottie' ], true ) ) {
			return true;
		}

		return self::is_on( 'lib_' . $lib );
	}

	/**
	 * @return void
	 */
	public function menu() {
		$parent = self::parent_slug();

		/*
		 * Sob o menu WooFlow o nome do produto já está no pai, então o
		 * rótulo curto evita "WooFlow → WooFlow Animations". No Elementor
		 * o rótulo precisa se identificar sozinho.
		 */
		$label = self::FALLBACK_PARENT === $parent
			? __( 'WooFlow Animations', 'wooflow-animations' )
			: __( 'Animations', 'wooflow-animations' );

		add_submenu_page(
			$parent,
			__( 'WooFlow Animations', 'wooflow-animations' ),
			$label,
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Resolve o menu-pai: o hub WooFlow quando a família está instalada,
	 * o Elementor quando o plugin está sozinho.
	 *
	 * O hub (WooFlow Admin) registra o pai no `admin_menu` com prioridade 5
	 * e este método roda na 10, então `$admin_page_hooks` já está populado
	 * quando a checagem acontece. O Elementor registra o menu dele só na 20,
	 * mas isso não atrapalha o fallback: `add_submenu_page()` acumula em
	 * `$submenu['elementor']` e o pai aparece depois.
	 *
	 * A capability continua `manage_options`: este plugin não depende do
	 * WooCommerce, e exigir `manage_woocommerce` deixaria a tela inacessível
	 * num site que tem Elementor e não tem Woo — ninguém teria a capability.
	 *
	 * @return string
	 */
	public static function parent_slug() {
		if ( class_exists( 'WFA_Menu' ) && method_exists( 'WFA_Menu', 'resolve_parent_slug' ) ) {
			$resolved = WFA_Menu::resolve_parent_slug();

			if ( is_string( $resolved ) && '' !== $resolved ) {
				return $resolved;
			}
		}

		global $admin_page_hooks, $menu;

		foreach ( self::HUB_PARENTS as $slug ) {
			if ( isset( $admin_page_hooks[ $slug ] ) ) {
				return $slug;
			}

			foreach ( (array) $menu as $item ) {
				if ( isset( $item[2] ) && $slug === $item[2] ) {
					return $slug;
				}
			}
		}

		return self::FALLBACK_PARENT;
	}

	/**
	 * Body class estável para o CSS: independe de a tela ser
	 * `elementor_page_wfan-settings` ou `wooflow_page_wfan-settings`.
	 *
	 * @param string $classes Classes do body.
	 * @return string
	 */
	public function body_class( $classes ) {
		if ( self::is_our_screen() ) {
			$classes .= ' wooflow-admin-screen';
		}

		return $classes;
	}

	/**
	 * Remove notices de terceiros na nossa tela. As mensagens do próprio
	 * plugin são impressas no template, em `.wooflow-notice`.
	 *
	 * @return void
	 */
	public function suppress_external_notices() {
		if ( ! self::is_our_screen() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	/**
	 * @return bool
	 */
	protected static function is_our_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		$id = isset( $screen->id ) ? (string) $screen->id : '';

		return false !== strpos( $id, self::SLUG );
	}

	/**
	 * O CSS do painel entra só na nossa tela.
	 *
	 * Sem dependência de `woocommerce_admin_styles`: o plugin não requer
	 * WooCommerce, e um `wp_enqueue_style()` com dependência inexistente é
	 * descartado em silêncio pelo WordPress — a tela sairia sem estilo
	 * nenhum em site sem Woo.
	 *
	 * @param string $hook Sufixo da tela atual.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'wfan-admin',
			WFAN_Assets::url( 'assets/css/admin.css' ),
			[],
			WFAN_Assets::ver( 'assets/css/admin.css' )
		);
	}

	/**
	 * @param array $links Links de ação na lista de plugins.
	 * @return array
	 */
	public function action_link( $links ) {
		$url = admin_url( 'admin.php?page=' . self::SLUG );

		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Ajustes', 'wooflow-animations' ) )
		);

		return $links;
	}

	/**
	 * @return void
	 */
	public function register() {
		register_setting(
			self::OPTION,
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => self::defaults(),
			]
		);
	}

	/**
	 * Abas da tela e os toggles que cada uma é dona.
	 *
	 * Mapa único de propósito: a navegação, o nome do template
	 * (`templates/admin/tabs/tab-<chave>.php`) e o recorte do `sanitize()`
	 * saem todos daqui. Toggle que não estiver em aba nenhuma nunca é
	 * gravado pelo formulário — o smoke test reprova quem esquecer.
	 *
	 * @return array<string,array{label:string,toggles:string[]}>
	 */
	public static function tabs() {
		return [
			'bibliotecas'   => [
				'label'   => __( 'Bibliotecas', 'wooflow-animations' ),
				'toggles' => [ 'lib_gsap', 'lib_anime', 'lib_lottie' ],
			],
			'scroll'        => [
				'label'   => __( 'Scroll suave', 'wooflow-animations' ),
				'toggles' => [ 'lenis_enable', 'lenis_wheel', 'lenis_touch' ],
			],
			'blur'          => [
				'label'   => __( 'Blur progressivo', 'wooflow-animations' ),
				'toggles' => [ 'blur_enable', 'blur_off_mobile' ],
			],
			'comportamento' => [
				'label'   => __( 'Comportamento', 'wooflow-animations' ),
				'toggles' => [ 'respect_reduced', 'off_mobile', 'disable_native', 'debug' ],
			],
		];
	}

	/**
	 * Todos os toggles, na ordem das abas.
	 *
	 * @return string[]
	 */
	public static function toggles() {
		$all = [];

		foreach ( self::tabs() as $tab ) {
			$all = array_merge( $all, $tab['toggles'] );
		}

		return $all;
	}

	/**
	 * Campos numéricos: [ mínimo, máximo, padrão, aceita decimal ].
	 *
	 * @return array<string,array{0:float,1:float,2:float,3:bool}>
	 */
	private static function numbers() {
		return [
			'lenis_lerp'     => [ 0.01, 1, 0.1, true ],
			'lenis_duration' => [ 0.1, 5, 1.2, true ],
			'blur_height'    => [ 1, 100, 20, false ],
			'blur_strength'  => [ 1, 40, 7, false ],
			'blur_z'         => [ 0, 999999, 999, false ],
			'mobile_bp'      => [ 320, 1920, 767, false ],
		];
	}

	/**
	 * A tela salva **uma aba por vez**, e o POST só traz os campos dela.
	 *
	 * Daí partir dos valores já gravados em vez de um array vazio: a regra
	 * 12 (checkbox desmarcado some do POST) vale só para os toggles da aba
	 * enviada, que o campo oculto `_tab` identifica. Zerar a lista inteira
	 * aqui faria salvar uma aba desligar os toggles de todas as outras.
	 *
	 * Sem `_tab` conhecido — POST programático, ou um formulário futuro com
	 * tudo junto — o recorte volta a ser a lista completa.
	 *
	 * @param mixed $input Valores crus do formulário.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : [];
		$tabs  = self::tabs();
		$tab   = sanitize_key( (string) ( $input['_tab'] ?? '' ) );
		$clean = self::all();

		foreach ( isset( $tabs[ $tab ] ) ? $tabs[ $tab ]['toggles'] : self::toggles() as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? '0' : '1';
		}

		foreach ( self::numbers() as $key => $spec ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			list( $min, $max, $default, $decimal ) = $spec;

			// Campo apagado volta ao padrão, em vez de desabar para o mínimo.
			$raw = '' === trim( (string) $input[ $key ] ) ? $default : $input[ $key ];

			$value = $decimal ? (float) $raw : (int) $raw;

			$clean[ $key ] = (string) max( $min, min( $max, $value ) );
		}

		if ( isset( $input['blur_pos'] ) ) {
			$blur_pos          = sanitize_key( (string) $input['blur_pos'] );
			$clean['blur_pos'] = in_array( $blur_pos, [ 'bottom', 'top', 'both' ], true ) ? $blur_pos : 'bottom';
		}

		unset( $clean['_tab'] );

		self::flush();

		return $clean;
	}

	/**
	 * Descarta o cache da option. Necessário depois de qualquer gravação
	 * feita fora do formulário — a leitura é memoizada por requisição.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wfan_settings = self::all();

		require WFAN_DIR . 'templates/admin/settings-page.php';
	}
}
