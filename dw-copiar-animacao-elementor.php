<?php
/**
 * Plugin Name: DW Animações para Elementor
 * Description: Painel de animações pronto para aplicar em qualquer elemento do Elementor — entrada, scroll, texto, ênfase, SVG e Lottie — mais as ações "Copiar animação" e "Colar animação" no menu de contexto e por atalho.
 * Version: 2.0.0
 * Author: David William da Costa
 * Author URI: https://davidwilliam.studio
 * Plugin URI: https://github.com/davidwilliamcosta/dw-copiar-animacao-elementor
 * Requires Plugins: elementor
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dw-copiar-animacao
 * Domain Path: /languages
 * Elementor tested up to: 4.3.4
 * Elementor Pro tested up to: 4.2.3
 *
 * @package DW_Anim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// O cabeçalho Version: e a constante DWANIM_VER andam sempre juntos. Ver CLAUDE.md, regra 1.
define( 'DWANIM_VER', '2.0.0' );
define( 'DWANIM_FILE', __FILE__ );
define( 'DWANIM_DIR', plugin_dir_path( __FILE__ ) );
define( 'DWANIM_URL', plugin_dir_url( __FILE__ ) );
define( 'DWANIM_BASENAME', plugin_basename( __FILE__ ) );
define( 'DWANIM_TD', 'dw-copiar-animacao' );

require_once DWANIM_DIR . 'includes/class-requirements.php';
require_once DWANIM_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', 'dw_anim_boot' );

/**
 * Sobe o plugin depois que todos os outros carregaram (o Elementor precisa existir).
 *
 * @return void
 */
function dw_anim_boot() {
	load_plugin_textdomain( DWANIM_TD, false, dirname( DWANIM_BASENAME ) . '/languages' );

	if ( ! DW_Anim_Requirements::met() ) {
		DW_Anim_Requirements::hook_notice();
		return;
	}

	DW_Anim_Plugin::instance();
}
