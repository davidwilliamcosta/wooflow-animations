<?php
/**
 * Ajustes globais, em Elementor → DW Animações.
 *
 * @package DW_Anim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DW_Anim_Settings {

	const OPTION = 'dwanim_settings';
	const SLUG   = 'dw-anim-settings';

	/**
	 * Cache da option.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register' ] );
		add_filter( 'plugin_action_links_' . DWANIM_BASENAME, [ $this, 'action_link' ] );
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
		add_submenu_page(
			'elementor',
			__( 'DW Animações', 'dw-copiar-animacao' ),
			__( 'DW Animações', 'dw-copiar-animacao' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
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
			sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Ajustes', 'dw-copiar-animacao' ) )
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
	 * @param mixed $input Valores crus do formulário.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : [];
		$clean   = [];
		$toggles = [ 'lib_gsap', 'lib_anime', 'lib_lottie', 'lenis_enable', 'lenis_wheel', 'lenis_touch', 'off_mobile', 'respect_reduced', 'disable_native', 'debug' ];

		foreach ( $toggles as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? '0' : '1';
		}

		$clean['lenis_lerp']     = (string) max( 0.01, min( 1, (float) ( $input['lenis_lerp'] ?? 0.1 ) ) );
		$clean['lenis_duration'] = (string) max( 0.1, min( 5, (float) ( $input['lenis_duration'] ?? 1.2 ) ) );
		$clean['mobile_bp']      = (string) max( 320, min( 1920, (int) ( $input['mobile_bp'] ?? 767 ) ) );

		self::$cache = null;

		return $clean;
	}

	/**
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s = self::all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DW Animações para Elementor', 'dw-copiar-animacao' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'As animações são configuradas em cada elemento, na aba Avançado → DW Animações. Aqui ficam só os ajustes que valem para o site inteiro.', 'dw-copiar-animacao' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION ); ?>
				<?php $name = self::OPTION; ?>

				<h2><?php esc_html_e( 'Bibliotecas', 'dw-copiar-animacao' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Nenhuma biblioteca é carregada por padrão: cada uma entra apenas nas páginas que usam um preset que a exige. Desmarcar aqui bloqueia o carregamento mesmo nessas páginas.', 'dw-copiar-animacao' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Permitir', 'dw-copiar-animacao' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[lib_gsap]" value="1" <?php checked( self::is_on( 'lib_gsap' ) ); ?>> <?php esc_html_e( 'GSAP + ScrollTrigger — presets de scroll travado, pin, parallax e contador', 'dw-copiar-animacao' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[lib_anime]" value="1" <?php checked( self::is_on( 'lib_anime' ) ); ?>> <?php esc_html_e( 'Anime.js — presets de SVG', 'dw-copiar-animacao' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[lib_lottie]" value="1" <?php checked( self::is_on( 'lib_lottie' ) ); ?>> <?php esc_html_e( 'Lottie — presets de Lottie (reusa a biblioteca do Elementor Pro quando ele estiver ativo)', 'dw-copiar-animacao' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Scroll suave (Lenis)', 'dw-copiar-animacao' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Ativar', 'dw-copiar-animacao' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[lenis_enable]" value="1" <?php checked( self::is_on( 'lenis_enable' ) ); ?>> <?php esc_html_e( 'Ligar o scroll suave no site inteiro', 'dw-copiar-animacao' ); ?></label>
							<p class="description"><?php esc_html_e( 'Fica desligado no editor do Elementor e quando o visitante pede menos movimento no sistema.', 'dw-copiar-animacao' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dwanim-lerp"><?php esc_html_e( 'Suavidade (lerp)', 'dw-copiar-animacao' ); ?></label></th>
						<td>
							<input id="dwanim-lerp" type="number" step="0.01" min="0.01" max="1" name="<?php echo esc_attr( $name ); ?>[lenis_lerp]" value="<?php echo esc_attr( $s['lenis_lerp'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Quanto menor, mais longo o deslize. 0,1 é o padrão.', 'dw-copiar-animacao' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dwanim-lenis-duration"><?php esc_html_e( 'Duração (s)', 'dw-copiar-animacao' ); ?></label></th>
						<td><input id="dwanim-lenis-duration" type="number" step="0.1" min="0.1" max="5" name="<?php echo esc_attr( $name ); ?>[lenis_duration]" value="<?php echo esc_attr( $s['lenis_duration'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Entradas', 'dw-copiar-animacao' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[lenis_wheel]" value="1" <?php checked( self::is_on( 'lenis_wheel' ) ); ?>> <?php esc_html_e( 'Suavizar a roda do mouse', 'dw-copiar-animacao' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[lenis_touch]" value="1" <?php checked( self::is_on( 'lenis_touch' ) ); ?>> <?php esc_html_e( 'Suavizar o toque no celular (não recomendado)', 'dw-copiar-animacao' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Comportamento', 'dw-copiar-animacao' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Acessibilidade', 'dw-copiar-animacao' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[respect_reduced]" value="1" <?php checked( self::is_on( 'respect_reduced' ) ); ?>> <?php esc_html_e( 'Respeitar "reduzir movimento" do sistema do visitante', 'dw-copiar-animacao' ); ?></label>
							<p class="description"><?php esc_html_e( 'Recomendado manter ligado. O conteúdo aparece normalmente, só sem movimento.', 'dw-copiar-animacao' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Celular', 'dw-copiar-animacao' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[off_mobile]" value="1" <?php checked( self::is_on( 'off_mobile' ) ); ?>> <?php esc_html_e( 'Desligar todas as animações em telas pequenas', 'dw-copiar-animacao' ); ?></label>
							<p>
								<label for="dwanim-bp"><?php esc_html_e( 'Largura limite (px)', 'dw-copiar-animacao' ); ?></label>
								<input id="dwanim-bp" type="number" step="1" min="320" max="1920" name="<?php echo esc_attr( $name ); ?>[mobile_bp]" value="<?php echo esc_attr( $s['mobile_bp'] ); ?>" class="small-text">
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Animação nativa', 'dw-copiar-animacao' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[disable_native]" value="1" <?php checked( self::is_on( 'disable_native' ) ); ?>> <?php esc_html_e( 'Desligar a animação de entrada do Elementor quando o mesmo elemento tiver uma animação DW', 'dw-copiar-animacao' ); ?></label>
							<p class="description"><?php esc_html_e( 'Evita as duas animações rodando juntas no mesmo elemento.', 'dw-copiar-animacao' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Diagnóstico', 'dw-copiar-animacao' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[debug]" value="1" <?php checked( self::is_on( 'debug' ) ); ?>> <?php esc_html_e( 'Registrar no console do navegador cada animação registrada e disparada', 'dw-copiar-animacao' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
