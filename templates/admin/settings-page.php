<?php
/**
 * Casca da tela de ajustes: brand bar, navegação e include da aba.
 *
 * Só navegação, formulário e includes. Nenhum HTML de componente e nenhum
 * CSS — o estilo vive em assets/css/admin.css.
 *
 * @package WFAN
 *
 * @var array $wfan_settings Valores já mesclados com os padrões.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wfan_name = WFAN_Settings::OPTION;
$wfan_tabs = WFAN_Settings::tabs();

$wfan_make_url = static function ( $tab ) {
	return add_query_arg(
		[
			'page' => WFAN_Settings::SLUG,
			'tab'  => $tab,
		],
		admin_url( 'admin.php' )
	);
};

$wfan_keys   = array_keys( $wfan_tabs );
$wfan_active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( ! isset( $wfan_tabs[ $wfan_active ] ) ) {
	$wfan_active = reset( $wfan_keys );
}
?>
<div class="wrap wooflow-admin">

	<div class="wooflow-brand-bar">
		<div class="wooflow-brand-bar__main">
			<strong class="wooflow-brand-bar__name"><?php esc_html_e( 'WooFlow Animations', 'wooflow-animations' ); ?></strong>
			<span class="wooflow-brand-bar__desc"><?php esc_html_e( 'Painel de animações por elemento para o Elementor', 'wooflow-animations' ); ?></span>
		</div>
		<span class="wooflow-brand-bar__version">
			<?php
			printf(
				/* translators: %s: versão do plugin. */
				esc_html__( 'Versão %s', 'wooflow-animations' ),
				esc_html( WFAN_VER )
			);
			?>
		</span>
	</div>

	<div class="wooflow-notice wooflow-notice--info">
		<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
		<span>
			<?php esc_html_e( 'Cada animação é escolhida no próprio elemento: na aba Conteúdo dos widgets, e no topo da aba Layout de containers, seções e colunas. Aqui ficam só os ajustes que valem para o site inteiro.', 'wooflow-animations' ); ?>
		</span>
	</div>

	<nav class="wooflow-main-nav">
		<?php foreach ( $wfan_tabs as $wfan_key => $wfan_tab ) : ?>
			<a href="<?php echo esc_url( $wfan_make_url( $wfan_key ) ); ?>"
				class="wooflow-main-nav__item<?php echo ( $wfan_active === $wfan_key ) ? ' is-active' : ''; ?>"
				<?php echo ( $wfan_active === $wfan_key ) ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $wfan_tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php settings_errors(); ?>

	<?php
	/*
	 * Um formulário por aba, e é o campo oculto `_tab` que diz ao
	 * WFAN_Settings::sanitize() quais toggles este POST é dono —
	 * sem ele, salvar uma aba zeraria os checkboxes das outras,
	 * que nem chegam a ser enviados. Ver a regra 12 do CLAUDE.md.
	 */
	?>
	<form method="post" action="options.php">
		<?php settings_fields( $wfan_name ); ?>
		<input type="hidden" name="<?php echo esc_attr( $wfan_name ); ?>[_tab]" value="<?php echo esc_attr( $wfan_active ); ?>">

		<div class="wooflow-section">
			<?php require WFAN_DIR . 'templates/admin/tabs/tab-' . $wfan_active . '.php'; ?>
		</div>

		<div class="wooflow-form-actions">
			<?php submit_button( __( 'Salvar ajustes', 'wooflow-animations' ), 'primary', 'submit', false ); ?>
		</div>
	</form>

</div>
