<?php
/**
 * Plugin Name: DW Copiar Animação Elementor
 * Description: Adiciona ao Elementor as ações "Copiar animação" e "Colar animação" (menu de contexto e atalhos), levando apenas o efeito de entrada, sem estilos, rolagem ou mouse.
 * Version: 1.0.0
 * Author: David William da Costa
 * Author URI: https://davidwilliam.studio
 * Plugin URI: https://github.com/davidwilliamcosta/dw-copiar-animacao-elementor
 * Requires Plugins: elementor
 * Text Domain: dw-copiar-animacao
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injeta o script no rodapé do editor do Elementor.
 */
add_action( 'elementor/editor/footer', function () {
	?>
	<script id="dw-copiar-animacao-elementor">
	(function () {
		'use strict';

		var STORAGE_KEY = 'dwElementorCopiedAnimation';
		var memory = null;

		// Chaves de configuração: widgets usam prefixo "_", seções, colunas e containers não.
		var KEYS_WIDGET = {
			name: '_animation',
			tablet: '_animation_tablet',
			mobile: '_animation_mobile',
			duration: 'animation_duration',
			delay: '_animation_delay'
		};
		var KEYS_BLOCK = {
			name: 'animation',
			tablet: 'animation_tablet',
			mobile: 'animation_mobile',
			duration: 'animation_duration',
			delay: 'animation_delay'
		};

		function keysFor(container) {
			return container.model.get('elType') === 'widget' ? KEYS_WIDGET : KEYS_BLOCK;
		}

		function toast(message) {
			try {
				elementor.notifications.showToast({ message: message });
			} catch (e) {}
		}

		function getStored() {
			if (memory) {
				return memory;
			}
			try {
				var raw = window.localStorage.getItem(STORAGE_KEY);
				return raw ? JSON.parse(raw) : null;
			} catch (e) {
				return null;
			}
		}

		function setStored(data) {
			memory = data;
			try {
				window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
			} catch (e) {}
		}

		function selectedContainers() {
			var list = [];
			try {
				list = elementor.selection.getElements() || [];
			} catch (e) {}
			return list.filter(function (c) {
				return c && c.model && c.settings;
			});
		}

		function copyAnimation(container) {
			if (!container || !container.settings) {
				return;
			}
			var keys = keysFor(container);
			var data = {};
			Object.keys(keys).forEach(function (k) {
				data[k] = container.settings.get(keys[k]) || '';
			});
			setStored(data);
			toast(data.name ? 'Animação copiada: ' + data.name : 'Elemento sem animação copiado (colar vai remover a animação do destino).');
		}

		function pasteAnimation(containers) {
			var data = getStored();
			if (!data) {
				toast('Nenhuma animação copiada ainda.');
				return;
			}
			containers.forEach(function (container) {
				var keys = keysFor(container);
				var settings = {};
				Object.keys(keys).forEach(function (k) {
					settings[keys[k]] = data[k] || '';
				});
				$e.run('document/elements/settings', {
					container: container,
					settings: settings
				});
			});
			toast('Animação colada em ' + containers.length + (containers.length === 1 ? ' elemento.' : ' elementos.'));
		}

		// Fecha o menu de contexto (no documento do editor e no iframe de preview).
		function closeMenu() {
			var docs = [document];
			try {
				docs.push(elementor.$preview[0].contentWindow.document);
			} catch (e) {}
			docs.forEach(function (doc) {
				try {
					jQuery(doc).find('.elementor-context-menu').hide();
				} catch (e) {}
			});
		}

		// Itens do menu de contexto (botão direito).
		function addGroup(groups, view) {
			var container = view || null; // O container real só é resolvido no clique, nunca na criação do menu.
			if (!container) {
				return groups;
			}
			groups.push({
				name: 'dw-animation',
				actions: [
					{
						name: 'dw-copy-animation',
						icon: 'eicon-animation',
						title: 'Copiar animação',
						shortcut: 'Ctrl+Alt+C',
						callback: function () {
							try {
								copyAnimation(container.getContainer());
							} catch (e) {
								console.error('DW Copiar Animação:', e);
							}
							closeMenu();
						}
					},
					{
						name: 'dw-paste-animation',
						icon: 'eicon-animation',
						title: 'Colar animação',
						shortcut: 'Ctrl+Alt+V',
						isEnabled: function () {
							return !!getStored();
						},
						callback: function () {
							var selected = selectedContainers();
							var isSelected = selected.some(function (c) {
								return c.id === container.getContainer().id;
							});
							try {
								pasteAnimation(isSelected && selected.length ? selected : [container.getContainer()]);
							} catch (e) {
								console.error('DW Copiar Animação:', e);
							}
							closeMenu();
						}
					}
				]
			});
			return groups;
		}

		// Atalhos de teclado: Ctrl+Alt+C e Ctrl+Alt+V (Cmd+Option no Mac).
		function onKeyDown(e) {
			if (!(e.ctrlKey || e.metaKey) || !e.altKey || e.shiftKey) {
				return;
			}
			var tag = e.target && e.target.tagName ? e.target.tagName.toLowerCase() : '';
			if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) {
				return;
			}
			var selected = selectedContainers();
			if (!selected.length) {
				return;
			}
			if (e.code === 'KeyC') {
				e.preventDefault();
				copyAnimation(selected[0]);
			} else if (e.code === 'KeyV') {
				e.preventDefault();
				pasteAnimation(selected);
			}
		}

		function bindKeys(doc) {
			if (!doc || doc.__dwAnimBound) {
				return;
			}
			doc.__dwAnimBound = true;
			doc.addEventListener('keydown', onKeyDown, true);
		}

		function bindPreview() {
			try {
				bindKeys(elementor.$preview[0].contentWindow.document);
			} catch (e) {}
		}

		function init() {
			['widget', 'section', 'column', 'container'].forEach(function (type) {
				elementor.hooks.addFilter('elements/' + type + '/contextMenuGroups', addGroup);
			});
			bindKeys(document);
			bindPreview();
			if (typeof elementor.on === 'function') {
				elementor.on('preview:loaded', bindPreview);
			}
		}

		// Aguarda o editor ficar disponível.
		var tries = 0;
		var timer = setInterval(function () {
			tries++;
			if (window.elementor && elementor.hooks && window.$e) {
				clearInterval(timer);
				init();
			} else if (tries > 200) {
				clearInterval(timer);
			}
		}, 100);
	})();
	</script>
	<?php
} );
