<?php
/**
 * Controle visual de presets: grade de cards com preview no hover.
 *
 * O template é montado aqui porque o catálogo é igual para todos os elementos;
 * o JS (assets/js/editor/control-picker.js) só cuida de seleção, busca e grupos.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Control_Picker extends \Elementor\Base_Data_Control {

	/**
	 * @return string
	 */
	public function get_type() {
		return 'wfan-picker';
	}

	/**
	 * @return array
	 */
	protected function get_default_settings() {
		return [
			'label_block' => true,
			'show_label'  => true,
		];
	}

	/**
	 * @return string
	 */
	public function get_default_value() {
		return '';
	}

	/**
	 * @return void
	 */
	public function content_template() {
		$groups  = WFAN_Presets::groups();
		$presets = WFAN_Presets::all();
		?>
		<div class="elementor-control-field wfan-picker">
			<# if ( data.show_label ) { #>
				<label class="elementor-control-title">{{{ data.label }}}</label>
			<# } #>
			<div class="elementor-control-input-wrapper">
				<input type="hidden" class="wfan-picker__value" data-setting="{{ data.name }}">

				<div class="wfan-picker__search">
					<input type="search" class="wfan-picker__q" placeholder="<?php echo esc_attr__( 'Buscar animação…', 'wooflow-animations' ); ?>" autocomplete="off">
				</div>

				<div class="wfan-picker__tabs">
					<button type="button" class="wfan-tab is-active" data-group="*"><?php echo esc_html__( 'Todas', 'wooflow-animations' ); ?></button>
					<?php foreach ( $groups as $slug => $label ) : ?>
						<button type="button" class="wfan-tab" data-group="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>

				<div class="wfan-picker__grid">
					<button type="button" class="wfan-card wfan-card--none" data-value="" data-group="*" data-label="<?php echo esc_attr__( 'Nenhuma', 'wooflow-animations' ); ?>">
						<span class="wfan-card__stage"><span class="wfan-card__box"></span></span>
						<span class="wfan-card__label"><?php echo esc_html__( 'Nenhuma', 'wooflow-animations' ); ?></span>
					</button>

					<?php foreach ( $presets as $id => $preset ) : ?>
						<button type="button"
							class="wfan-card"
							data-value="<?php echo esc_attr( $id ); ?>"
							data-group="<?php echo esc_attr( $preset['group'] ); ?>"
							data-engine="<?php echo esc_attr( $preset['engine'] ); ?>"
							data-label="<?php echo esc_attr( $preset['label'] ); ?>"
							title="<?php echo esc_attr( $preset['label'] ); ?>">
							<span class="wfan-card__stage">
								<span class="wfan-card__box wfan-pv-<?php echo esc_attr( $id ); ?>"></span>
							</span>
							<span class="wfan-card__label"><?php echo esc_html( $preset['label'] ); ?></span>
							<?php if ( 'css' !== $preset['engine'] ) : ?>
								<span class="wfan-card__engine"><?php echo esc_html( $preset['engine'] ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="wfan-picker__saved" data-wfan-saved></div>

				<# if ( data.description ) { #>
					<div class="elementor-control-field-description">{{{ data.description }}}</div>
				<# } #>
			</div>
		</div>
		<?php
	}
}
