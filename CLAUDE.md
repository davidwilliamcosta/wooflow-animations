# DW Animações para Elementor — índice para agentes

Plugin WordPress que dá ao Elementor um painel de animações por elemento (38
presets, gatilhos, cascata, scroll travado) e as ações de copiar/colar animação
que originaram o projeto.

- **Versão:** `DWANIM_VER` em [dw-copiar-animacao-elementor.php](dw-copiar-animacao-elementor.php) — o cabeçalho `Version:` **precisa** bater com a constante
- **Slug e text domain:** a pasta é `dw-copiar-animacao-elementor` e o domínio é `dw-copiar-animacao`. **Não renomear**: o plugin nasceu como utilitário de copiar/colar e o repositório já está publicado nesse nome. Só o `Plugin Name:` mudou
- **Ambiente de teste:** Local by Flywheel, site `woo.local`, com Elementor 4.3.4, Elementor Pro 4.2.3 e o `plugin-check` já instalados. Instalado por symlink em `wp-content/plugins/`
- **Idioma:** produto e documentação em pt-BR
- **Distribuição:** GitHub. **Não** vai para o repositório oficial do WordPress, porque o GSAP é empacotado e a licença dele não é compatível com GPL

---

## 1. Nunca leia, edite nem indexe

| Caminho | Por quê |
|---|---|
| `assets/lib/` | Bibliotecas de terceiros (GSAP, ScrollTrigger, Anime.js, Lenis, lottie-web). Reconstruídas por `npm run vendor`; nunca editadas à mão, nunca versionadas. |
| `node_modules/`, `release/`, `*.zip` | Dependências e artefatos regeneráveis. |

---

## 2. Regras invioláveis

Cada linha corresponde a uma armadilha concreta, verificada na instalação real
do Elementor 4.3.4.

1. **As chaves nativas de animação são assimétricas.** Widget usa `_animation`, `_animation_tablet`, `_animation_mobile`, `_animation_delay` — mas `animation_duration` **sem** underscore. Container, section e column usam todas sem underscore. Confirmado em `elementor/includes/widgets/common-base.php:838` e `elementor/includes/elements/container.php:1814`. [includes/class-keys.php](includes/class-keys.php) é o **único** lugar do plugin onde esses nomes podem aparecer.
2. **A seção a ancorar é `section_effects` (sem underscore inicial) e são cinco ganchos**, não quatro: `common`, **`common-optimized`**, `container`, `section`, `column`. `common-optimized` é o elemento usado quando o experimento de markup otimizado está ligado — esquecer dele faz os controles sumirem do painel sem erro nenhum. A lista vive em `DW_Anim_Controls::ELEMENT_TYPES` e o smoke test confere os cinco.
3. **Os nossos controles usam o prefixo `_dwanim_` em todo tipo de elemento.** Mapa único, de propósito, ao contrário das chaves nativas. Chave de dado nova **tem** de entrar em `DW_Anim_Keys::OURS`, senão copiar/colar e a biblioteca de animações a ignoram em silêncio — o smoke test reprova quem esquecer.
4. **O atributo do front-end sai de `elementor/frontend/before_render` + `add_render_attribute( '_wrapper', … )`.** Funciona para widget, container, section e column. Nunca filtrar `the_content`.
5. **Nenhuma biblioteca é enfileirada fora de [includes/class-assets.php](includes/class-assets.php).** O motor é decidido no servidor, por preset, e só o que a página usa entra no rodapé. Um `wp_enqueue_script( 'dw-anim-gsap' )` solto em qualquer outro arquivo põe 115 KB em toda página do site.
6. **O Elementor Pro já registra o lottie-web sob o handle `lottie`.** `DW_Anim_Lottie::handle()` resolve isso na hora do enqueue (o Pro registra depois de nós). Registrar a nossa cópia por cima são ~164 KB duplicados.
7. **Elemento atômico do Elementor 4 fica de fora.** Com o experimento `e_atomic_elements` ligado, esses elementos têm o módulo `interactions` nativo, e os dois sistemas brigam. O reconhecimento é por `method_exists( $element, 'get_props_schema' )`, em `DW_Anim_Controls::is_atomic()` e `DW_Anim_Render::is_atomic()`.
8. **`prefers-reduced-motion` e o tempo de segurança de 3 s são obrigatórios.** Animação que não dispara **não pode** deixar conteúdo invisível. São três redes: o `<script>` inline do `<head>` (`DW_Anim_Assets::print_prehide()`), o `<noscript>`, e a checagem no `core.js` antes de qualquer biblioteca carregar. Não mexer numa sem entender as outras duas.
9. **`assets/lib/` é terceiro.** Só `npm run vendor`, com as versões fixadas no campo `dwVendor` do [package.json](package.json).
10. **Colar em vários elementos é UMA chamada por tipo, dentro de um log de histórico.** `$e.run( 'document/elements/settings', { containers: […] } )` com o array, nunca um `$e.run` por elemento: N chamadas viram N entradas de histórico e o Ctrl+Z desfaz a colagem aos pedaços. Widget e bloco vão em grupos separados (regra 1) mas no mesmo `document/history/start-log`.
11. **`update_option( $chave, false )` não persiste** quando a option não existe. Toggles gravam `'1'`/`'0'`, e a leitura passa por `DW_Anim_Settings::is_on()`, que aceita as duas formas.
12. **Checkbox desmarcado some do POST.** O `sanitize()` dos ajustes itera a lista de toggles e grava `'0'` para o que não veio — nunca confia em `! empty( $input['x'] )` sobre o array recebido.
13. **Preset novo precisa de card com preview.** O card entra sozinho na grade, mas sem `@keyframes` e sem a regra `.dw-anim-card:hover .dw-pv-<id>` em [assets/css/editor.css](assets/css/editor.css) ele fica parado no hover — que é justamente o motivo de o painel existir. O smoke test confere a cobertura.
14. **Chave nova no payload tem de ser lida no `core.js`.** O contrato `data-dw-anim` é conferido nos dois sentidos pelo smoke test: chave enviada e não lida é configuração que não faz nada; chave lida e não enviada é `undefined` no motor.

