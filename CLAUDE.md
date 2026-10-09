# WooFlow Animations for Elementor — índice para agentes

Plugin WordPress que dá ao Elementor um painel de animações por elemento (39
presets, gatilhos, cascata, scroll travado) e as ações de copiar/colar animação
que originaram o projeto.

- **Versão:** `WFAN_VER` em [wooflow-animations.php](wooflow-animations.php) — o cabeçalho `Version:` **precisa** bater com a constante
- **Slug e text domain:** ambos `wooflow-animations`, como o resto da família (`wooflow-admin`, `wooflow-delivery`, `wooflow-pdv`)
- **Prefixo:** `WFAN_` em classes e constantes, `wfan_` em funções, filtros, options e chaves de controle, `wfan-` em handles e classes CSS. **Nunca `WFA_`**: `WFA_Settings` e `WFA_Render` já existem no `wooflow-admin`, e os dois plugins ativos juntos dariam fatal error. O precedente da família para prefixo próprio é o `WFUPV_` do `wooflow-update-product-view`
- **Renomeação:** até a 2.0.0 o plugin era *DW Copiar Animação Elementor*, na pasta `dw-copiar-animacao-elementor`, com prefixos `DW_Anim_`/`DWANIM_`/`dw-anim-` e chaves de controle `_dwanim_`. A 2.1.0 renomeou tudo. Se aparecer qualquer `dw`/`DW` no código, é sobra de migração — a 2.1.0 só deixou um resquício legítimo: a leitura da chave antiga do `localStorage` (`dwElementorCopiedAnimation`), em [assets/js/editor/copy-paste.js](assets/js/editor/copy-paste.js)
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
2. **A seção entra na PRIMEIRA aba, e são duas estratégias diferentes — por um motivo medido.** Os controles de `common` são anexados ao **fim** do stack de cada widget (`Widget_Base::get_stack()`), então seção registrada lá nunca alcança o topo da primeira aba. Registrar em cada widget resolveria a posição, mas esta instalação tem **367 tipos de widget**: 5,8 KB de controles × 367 = **2 MB a mais na configuração do editor**. Então:
   - **bloco** (`container`, `section`, `column`): gancho `before_section_start` da **primeira seção nativa** de cada um (`section_layout_container`, `section_layout`, `layout` — ids confirmados no 4.3.4), com `TAB_LAYOUT`. Cai no topo da aba Layout;
   - **widget**: uma vez só, em `common` e `common-optimized`, no `section_effects/after_section_end`, com `TAB_CONTENT`. Cai na aba Conteúdo, abaixo dos controles do widget.

   Os dois stacks de widget são necessários **e** precisam do guard: com `e_optimized_markup` ligado — que é o caso neste site — o stack `common-optimized` dispara **também** os ganchos de `common` (`Controls_Stack::should_manually_trigger_common_action()`), e sem o `self::$done` os 27 controles seriam registrados em dobro. O guard é por **nome de stack**, nunca por `spl_object_id()`: o PHP recicla id de objeto liberado e um id reaproveitado faria a seção sumir inteira, sem erro.
