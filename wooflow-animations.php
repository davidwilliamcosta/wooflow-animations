<?php
/**
 * Plugin Name: WooFlow Animations for Elementor
 * Description: Painel de animações pronto para aplicar em qualquer elemento do Elementor — entrada, scroll, texto, ênfase, SVG e Lottie — mais as ações "Copiar animação" e "Colar animação" no menu de contexto e por atalho.
 * Version: 2.1.0
 * Author: David William da Costa
 * Author URI: https://davidwilliam.studio
 * Plugin URI: https://github.com/davidwilliamcosta/wooflow-animations
 * Requires Plugins: elementor
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wooflow-animations
 * Domain Path: /languages
 * Elementor tested up to: 4.3.4
 * Elementor Pro tested up to: 4.2.3
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// O cabeçalho Version: e a constante WFAN_VER andam sempre juntos. Ver CLAUDE.md, regra 1.
define( 'WFAN_VER', '2.1.0' );
define( 'WFAN_FILE', __FILE__ );
define( 'WFAN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFAN_URL', plugin_dir_url( __FILE__ ) );
define( 'WFAN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WFAN_TD', 'wooflow-animations' );

require_once WFAN_DIR . 'includes/class-requirements.php';
require_once WFAN_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', 'wfan_boot' );

/**
 * Sobe o plugin depois que todos os outros carregaram (o Elementor precisa existir).
 *
 * @return void
 */
function wfan_boot() {
	load_plugin_textdomain( WFAN_TD, false, dirname( WFAN_BASENAME ) . '/languages' );

	if ( ! WFAN_Requirements::met() ) {
		WFAN_Requirements::hook_notice();
		return;
	}

	WFAN_Plugin::instance();
}