---

## 3. Arquitetura

```
dw-copiar-animacao-elementor.php   bootstrap: cabeçalho, constantes, requisitos, boot
includes/
  class-plugin.php          singleton; carrega e liga os módulos
  class-requirements.php    PHP/WP/Elementor; admin notice, nunca fatal
  class-keys.php            REGRA 1 e 3: dono único dos nomes de chave
  class-presets.php         catálogo (38), grupos, gatilhos, curvas; filtro dw_anim_presets
  class-controls.php        REGRA 2: injeta a seção nos 5 tipos de elemento
  class-control-picker.php  controle `dw-anim-picker` (template da grade)
  class-editor.php          assets do editor + wp_localize_script
  class-render.php          REGRA 4: escreve data-dw-anim; resolve motor e gatilho
  class-assets.php          REGRA 5 e 8: registry, carregamento sob demanda, pré-esconde
  class-lenis.php           scroll suave global
  class-lottie.php          REGRA 6: handle do lottie-web + guarda de URL
  class-settings.php        Elementor → DW Animações
  class-library.php         "minhas animações" (option + ajax)
assets/js/editor/      copy-paste.js (REGRA 10) · control-picker.js · panel.js
assets/js/frontend/    core.js (orquestrador) · engine-{css,gsap,anime,lottie}.js · lenis-boot.js
assets/css/            editor.css (REGRA 13) · frontend.css
tests/                 smoke.php + stubs.php
```

### Quem decide o quê

O **servidor** decide o motor (`DW_Anim_Render::resolve_engine()`), o gatilho
válido para o preset (`resolve_trigger()`) e se o elemento começa escondido
(`hides_element()`). O **navegador** só obedece. Essa divisão é o que permite a
regra 5: se o motor fosse escolhido no cliente, toda página precisaria carregar
todas as bibliotecas por precaução.

O **`core.js`** é dono dos gatilhos (IntersectionObserver, hover, clique, load),
da quebra de texto e da ordem da cascata. Os motores não conhecem scroll: recebem
`play( el, spec, targets )`. A exceção é o gatilho travado no scroll, em que o
motor implementa `bind()` e assume a matemática — é o caso do GSAP, porque quem
faz essa conta é o ScrollTrigger.

---

## 4. Como testar

```bash
php tests/smoke.php          # 83 verificações, sem WordPress e sem banco
php -l <arquivo>             # lint de qualquer PHP alterado
node --check <arquivo>       # lint de qualquer JS alterado
npm run vendor               # reconstrói assets/lib/
```

O smoke test ([tests/smoke.php](tests/smoke.php)) sobe o plugin inteiro contra
dublês do WordPress e do Elementor ([tests/stubs.php](tests/stubs.php)), injeta
controles num elemento falso, renderiza vários presets e confere as regras 1-7,
11, 12 e 14. **Rodar antes de qualquer commit**: ele pega exatamente a classe de
erro que só apareceria dentro do editor.

O que o smoke test **não** cobre e exige o `woo.local` no ar: a aparência da
grade no painel, o ▶ Testar, os atalhos de teclado, o Lenis junto do ScrollTrigger
e qualquer coisa que dependa de layout real (a quebra de linhas do texto mede
`offsetTop`).