3. **Os nossos controles usam o prefixo `_wfan_` em todo tipo de elemento.** Mapa único, de propósito, ao contrário das chaves nativas. Chave de dado nova **tem** de entrar em `WFAN_Keys::OURS`, senão copiar/colar e a biblioteca de animações a ignoram em silêncio — o smoke test reprova quem esquecer.
4. **O atributo do front-end sai de `elementor/frontend/before_render` + `add_render_attribute( '_wrapper', … )`.** Funciona para widget, container, section e column. Nunca filtrar `the_content`.
5. **Nenhuma biblioteca é enfileirada fora de [includes/class-assets.php](includes/class-assets.php).** O motor é decidido no servidor, por preset, e só o que a página usa entra no rodapé. Um `wp_enqueue_script( 'wfan-gsap' )` solto em qualquer outro arquivo põe 115 KB em toda página do site.
6. **O Elementor Pro já registra o lottie-web sob o handle `lottie`.** `WFAN_Lottie::handle()` resolve isso na hora do enqueue (o Pro registra depois de nós). Registrar a nossa cópia por cima são ~164 KB duplicados.
7. **Elemento atômico do Elementor 4 fica de fora.** Com o experimento `e_atomic_elements` ligado, esses elementos têm o módulo `interactions` nativo, e os dois sistemas brigam. O reconhecimento é por `method_exists( $element, 'get_props_schema' )`, em `WFAN_Controls::is_atomic()` e `WFAN_Render::is_atomic()`.
8. **`prefers-reduced-motion` e o tempo de segurança de 3 s são obrigatórios.** Animação que não dispara **não pode** deixar conteúdo invisível. São três redes: o `<script>` inline do `<head>` (`WFAN_Assets::print_prehide()`), o `<noscript>`, e a checagem no `core.js` antes de qualquer biblioteca carregar. Não mexer numa sem entender as outras duas.
9. **`assets/lib/` é terceiro.** Só `npm run vendor`, com as versões fixadas no campo `wfanVendor` do [package.json](package.json).
10. **Colar em vários elementos é UMA chamada por tipo, dentro de um log de histórico.** `$e.run( 'document/elements/settings', { containers: […] } )` com o array, nunca um `$e.run` por elemento: N chamadas viram N entradas de histórico e o Ctrl+Z desfaz a colagem aos pedaços. Widget e bloco vão em grupos separados (regra 1) mas no mesmo `document/history/start-log`.
11. **`update_option( $chave, false )` não persiste** quando a option não existe. Toggles gravam `'1'`/`'0'`, e a leitura passa por `WFAN_Settings::is_on()`, que aceita as duas formas.
12. **Checkbox desmarcado some do POST.** O `sanitize()` dos ajustes itera a lista de toggles e grava `'0'` para o que não veio — nunca confia em `! empty( $input['x'] )` sobre o array recebido.
13. **Não confie no campo `tab` fora do editor.** `get_controls()`, `get_widget_types_config()` e `get_element_types_config()` chamados numa requisição de CLI devolvem `tab => 'content'` para **todas** as seções — inclusive as nativas que o Elementor declara como `TAB_ADVANCED`. Conferir a aba por aí dá falso negativo garantido. A única fonte confiável é a configuração que o editor recebe: autenticar, buscar `wp-admin/post.php?post=<id>&action=elementor` e ler o JSON (`"_wfan_section":{…"tab":"layout"…}`). Foi assim que a mudança da regra 2 foi verificada.
14. **Preset novo precisa de card com preview.** O card entra sozinho na grade, mas sem `@keyframes` e sem a regra `.wfan-card:hover .wfan-pv-<id>` em [assets/css/editor.css](assets/css/editor.css) ele fica parado no hover — que é justamente o motivo de o painel existir. O smoke test confere a cobertura.
15. **Chave nova no payload tem de ser lida no `core.js`.** O contrato `data-wfan` é conferido nos dois sentidos pelo smoke test: chave enviada e não lida é configuração que não faz nada; chave lida e não enviada é `undefined` no motor.
16. **A tela de ajustes segue o padrão de UI da linha WooFlow, e tem seis armadilhas próprias.** Todo o CSS vive em [assets/css/admin.css](assets/css/admin.css) — nada de `<style>` ou `style=""` nos templates — e nenhum valor de cor fica fora dos tokens `--wf-*`. O smoke test confere isso, mais o prefixo único `.wooflow-` e a existência de regra para cada classe usada. As seis:
    - **O menu-pai é resolvido em runtime**, não fixo: `WFAN_Settings::parent_slug()` devolve o hub da família (`wooflow`, ou o legado `wooflow-checkout`) quando ele existe, e cai no `elementor` quando o plugin está sozinho. Funciona na prioridade padrão do `admin_menu` porque o hub registra o pai na 5; o Elementor registra o dele só na 20, mas `add_submenu_page()` acumula em `$submenu` e o pai aparece depois;
    - **a capability é `manage_options`, nunca `manage_woocommerce`.** O plugin não depende do WooCommerce, e num site sem Woo ninguém tem essa capability — a tela ficaria inacessível. O smoke test reprova quem trocar;
    - **o CSS de admin não pode depender de `woocommerce_admin_styles`.** `wp_enqueue_style()` com dependência inexistente é descartado em silêncio, e a tela sairia sem estilo nenhum em site sem Woo;
    - **a supressão de notices de terceiros precisa do `:not(.settings-error)`.** A regra que esconde `#wpbody-content > .notice` tem especificidade maior que um resgate por id, então sem o `:not()` o próprio aviso de "Ajustes salvos." do `settings_errors()` desaparece — mesmo com `!important` dos dois lados;
    - **o reset de fonte do painel não pode alcançar os Dashicons.** `.wooflow-admin *` define `font-family` com a mesma especificidade de `.dashicons` do core e vem depois, então sem o resgate `.wooflow-admin .dashicons{font-family:dashicons}` todo ícone vira o caractere cru da área de uso privado. O smoke test confere o resgate;
    - **cada aba é um `<form>` e grava só os toggles dela.** `sanitize()` parte dos valores já gravados e usa o campo oculto `_tab` para saber que recorte da regra 12 aplicar — sem isso, salvar uma aba desligaria os checkboxes de todas as outras, que nem chegam a ser enviados. `WFAN_Settings::tabs()` é o mapa único: dele saem a navegação, o nome do template (`templates/admin/tabs/tab-<chave>.php`) e esse recorte. Toggle que não estiver em nenhuma aba nunca é gravado pelo formulário — o smoke test reprova quem esquecer.
