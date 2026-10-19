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
	 * Ícone e posição do menu-pai da família, usados só quando é este plugin
	 * que o cria. Os mesmos do `wooflow-update-product-view`, para o menu
	 * nascer igual não importa qual plugin da linha chegou primeiro.
	 */
	const PARENT_ICON     = 'dashicons-store';
	const PARENT_POSITION = 56;

	/**
	 * Prioridade do `admin_menu`, igual à do resto dos satélites da linha.
	 *
	 * O hub (WooFlow Admin) registra o pai na 5, e os satélites que criam o
	 * pai quando ele falta rodam na 20. Registrar na 10 — como era até aqui —
	 * é chegar antes deles: a checagem não via pai nenhum e o plugin acabava
	 * num menu só dele, do lado do menu WooFlow em vez de dentro dele.
	 *
	 * @var int
	 */
	const MENU_PRIORITY = 20;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ], self::MENU_PRIORITY );
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
			/*
			 * Botão "voltar ao topo". Nenhum padrão numérico daqui pode ser
			 * '0' nem '1': o smoke test reconhece toggle por esse valor nos
			 * padrões, e um afastamento zerado de fábrica viraria toggle
			 * fantasma sem aba. Valor 0 gravado pelo usuário passa normal.
			 */
			'btt_enable'      => '0',
			'btt_pos'         => 'right',
			'btt_shape'       => 'circle',
			'btt_icon'        => 'arrow',
			'btt_anim'        => 'fade',
			'btt_x'           => '24',
			'btt_y'           => '24',
			'btt_size'        => '48',
			'btt_bg'          => '#111827',
			'btt_fg'          => '#ffffff',
			'btt_bg_hover'    => '#7c3aed',
			'btt_threshold'   => '400',
			'btt_duration'    => '0.8',
			'btt_z'           => '1000',
			'btt_ring'        => '0',
			'btt_off_mobile'  => '0',
			// Barra fixa. Mesma regra dos padrões do botão: nenhum valor
			// numérico de fábrica pode ser '0' nem '1'.
			'hdr_enable'      => '0',
			'hdr_fixed'       => '1',
			'hdr_shadow'      => '1',
			'hdr_distance'    => '280',
			'hdr_smooth'      => '220',
			'hdr_shadow_op'   => '6',
			'hdr_z'           => '999',
			// Popup circular. Mesma regra: nenhum padrão numérico '0' ou '1'.
			'pop_enable'      => '0',
			'pop_blur_on'     => '1',
			'pop_off_mobile'  => '0',
			'pop_open'        => '850',
			'pop_close'       => '520',
			'pop_blur'        => '10',
			'pop_near'        => '30',
			'pop_far'         => '68',
			'pop_overlay'     => '#0e1016',
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
		$nosso  = false;

		// Sem ninguém da família no ar, o pai é criado aqui — é o que o
		// `wooflow-update-product-view` faz, e é o que mantém a linha inteira
		// reunida num item só, em qualquer combinação de plugins instalados.
		if ( '' === $parent ) {
			$parent = self::HUB_PARENTS[0];
			$nosso  = true;

			add_menu_page(
				__( 'WooFlow', 'wooflow-animations' ),
				__( 'WooFlow', 'wooflow-animations' ),
				'manage_options',
				$parent,
				'__return_null',
				self::PARENT_ICON,
				self::PARENT_POSITION
			);
		}

		/*
		 * Sob o menu WooFlow o nome do produto já está no pai, então o rótulo
		 * curto evita "WooFlow → WooFlow Animations".
		 */
		add_submenu_page(
			$parent,
			__( 'WooFlow Animations', 'wooflow-animations' ),
			__( 'Animations', 'wooflow-animations' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);

		/*
		 * `add_menu_page()` cria um primeiro submenu com o rótulo do pai, que
		 * aqui apontaria para um `__return_null` — item que não leva a lugar
		 * nenhum. Ele só sai quando o pai é nosso: com o hub ativo, esse
		 * primeiro item é o Painel dele e tem de ficar.
		 */
		if ( $nosso ) {
			remove_submenu_page( $parent, $parent );
		}
	}


	/**
	 * Resolve o menu-pai: o hub WooFlow quando a família está instalada,
	 * string vazia quando o plugin está sozinho — e aí ele faz menu próprio.
	 *
	 * O hub (WooFlow Admin) registra o pai no `admin_menu` com prioridade 5
	 * e este método roda na 10, então `$admin_page_hooks` já está populado
	 * quando a checagem acontece.
	 *
	 * A capability continua `manage_options`: este plugin não depende do
	 * WooCommerce, e exigir `manage_woocommerce` deixaria a tela inacessível
	 * num site que tem Elementor e não tem Woo — ninguém teria a capability.
	 *
	 * @return string Slug do hub, ou '' se não houver hub.
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

		return '';
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
			'topo'          => [
				'label'   => __( 'Voltar ao topo', 'wooflow-animations' ),
				'toggles' => [ 'btt_enable', 'btt_ring', 'btt_off_mobile' ],
			],
			'cabecalho'     => [
				'label'   => __( 'Barra fixa', 'wooflow-animations' ),
				'toggles' => [ 'hdr_enable', 'hdr_fixed', 'hdr_shadow' ],
			],
			'popup'         => [
				'label'   => __( 'Popup circular', 'wooflow-animations' ),
				'toggles' => [ 'pop_enable', 'pop_blur_on', 'pop_off_mobile' ],
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
			/*
			 * O mínimo 0 de afastamento e limiar é de propósito; o PADRÃO é que
			 * não pode ser 0 nem 1 — ver o comentário em defaults().
			 */
			'btt_x'          => [ 0, 200, 24, false ],
			'btt_y'          => [ 0, 200, 24, false ],
			'btt_size'       => [ 28, 96, 48, false ],
			'btt_threshold'  => [ 0, 20000, 400, false ],
			'btt_duration'   => [ 0.1, 3, 0.8, true ],
			'btt_z'          => [ 0, 999999, 1000, false ],
			'hdr_distance'   => [ 40, 2000, 280, false ],
			'hdr_smooth'     => [ 0, 1000, 220, false ],
			'hdr_shadow_op'  => [ 0, 100, 6, false ],
			'hdr_z'          => [ 0, 999999, 999, false ],
			'pop_open'     => [ 100, 3000, 850, false ],
			'pop_close'    => [ 100, 3000, 520, false ],
			'pop_blur'     => [ 0, 40, 10, false ],
			'pop_near'     => [ 0, 100, 30, false ],
			'pop_far'      => [ 0, 100, 68, false ],
			'mobile_bp'      => [ 320, 1920, 767, false ],
		];
	}

	/**
	 * Campos de escolha e os valores que cada um aceita.
	 *
	 * O padrão NÃO é repetido aqui: ele sai de `defaults()`, que continua mapa
	 * único. Foi para não ter duas cópias de 'bottom' envelhecendo em paralelo
	 * que o `if` solto do blur_pos virou este mapa.
	 *
	 * Público porque o template monta os <select> a partir dos rótulos dos
	 * módulos, e o smoke test confere que as duas listas não divergiram:
	 * opção oferecida e recusada aqui é campo que volta sozinho ao salvar.
	 *
	 * @return array<string,string[]>
	 */
	public static function selects() {
		return [
			'blur_pos'  => [ 'bottom', 'top', 'both' ],
			'btt_pos'   => [ 'right', 'left', 'center' ],
			'btt_shape' => [ 'circle', 'rounded', 'square' ],
			'btt_icon'  => [ 'arrow', 'chevron', 'double' ],
			'btt_anim'  => [ 'fade', 'slide-up', 'zoom' ],
		];
	}

	/**
	 * Campos de cor. O padrão de cada um também sai de `defaults()`.
	 *
	 * @return string[]
	 */
	public static function colors() {
		return [ 'btt_bg', 'btt_fg', 'btt_bg_hover', 'pop_overlay' ];
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

		$fallback = self::defaults();

		foreach ( self::selects() as $key => $allowed ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			// sanitize_key() mantém o hífen de 'slide-up' e derruba o resto.
			$value = sanitize_key( (string) $input[ $key ] );

			$clean[ $key ] = in_array( $value, $allowed, true ) ? $value : $fallback[ $key ];
		}

		foreach ( self::colors() as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			/*
			 * Nunca por sanitize_key(), que minúsculiza '#FFF'.
			 * sanitize_hex_color() devolve null em valor inválido e '' em campo
			 * vazio — as duas formas são falsy, e as duas voltam ao padrão. Sem
			 * esse resgate, uma cor vazia iria para a custom property como
			 * `--wfan-btt-bg:;`, que invalida a declaração e deixa o botão
			 * transparente, com o ícone solto no ar.
			 */
			$hex = sanitize_hex_color( trim( (string) $input[ $key ] ) );

			$clean[ $key ] = $hex ? $hex : $fallback[ $key ];
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