17. **Nenhuma configuração vai ao JS por `wp_localize_script()`.** Ele converte todo escalar do primeiro nível em string, e `"0"` é **verdadeiro** no JavaScript: `cfg.offMobile`, `cfg.debug` e `cfg.reduced` são testados por veracidade no [core.js](assets/js/frontend/core.js), então um toggle desligado chegava ligado. Custou a animação inteira em tela ≤ `mobile_bp` — medido em produção, zero animações num viewport de 390 px com "desligar no celular" desligado — e o log de depuração aceso em todo site. O caminho é `WFAN_Assets::localize()`, que imprime `wp_add_inline_script()` com `wp_json_encode()` e os `JSON_HEX_*` (rótulo vindo do banco não pode fechar o `<script>`). O smoke test reprova qualquer `wp_localize_script` em `includes/`, por tokens, e confere o tipo de cada bandeira do `wfanConfig`.

18. **Preset de estado (`css_only`) não carrega motor, e a cor dele sai dos `selectors` do controle.** `hover-invert` é o primeiro: inverter a cor no hover é um estado, não uma linha do tempo, e o `play()` dos motores não tem volta — o `mouseenter` do `core.js` dispara uma vez e acabou. Três consequências que não dá para separar:
    - a cor **tem** de sair de um seletor `{{WRAPPER}}:hover`, montado em `WFAN_Controls::HOVER_TARGETS`. O Elementor escreve a cor de cada widget em `.elementor-{post} .elementor-element.elementor-element-{id} …` (0,3,0); um CSS estático do plugin (`.wfan-hv:hover`, 0,2,0) perderia e exigiria `!important`. Com `{{WRAPPER}}:hover` a regra nasce com 0,4,0 para cima e ganha sozinha;
    - **declaração estática não entra junto da cor** no mesmo controle: com uma cor global escolhida, o Elementor troca tudo depois do primeiro `:` pelo valor global (`Base::add_control_rules()`), e um `transition:` vizinho viraria `transition:var(--e-global-color-x)`. Por isso a transição é escrita só pelo controle `hover_dur`, que tem `{{SIZE}}` e nunca é global;
    - **`:hover` não se simula**, então toda regra de cor sai nos dois estados: `{{WRAPPER}}:hover` **e** `{{WRAPPER}}.wfan-hv-on` (`WFAN_Controls::HOVER_PREVIEW_CLASS`). Sem esse par o ▶ Testar não tem o que ligar — ele mostra o toast "Reproduzindo no preview…" e não acontece nada, que foi exatamente o primeiro bug do preset. O `core.js` pendura a classe por `duração × 2 + 400 ms`, e o `d` do payload vem do `hover_dur` e não do controle genérico de duração, que neste preset está escondido e por isso chega como `null` (`get_active_settings()`). O smoke test confere o par de seletores e o nome da classe dos dois lados;
    - o render chama `require_style()` no lugar de `require_engine()` (nenhum byte de JS entra), manda `co:1` no payload para o `core.js` não amarrar gatilho nenhum, e os controles que só o JS obedece — duração, espera, curva, motor, "desligar no celular" — saem do painel por `'!' => array_merge( [''], WFAN_Presets::ids_css_only() )`.

19. **No canvas do editor o nosso `data-wfan` não existe — para preset nenhum.** O wrapper ali é construído pelo Backbone do Elementor: `BaseElementView.attributes()` devolve só `data-id`, `data-element_type` e `data-model-cid`, e `className()` monta as classes dele. Os atributos do `_wrapper` do PHP saem do `print_element()`, que roda no front-end e na carga inicial do preview; a re-renderização de um elemento no editor passa pelo ajax `render_widget` → `Document::render_element()` → `render_content()`, que **não** dispara `elementor/frontend/before_render`. Consequência medida: o ▶ Testar ficou desde sempre mostrando "Reproduzindo no preview…" sem reproduzir nada, porque o `setup()` do `core.js` não achava atributo para ler. O caminho é `WFAN_Render::spec()` — o mesmo que o `before_render()` usa — exposto ao painel pelo ajax `WFAN_Editor::PREVIEW_ACTION`; o `panel.js` entrega o contrato pronto em `wfanPlay( id, spec )` e só então mostra o toast. **Nunca** redecidir motor ou gatilho em JavaScript para resolver isto: é o servidor que decide (seção 3), e uma segunda implementação divergiria em silêncio.

---

## 3. Arquitetura

```
wooflow-animations.php   bootstrap: cabeçalho, constantes, requisitos, boot
includes/
  class-plugin.php          singleton; carrega e liga os módulos
  class-requirements.php    PHP/WP/Elementor; admin notice, nunca fatal
  class-keys.php            REGRA 1 e 3: dono único dos nomes de chave
  class-presets.php         catálogo (39), grupos, gatilhos, curvas, css_only; filtro wfan_presets
  class-controls.php        REGRA 2 e 18: injeta a seção na primeira aba dos 5 tipos
  class-control-picker.php  controle `wfan-picker` (template da grade)
  class-editor.php          assets do editor + config tipada (REGRA 17) + contrato do ▶ Testar (REGRA 19)
  class-render.php          REGRA 4 e 18: escreve data-wfan; resolve motor e gatilho
  class-assets.php          REGRA 5, 8, 17 e 18: registry, carregamento sob demanda, pré-esconde, config tipada
  class-lenis.php           scroll suave global
  class-blur.php            blur progressivo global; efeito de borda, não preset
  class-lottie.php          REGRA 6: handle do lottie-web + guarda de URL
  class-settings.php        REGRA 16: ajustes globais; menu-pai resolvido em runtime
  class-library.php         "minhas animações" (option + ajax)
templates/admin/       settings-page.php (casca: nav + form) + tabs/tab-<aba>.php (REGRA 16)
assets/js/editor/      copy-paste.js (REGRA 10) · control-picker.js · panel.js
assets/js/frontend/    core.js (orquestrador) · engine-{css,gsap,anime,lottie}.js · lenis-boot.js
assets/css/            editor.css (REGRA 13) · frontend.css · blur.css · admin.css (REGRA 16)
tests/                 smoke.php + stubs.php
```

### Quem decide o quê

O **servidor** decide o motor (`WFAN_Render::resolve_engine()`), o gatilho
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
php tests/smoke.php          # 166 verificações, sem WordPress e sem banco
php -l <arquivo>             # lint de qualquer PHP alterado
node --check <arquivo>       # lint de qualquer JS alterado
npm run vendor               # reconstrói assets/lib/
```

O smoke test ([tests/smoke.php](tests/smoke.php)) sobe o plugin inteiro contra
dublês do WordPress e do Elementor ([tests/stubs.php](tests/stubs.php)), injeta
controles num elemento falso, renderiza vários presets e confere as regras 1-7,
11, 12, 14, 15, 18 e 19. **Rodar antes de qualquer commit**: ele pega exatamente a classe de
erro que só apareceria dentro do editor.

O que o smoke test **não** cobre e exige o `woo.local` no ar: a aparência da
grade no painel, o ▶ Testar, os atalhos de teclado, o Lenis junto do ScrollTrigger
e qualquer coisa que dependa de layout real (a quebra de linhas do texto mede
`offsetTop`).
