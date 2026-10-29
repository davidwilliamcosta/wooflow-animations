# WooFlow Animations for Elementor — índice para agentes

Plugin WordPress que dá ao Elementor um painel de animações por elemento (82
presets — 46 nossos e 36 entregues à animação de entrada do próprio Elementor —,
gatilhos, cascata, scroll travado, fundo animado) e as ações de copiar/colar
animação que originaram o projeto.

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

   Os dois stacks de widget são necessários **e** precisam do guard: com `e_optimized_markup` ligado — que é o caso neste site — o stack `common-optimized` dispara **também** os ganchos de `common` (`Controls_Stack::should_manually_trigger_common_action()`), e sem o guard os 27 controles seriam registrados em dobro. O guard (`WFAN_Controls::is_injected()`) pergunta ao **stack que está sendo montado** se a nossa seção já está lá, por `Controls_Manager::get_control_from_stack()` — nunca uma lista nossa de stacks "já feitos", e nunca `spl_object_id()` (o PHP recicla id de objeto liberado). O motivo é medido: o Elementor **apaga e remonta** o stack de um tipo dentro da mesma requisição quando ele foi montado num `responsive_control_duplication_mode` mais baixo que o atual (`should_clean_stack()`), e a geração do CSS pede `on` enquanto o render do front-end monta em `off`. Com a lista por nome (`self::$done`, até a 2.7.0), um container renderizado antes — cabeçalho, item de loop — marcava o tipo, a remontagem saía sem a nossa seção e o `post-<id>.css` regenerado numa visita nascia **sem nenhuma regra nossa**: a inversão no hover e a duração das animações nativas sumiam do site publicado, enquanto o editor (que gera o CSS no navegador) continuava certo. Medido em produção, em `barbascommerce.com.br`: 15 elementos `hover-invert` e zero `wfan-hv-on` no `post-22.css`. Parece culpa de cache (WP Rocket, Redis) porque é a limpeza de cache que faz o arquivo ser regenerado numa visita. O smoke test simula a remontagem.
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
    - **A tela é sempre um submenu do menu WooFlow, e o pai é criado por quem chegar primeiro.** `WFAN_Settings::parent_slug()` devolve o hub da família (`wooflow`, ou o legado `wooflow-checkout`) quando ele já existe, e string vazia quando ninguém da linha subiu ainda — e aí o `menu()` **cria o pai `wooflow`**, com o mesmo ícone (`dashicons-store`) e a mesma posição (56) do [wooflow-update-product-view](../wooflow-update-product-view/includes/class-wfupv-admin.php), para o menu nascer igual não importa qual plugin chegou antes. Três detalhes que custaram:
        - **a prioridade do `admin_menu` é 20**, não a padrão. O hub registra o pai na 5, mas os satélites que criam o pai quando ele falta rodam na 20; registrando na 10 a checagem não via pai nenhum e o plugin ia parar num **menu só dele, do lado do WooFlow em vez de dentro dele** — foi assim que o defeito chegou, com o print do menu;
        - **o item-fantasma só sai quando o pai é nosso.** `add_menu_page()` cria um primeiro submenu com o rótulo do pai, que aqui aponta para `__return_null`; ele é removido com `remove_submenu_page( 'wooflow', 'wooflow' )` **apenas** se fomos nós que criamos o pai — com o hub ativo, esse primeiro item é o Painel dele e não pode sair;
        - **pendurar no menu do Elementor morreu no 4.x.** Era a saída até a 2.4.2: o menu unificado dele esconde o item de topo inteiro (`#toplevel_page_elementor{display:none!important}`, em `Elementor_One_Menu_Manager::hide_old_elementor_menu()`, no `admin_head`) e ainda tira de lá todo submenu de terceiro (`hide_flyout_items_from_wp_menu()` → `remove_submenu_page()`), adotando-o no painel próprio dele. A tela continuava existindo pela URL e sumia do menu da esquerda.

      O slug da tela é o mesmo em qualquer arranjo, então a URL nunca muda; o que muda é o hook (`wooflow_page_…`, `toplevel_page_…`), e por isso `enqueue()` e `is_our_screen()` casam por **substring do slug**, nunca por hook fixo — senão a tela sairia crua num dos caminhos. O smoke test cobre os dois arranjos, a prioridade, o item-fantasma e o CSS;
    - **a capability é `manage_options`, nunca `manage_woocommerce`.** O plugin não depende do WooCommerce, e num site sem Woo ninguém tem essa capability — a tela ficaria inacessível. O smoke test reprova quem trocar;
    - **o CSS de admin não pode depender de `woocommerce_admin_styles`.** `wp_enqueue_style()` com dependência inexistente é descartado em silêncio, e a tela sairia sem estilo nenhum em site sem Woo;
    - **a supressão de notices de terceiros precisa do `:not(.settings-error)`.** A regra que esconde `#wpbody-content > .notice` tem especificidade maior que um resgate por id, então sem o `:not()` o próprio aviso de "Ajustes salvos." do `settings_errors()` desaparece — mesmo com `!important` dos dois lados;
    - **o reset de fonte do painel não pode alcançar os Dashicons.** `.wooflow-admin *` define `font-family` com a mesma especificidade de `.dashicons` do core e vem depois, então sem o resgate `.wooflow-admin .dashicons{font-family:dashicons}` todo ícone vira o caractere cru da área de uso privado. O smoke test confere o resgate;
    - **cada aba é um `<form>` e grava só os toggles dela.** `sanitize()` parte dos valores já gravados e usa o campo oculto `_tab` para saber que recorte da regra 12 aplicar — sem isso, salvar uma aba desligaria os checkboxes de todas as outras, que nem chegam a ser enviados. `WFAN_Settings::tabs()` é o mapa único: dele saem a navegação, o nome do template (`templates/admin/tabs/tab-<chave>.php`) e esse recorte. Toggle que não estiver em nenhuma aba nunca é gravado pelo formulário — o smoke test reprova quem esquecer.
17. **Nenhuma configuração vai ao JS por `wp_localize_script()`.** Ele converte todo escalar do primeiro nível em string, e `"0"` é **verdadeiro** no JavaScript: `cfg.offMobile`, `cfg.debug` e `cfg.reduced` são testados por veracidade no [core.js](assets/js/frontend/core.js), então um toggle desligado chegava ligado. Custou a animação inteira em tela ≤ `mobile_bp` — medido em produção, zero animações num viewport de 390 px com "desligar no celular" desligado — e o log de depuração aceso em todo site. O caminho é `WFAN_Assets::localize()`, que imprime `wp_add_inline_script()` com `wp_json_encode()` e os `JSON_HEX_*` (rótulo vindo do banco não pode fechar o `<script>`). O smoke test reprova qualquer `wp_localize_script` em `includes/`, por tokens, e confere o tipo de cada bandeira do `wfanConfig`.

18. **Preset de estado (`css_only`) não carrega motor, e a cor dele sai dos `selectors` do controle.** `hover-invert` é o primeiro: inverter a cor no hover é um estado, não uma linha do tempo, e o `play()` dos motores não tem volta — o `mouseenter` do `core.js` dispara uma vez e acabou. Três consequências que não dá para separar:
    - a cor **tem** de sair de um seletor `{{WRAPPER}}:hover`, montado em `WFAN_Controls::HOVER_TARGETS`. O Elementor escreve a cor de cada widget em `.elementor-{post} .elementor-element.elementor-element-{id} …` (0,3,0); um CSS estático do plugin (`.wfan-hv:hover`, 0,2,0) perderia e exigiria `!important`;
    - **mas `{{WRAPPER}}:hover` sozinho não ganha: ele EMPATA, e empate é decidido pela ordem no arquivo — que muda de contexto.** No front-end o `Controls_Manager::add_control_to_stack()` desvia todo controle com `selectors` para um balde à parte (`Performance::should_optimize_controls()`) e o `get_controls()` o anexa no **fim** (`$controls += $stack['style_controls']`): as nossas regras saem por último e qualquer empate passa despercebido. No editor e na requisição que **salva** a página — que é quem gera o arquivo CSS que a página publicada carrega — não há desvio, e os controles saem na ordem da pilha, com a nossa seção no topo da primeira aba (regra 2): as nossas regras saem **primeiro** e perdem. Medido num container com cor de fundo: a nossa `{{WRAPPER}}:hover{background-color}` (0,4,0) perdia para `{{WRAPPER}}:not(.elementor-motion-effects-element-type-background)` (0,4,0), e o fundo não invertia em quadro nenhum — enquanto o título (0,5,0) invertia, dando branco sobre branco. Por isso **todo** ramo, inclusive o do estado normal onde mora a transição, nasce com os dois degraus de `WFAN_Controls::SPECIFICITY_BUMP` (`:nth-child(n)`, que casa com qualquer elemento e só pesa). Dois, e não um, porque um só empataria de novo com a cor de ícone do Elementor, `…-{id}.elementor-view-default .elementor-icon` (0,5,0). O smoke test pesa cada ramo contra essa regra, e não confere ordem nenhuma;
    - **declaração estática não entra junto da cor** no mesmo controle: com uma cor global escolhida, o Elementor troca tudo depois do primeiro `:` pelo valor global (`Base::add_control_rules()`), e um `transition:` vizinho viraria `transition:var(--e-global-color-x)`. Por isso a transição é escrita só pelo controle `hover_dur`, que tem `{{SIZE}}` e nunca é global;
    - **`:hover` não se simula**, então toda regra de cor sai nos dois estados: `{{WRAPPER}}:hover` **e** `{{WRAPPER}}.wfan-hv-on` (`WFAN_Controls::HOVER_PREVIEW_CLASS`). Sem esse par o ▶ Testar não tem o que ligar — ele mostra o toast "Reproduzindo no preview…" e não acontece nada, que foi exatamente o primeiro bug do preset. O `core.js` pendura a classe por `duração × 2 + 400 ms`, e o `d` do payload vem do `hover_dur` e não do controle genérico de duração, que neste preset está escondido e por isso chega como `null` (`get_active_settings()`). O smoke test confere o par de seletores e o nome da classe dos dois lados;
    - **ícone precisa de `fill`, e `svg *` além de `svg`.** Ícone de fonte obedece à cor herdada, mas SVG com `fill` no próprio arquivo ignora `color`, e um `fill` no `<svg>` não alcança um `<path fill="…">` — presentation attribute ganha da herança. Sem as duas coisas o ícone fica parado enquanto o resto do bloco inverte; o `transition-property` também precisa listar `fill`. O smoke test confere os três;
    - o render chama `require_style()` no lugar de `require_engine()` (nenhum byte de JS entra), manda `co:1` no payload para o `core.js` não amarrar gatilho nenhum, e os controles que só o JS obedece — duração, espera, curva, motor, "desligar no celular" — saem do painel por `'!' => array_merge( [''], WFAN_Presets::ids_css_only() )`.

19. **No canvas do editor o nosso `data-wfan` não existe — para preset nenhum.** O wrapper ali é construído pelo Backbone do Elementor: `BaseElementView.attributes()` devolve só `data-id`, `data-element_type` e `data-model-cid`, e `className()` monta as classes dele. Os atributos do `_wrapper` do PHP saem do `print_element()`, que roda no front-end e na carga inicial do preview; a re-renderização de um elemento no editor passa pelo ajax `render_widget` → `Document::render_element()` → `render_content()`, que **não** dispara `elementor/frontend/before_render`. Consequência medida: o ▶ Testar ficou desde sempre mostrando "Reproduzindo no preview…" sem reproduzir nada, porque o `setup()` do `core.js` não achava atributo para ler. O caminho é `WFAN_Render::spec()` — o mesmo que o `before_render()` usa — exposto ao painel pelo ajax `WFAN_Editor::PREVIEW_ACTION`; o `panel.js` entrega o contrato pronto em `wfanPlay( id, spec )` e só então mostra o toast. **Nunca** redecidir motor ou gatilho em JavaScript para resolver isto: é o servidor que decide (seção 3), e uma segunda implementação divergiria em silêncio.

20. **O fundo animado não é preset, e o contrato dele viaja em CSS — não em atributo.** O "Overlay Animated" ([includes/class-overlay.php](includes/class-overlay.php)) é um `<canvas>` de ruído simplex que roda sem parar atrás do conteúdo: não tem gatilho, não tem fim, não esconde nada e convive com uma animação de entrada no mesmo elemento. Por isso tem seção própria no painel (registrada no mesmo ponto de injeção da regra 2, sob o mesmo guard) e fica fora do catálogo. Quatro consequências:
    - **todo parâmetro sai nos `selectors` dos controles, em custom property.** É a única forma de o fundo sobreviver no editor: pela regra 19, o wrapper do canvas perde os atributos do PHP a cada re-render, mas o CSS que o Elementor gera por elemento sobrevive — e ainda é reescrito a cada mexida no controle, sem ida ao servidor. O `WFAN_Overlay::VARS` é o mapa único; o smoke test confere os dois sentidos, como na regra 15;
    - **as duas cores são lidas de `color` e `outline-color` do canvas, nunca da custom property.** `getPropertyValue('--wfan-ovl-c1')` devolve o texto como foi escrito — com uma cor global do Elementor, literalmente `var(--e-global-color-x)`. Aplicada a uma propriedade de cor de verdade, no [frontend.css](assets/css/frontend.css), o navegador resolve e o `getComputedStyle` devolve `rgb()`. Nenhuma das duas pinta coisa alguma num `<canvas>` sem borda nem texto. O smoke test reprova quem ler a cor direto da variável;
    - **vale a regra 18 inteira sobre declaração vizinha**: uma declaração por controle, com o nome da propriedade antes do primeiro `:`, senão a cor global leva a vizinha junto;
    - **o canvas sobe com `z-index:-1` dentro de um `isolation:isolate` inline**, posto pelo [overlay.js](assets/js/frontend/overlay.js) — acima do fundo do elemento, abaixo de todo o conteúdo. Inline, e não pela classe `.wfan-ovl`, porque a classe também se perde no re-render do editor; a classe serve só de seletor rápido no front-end, e quem decide se o fundo existe é sempre a custom property. O `isolation` cria um contexto de empilhamento no elemento, então um filho que precise escapar por `z-index` — submenu, tooltip — fica preso dentro dele: é o preço do `z-index:-1`, e vale só para quem ligou o fundo.

21. **A animação de entrada do Elementor é um grupo do catálogo, não uma conversão dos nossos presets.** Os 36 presets `nat-*` ([class-presets.php](includes/class-presets.php), `native_catalog()`) não têm motor: o render preenche `_animation` (widget) ou `animation` (bloco) e sai de cena — zero bytes de JavaScript nosso na página, e quem esconde, observa a tela e anima é o `GlobalHandler` do Elementor. O que **não** dá para fazer é converter `fade-up` em `fadeInUp` automaticamente: os keyframes do Elementor têm o deslocamento cravado (`translate3d(0,100%,0)`, escala 0.3, −200°) e os controles de distância, escala e rotação sumiriam de vista obedecendo a outro número. Seis consequências:
    - **a chave nativa é escrita ANTES de qualquer `get_settings_for_display()`.** O Elementor guarda o resultado em `parsed_active_settings` na primeira chamada, e é desse cache que o `add_render_attributes()` lê `_animation` para pôr a classe `elementor-invisible` e montar o `data-settings`. Escrever depois não chega a lugar nenhum — por isso `WFAN_Render::apply_native()` lê os ajustes crus (`get_settings()`) e roda antes de tudo no `before_render()`;
    - **preset nativo não escreve `data-wfan` e não pede motor.** Só a classe `wfan wfan-native` e a folha `e-animation-<nome>`, que o Elementor registra e ninguém enfileira sozinho: a varredura que ele faz dos dados salvos não vê a nossa chave, porque a animação nativa só existe em tempo de render. Quem enfileira é `WFAN_Assets::require_native()`;
    - **duração e curva exatas saem dos `selectors`**, presas a `{{WRAPPER}}.wfan-native.animated` (0,5,0, ganha de `.animated{animation-duration:1.25s}` sem `!important`). A classe no seletor não é enfeite: sem ela a regra vazaria para a animação de entrada que o usuário tenha escolhido à mão no mesmo elemento. A curva precisa de `selectors_dictionary` — o valor gravado é o nome GSAP, que não diz nada ao CSS. A espera vai pela chave nativa (`_animation_delay`, em ms), e "desligar no celular" vira `_animation_mobile = 'none'`, com o corte no breakpoint do Elementor, não no `mobile_bp` dos nossos ajustes;
    - **ponto de disparo e "animar só uma vez" saem do painel** (`condition` com `preset!` listando `WFAN_Presets::ids_native()`): o nativo dispara ao encostar na tela e toca uma vez só, e controle que não é obedecido é configuração que não faz nada. O gatilho é `['scroll-in']` no próprio preset;
    - **o resgate de 3 s não vale para `elementor-invisible`** — é o Elementor quem revela, e um elemento abaixo da dobra está legitimamente escondido aos 3 s. O `print_prehide()` só tira a classe de `.wfan-native` **se o `elementorFrontend` não tiver subido**, que é o caso em que ninguém mais vai revelar;
    - **no canvas do editor nada disso existe** (regra 19): o `playNative()` do [core.js](assets/js/frontend/core.js) refaz à mão o que o `GlobalHandler` faz, e os `@keyframes` vêm da folha única do Elementor, enfileirada por `WFAN_Assets::enqueue_native_keyframes()` no painel (para o card se mexer, regra 14) e no preview (para o ▶ Testar ter o que tocar).
22. **Todo controle nosso declara `render_type`, e é isso que separa um painel que responde de um que trava.** Controle sem `render_type` que não seja de estilo faz o Elementor marcar `isContentChanged` e **re-renderizar o widget inteiro por Ajax a cada mexida** (`BaseElementView.renderChanges()`): arrastar o slider de duração disparava uma requisição por parada do mouse, e o canvas piscava por cima do preview recém-tocado. Nenhum controle nosso muda o HTML do elemento — o contrato é escrito no servidor e, no canvas, o Elementor nem o lê. Então `WFAN_Controls::add()` e `WFAN_Overlay::add()` decidem por todo mundo: `ui` quando há `selectors` (só o CSS do elemento é reescrito, que é o que o preset de estado e o fundo animado precisam) e `none` quando não há. O smoke test reprova quem registrar um controle fora desses dois helpers. É o mesmo que o Elementor faz com os controles de animação dele (`Control_Animation::get_default_settings()`).
23. **O painel mostra o resultado sozinho, e a lista de gatilhos é reescrita — nunca escondida.** Duas coisas que o ▶ Testar sozinho não resolvia:
    - **mexeu num controle nosso, o preview toca** (`panel.js`, `schedulePreview()`, 250 ms depois da última mexida), como na animação de entrada nativa. O contrato continua vindo do servidor (regra 19), mas fica guardado por assinatura dos ajustes: quando o usuário clica em ▶ Testar, a resposta daquela configuração já chegou junto com o preview automático. O botão continua lá para repetir, e é o único que fala por toast — preview automático que avisa a cada mexida enche a tela. O fundo animado não passa pelo servidor: `wfanOverlay.syncId()` monta o canvas na hora, porque todo parâmetro dele já está no CSS que o Elementor acabou de reescrever (regra 20);
    - **a lista de gatilhos é reconstruída inteira a cada passada**, nunca marcada com `hidden`. Opção escondida deixa estado no DOM, e o DOM do painel sobrevive à troca de elemento: era isso que fazia opções sumirem do "Gatilho" até recarregar a página do editor. A reescrita é idempotente (marcada no próprio `<select>`, em `data-wfan-triggers`) e o preset é lido do modelo do container, não do DOM. Ela roda no `change` do painel, no `section:activated` e nos quatro `panel/open_editor/*`.

24. **Asset pedido durante o render é enfileirado NA HORA, não no `wp_footer`.** O Elementor guarda o HTML do documento em meta do post (`Document::print_elements()` → `set_document_cache()`, TTL em `elementor_element_cache_ttl`, **ligado por padrão**) e, junto com ele, **a lista de handles que entraram em `$wp_scripts->queue`/`$wp_styles->queue` enquanto aquele HTML era montado** — o diff tirado antes e depois do `do_print_elements()`. No cache hit ele devolve o HTML pronto, já com o nosso `data-wfan` dentro, reenfileira só aqueles handles e **não dispara `elementor/frontend/before_render` para elemento nenhum**. Enfileirando no `wp_footer` (que é depois do diff), o nosso handle nunca era gravado: a página saía com os atributos e sem o `core.js`, e o conteúdo escondido só aparecia pelo tempo de segurança de 3 s. Medido em produção, em `/barbas-manager/`: 22 elementos com `data-wfan`, zero scripts nossos — e o primeiro render depois de salvar a página funcionava, o que fazia o defeito parecer intermitente ("às vezes anima"). Por isso todo `require_*` do [class-assets.php](includes/class-assets.php) termina em `flush()`, que enfileira assim que o registry existe (`did_action( 'wp_enqueue_scripts' )`); o gancho de `wp_footer` fica só como rede. Enfileirar cedo não antecipa nada: os scripts são registrados com `$in_footer = true` e folha tardia sai pelo `print_late_styles()`. O smoke test reprova quem voltar a adiar. **Efeito global é outro caso**: o
que é decidido por option, e não por ajuste de elemento — o Lenis, o blur, o
botão de voltar ao topo —, enfileira no `wp_enqueue_scripts`, que roda em toda
requisição e antes de o Elementor tirar o retrato da fila; aí não há nada a
recuperar do cache, e usar um `require_*()` só arrastaria o `wfan-frontend`
junto. Vale para qualquer asset novo — e vale também para o widget dinâmico, que no cache hit é reexpandido pelo shortcode `[elementor-element]` e **esse** continua disparando o `before_render` (foi por isso que a home, cheia de loop, animava enquanto a página estática não).

25. **O gatilho de tela reage à MUDANÇA de estado, nunca ao cruzamento do limiar — porque a animação mexe o elemento que está sendo observado.** O `IntersectionObserver` do `observe()` ([core.js](assets/js/frontend/core.js)) é notificado a cada travessia dos limiares `[0, vp, 1]`, e um zoom de 0.85 ou um deslize de 40 px **mudam a caixa do elemento**: a razão de visibilidade oscila em volta do `vp`, cada oscilação reproduzia de novo e a animação se realimentava. Com "Animar só uma vez" desligado o efeito repetia sem parar, **com a página até parada**; com scroll suave (Lenis) é pior, porque o scroll chega em frações de pixel. Medido em produção, em `/barbas-strategy/`: 20 reproduções onde cabiam 2. Por isso a entrada guarda `inside` e só age na transição (fora→dentro, ou dentro→fora no `scroll-out`), e `hold()` segura novos disparos por `espera + duração + cascata + 120 ms` — uma saída e um retorno dentro dessa janela são efeito da própria animação, não do usuário. "Animar só uma vez" desligado quer dizer **toca de novo quando o elemento voltar à tela**, não "toca enquanto estiver visível". Quem conta as reproduções é [tests/trigger.js](tests/trigger.js), que dirige o observador num DOM de mentira — o smoke test só confere que o guarda continua lá, e o `build.sh` não empacota sem rodar os dois.

26. **A linha do texto é medida em DUAS passadas, e refeita quando a largura muda — e o caractere vive dentro de um invólucro `nowrap`.** Quatro armadilhas da quebra de texto, todas em [core.js](assets/js/frontend/core.js), e todas vindas dos efeitos de *text reveal* que rodavam colados no Custom Code:
    - **medir enquanto move dá agrupamento errado.** O invólucro de linha é `display:block`, então fechar a primeira linha reflui todas as palavras seguintes: o `offsetTop` lido na palavra 12 já não é o de antes da mudança. Por isso `groupLines()` tira **todas** as medidas primeiro (`words.map`) e só então constrói as linhas;
    - **a palavra sempre ganha invólucro, mesmo quando não é o alvo.** `splitText()` pica o texto em palavras antes de picar em letras: o `.wfan-word` é o que o agrupamento por linha mede **e** o `white-space:nowrap` que segura as letras juntas. Sem ele, cada letra é uma caixa `inline-block` independente e o navegador quebra a linha no meio da palavra;
    - **a linha agrupada envelhece.** Girar o celular, redimensionar a janela ou a webfont chegar mudam onde a linha quebra — e a webfont chega depois da primeira medida com frequência, porque o texto é pintado antes com a fonte de reserva. O `refreshLines()` roda no `resize` (com freio de 150 ms e só se a largura mexeu mais de 2 px) e uma vez no `document.fonts.ready`. Ele chama `regroupLines()`, que **nunca repica o texto**: desfaz os invólucros de linha e reagrupa a partir de `el.__wfanWords`, porque repicar aninharia span dentro de span. E atualiza `entry.targets`, senão a próxima reprodução animaria linhas que já saíram do documento;
    - o `overflow:hidden` da cortina também come o rabo do g, do j e do p. A folga sai de `.wfan-line--mask` ([frontend.css](assets/css/frontend.css)): `padding` para abrir espaço e `margin` negativa para devolver a medida, senão a linha empurra as vizinhas.

27. **A cor da barra fixa sai do ELEMENTO, nunca de um ajuste nosso — e a classe de leitura só pode sair do caminho.** O [class-header.php](includes/class-header.php) é o quarto efeito global (option, `wp_enqueue_scripts`, fora do catálogo, sem `core.js`), e a aba dele não tem campo de cor de propósito: quem escolhe é o Elementor, em *Estilo > Fundo*, e o script só varia a transparência daquele RGB. Quatro consequências:
    - **a medição precisa da nossa regra fora do caminho.** Com o fundo que o script controla já aplicado, `getComputedStyle` devolveria o transparente que nós mesmos acabamos de escrever, e a barra nunca ganharia cor. Daí o `wfan-header--off`, que **não pinta nada**: o CSS sai do caminho por `:not(.wfan-header--off)`. Escrever um fundo ali — mesmo `initial` — reintroduz o defeito, e o smoke test reprova;
    - **cor que o script não entende faz ele desistir.** Um degradê não casa com `rgb(…)`: aí nenhuma variável é escrita, o `is-ready` não entra e o fundo original fica de pé. Barra sem efeito é melhor que barra invisível;
    - **o laço de amortecimento tem de se apagar.** O passo é exponencial pelo tempo decorrido (`1 - exp(-dt/smooth)`, `dt` limitado a 64 ms para a aba voltar do segundo plano sem salto) e `running` vira `false` quando a diferença cai abaixo de 0,001. Um `requestAnimationFrame` eterno em toda página do site não aparece na tela: aparece na bateria. Com `prefers-reduced-motion` não há laço nenhum — vai direto ao alvo;
    - a transição do Elementor é desligada por `!important` na barra: os dois suavizariam a mesma mudança de cor, um por cima do outro. Quem confere tudo isto é [tests/header.js](tests/header.js).

28. **O popup circular pende de um evento jQuery do Elementor Pro, e a origem do círculo é o ÚLTIMO CLIQUE — não um ajuste.** O [class-popup.php](includes/class-popup.php) faz o popup nascer de um círculo que cresce do ponto em que foi aberto, com a sobreposição escurecendo e desfocando o site atrás. Cinco consequências:
    - **`elementor/popup/show` e `elementor/popup/hide` são disparados com `jQuery.trigger()`** e não existem como evento de DOM: o handle declara `[ 'jquery' ]` por necessidade, não por gosto, e `available()` exige `ELEMENTOR_PRO_VERSION`. Sem o Pro não há popup na página e nada é carregado;
    - **a origem não se configura.** O clique é lido na **fase de captura** em `document` — na borbulha o ponto chegaria depois de o Elementor já ter aberto o popup — e a coordenada já está dentro do ícone, o que dispensa a classe de gatilho do efeito original. Clique de teclado chega com `clientX`/`clientY` zerados: não é um ponto na tela, e usá-lo abriria o círculo no canto, então nesse caso a reserva é o canto superior direito;
    - **a medida espera DOIS `requestAnimationFrame`.** Na primeira abertura o Elementor ainda está posicionando o popup quando o evento chega, e a caixa medida no quadro seguinte ainda é a do layout em construção: o círculo nascia fora do ícone só na primeira vez. E a transição é desligada enquanto a origem é escrita, senão o círculo *viaja* da posição padrão até o ícone durante a abertura, em vez de já nascer nele;
    - **o Elementor não avisa que vai fechar**: ele põe a classe da animação de saída na caixa. Um `MutationObserver` nessa classe é o único jeito de reverter o círculo junto, em vez de o popup sumir com ele ainda aberto;
    - **todo parâmetro viaja em custom property**, como no fundo animado (regra 20) — o Elementor reescreve o que quiser em volta e a variável sobrevive. A cor sai dos ajustes em hex e é montada em `rgba()` no PHP com a opacidade de cada ponta do degradê, porque o campo de cor da tela é `sanitize_hex_color`. O recorte de celular zera só o desfoque, que é a parte cara: o `backdrop-filter` recalcula a página inteira a cada quadro da abertura.

29. **A revelação pedaço por pedaço no scroll tem percurso próprio: as duas pontas na MESMA altura de tela.** Os `text-scrub-*` acendem o texto conforme a rolagem, como as referências do gênero, e isso só funciona se a frente de luz ficar **parada** na tela enquanto o texto passa por ela. Logo o par de âncoras do ScrollTrigger é `top <linha>` → `bottom <linha>`, montado em `lineAnchor()` ([engine-gsap.js](assets/js/frontend/engine-gsap.js)) a partir de um controle só, `scrub_line` — a altura da linha, medida do topo da tela, 80% de fábrica. O percurso passa a valer exatamente a altura do elemento. Seis consequências:
    - **o par genérico "começar/terminar quando" não serve, e por isso sai do painel** (`params` sem `scrub_end`): com o padrão dele — "o elemento entrar na tela" até "o topo chegar ao topo" — o texto terminava de acender enquanto o bloco **ainda subia**, antes de dar para ler. Foi assim que o defeito chegou. Controle que não é obedecido é configuração que não faz nada;
    - **início e fim iguais são percurso de comprimento zero.** "Chegar ao centro" e "o topo chegar ao topo" existem nas duas listas, e escolhidos juntos faziam o ScrollTrigger empurrar o fim um pixel adiante: a animação inteira acontecia nesse pixel. O `endFor()` compara as duas pontas e cai na saída do elemento quando elas batem;
    - **o estado de partida de TODOS os pedaços é fixado com `gsap.set()` antes do `fromTo`.** O `fromTo` com `stagger` só aplica o valor inicial de cada filho quando o início *dele* chega: quem vem depois da frente de luz fica no estado natural — aceso — e só cai para o apagado quando a frente o alcança. Medido num Chrome real, com a página parada antes do percurso: só a primeira palavra em 0.20, as outras 101 em 1.00. É o fim do texto aparecendo antes da hora, mais ainda numa descida rápida, em que a frente salta. O fake de GSAP do `tests/scrub.js` não reproduz isso, então ele confere a causa (o `set` com todos os alvos); a medida de verdade só sai de um navegador real;
    - **a velocidade escala o percurso, não o tempo.** O fim não é uma segunda âncora: é `+=` a altura do elemento vezes `65 / speed` (`lineSpan()`, função, porque a altura muda e o ScrollTrigger refaz a conta a cada refresh). Em 65 — a escala do campo equivalente do Uncode, que é o padrão de fábrica — o percurso vale a altura do elemento; mais rápido, menos rolagem. Altura ainda não medida cai num piso, senão o percurso zera e tudo acende de uma vez;
    - **a borda do degradê é fração do texto, não número de palavras.** Com a duração de cada pedaço igual ao passo da cascata, só um pedaço está em transição por vez e a frente vira um interruptor andando pelo texto; `SOFT_EDGE` mantém um quarto do texto em degradê, que ocupa a mesma parte da tela num título de seis palavras e num parágrafo de cem;
    - **a amostra do ▶ Testar parte da mesma opacidade do efeito** (`fromVars()`), não de zero: o preset acende o que já está lá, apagado, e um texto nascendo do nada mostraria outro efeito. Quem confere o percurso é [tests/scrub.js](tests/scrub.js), que lê o que o motor entrega ao ScrollTrigger com um GSAP de mentira; o smoke test confere o controle, o sumiço do par genérico e que o `build.sh` não empacota sem rodar o teste.

30. **O "Virar cartão no scroll" (`scrub-card`) é a animação de linha do Uncode com estado "End", e o raio vai em DOIS lugares.** A seção encolhe em direção à base e arredonda os cantos enquanto sai da tela (ou o caminho inverso, ao entrar). Quatro consequências:
    - **percurso próprio, medido na TELA**: ao sair, `top top` até `+=N%`; ao entrar, `top bottom` até `+=N%` (`cardTrigger()`, [engine-gsap.js](assets/js/frontend/engine-gsap.js)). Por isso o param é `card`, e não `scrub_end`: o par genérico não tem "o topo encostou no topo" como padrão, e terminar numa âncora do elemento esticaria o efeito por várias telas numa seção alta;
    - **o raio é escrito em `border-radius` e em `--border-radius`.** O container do Elementor 4.3.4 pinta o próprio fundo com `border-radius:var(--border-radius)`, mas a sobreposição (`::before`), o vídeo e o slideshow de fundo são outros elementos que só leem a variável. Sem ela, o fundo arredonda e a sobreposição fica quadrada por cima. Assim não precisa de `overflow:hidden`, que cortaria submenu e sombra;
    - **o ponto de partida é o raio que o elemento já tem** (`cornersOf()`, lido do `getComputedStyle`, um valor por canto): começar de zero tirava o raio de um container arredondado no primeiro pixel de rolagem;
    - **o reset limpa só o que o cartão escreveu** (`CARD_PROPS`), nunca `clearProps:'all'`, que apagaria o `isolation` inline do fundo animado (regra 20). O alvo é sempre o próprio elemento, nunca os filhos: a cascata encolheria cada coluna para um lado.

31. **Marquee é motor próprio, sem gatilho, e o sentido do movimento mora na posição — não no deslocamento.** `marquee-auto` e `marquee-scroll` ([class-presets.php](includes/class-presets.php), motor `marquee`, [engine-marquee.js](assets/js/frontend/engine-marquee.js)) correm o conteúdo do elemento em fila infinita, como o Marquee Headings e o Marquee Layout do Uncode. Sete consequências:
    - **gatilho fixo em `load` e `bind()` direto.** O efeito não tem começo nem fim: o `bindTrigger()` do `core.js` testa `spec.marquee` antes de qualquer gatilho e entrega o elemento ao `bind()` do motor. Duração, espera, curva, ponto de disparo e "só uma vez" saem do painel por `WFAN_Presets::ids_marquee()` — com o gatilho gravado ainda em `scroll-in` por padrão, o `viewport` e o `once` precisam do `preset!` na condição, senão aparecem sem ser obedecidos. "Desligar no celular" continua, porque quem obedece é o `core.js`;
    - **o deslocamento `u` soma auto/rolagem + arrasto, e a direção só entra no `pos()`.** Arrastar e a inércia mexem no mesmo `ud` com `dx * dir`; se a direção fosse aplicada ao `u`, arrastar ao contrário do movimento inverteria o gesto;
    - **o laço se apaga.** Roda só com o elemento na tela (`IntersectionObserver`), congelado ele termina no primeiro quadro, e o amortecimento da rolagem converge. O passo é limitado a 64 ms (aba que volta do segundo plano). Um `requestAnimationFrame` eterno em página de loja é bateria — mesmo critério da regra 27;
    - **volta de cópia = largura do grupo, com o espaço dentro do grupo.** O grupo leva `padding-inline-end` igual ao `gap` dos itens: sem isso, a emenda entre uma cópia e outra seria maior que o espaço entre itens. A medida é `getBoundingClientRect().width` (fracionária); arredondar acumulava um salto no fim de cada volta;
    - **a cópia é inerte.** `aria-hidden` e `inert`, sem `id` repetido e sem `wfan-pending` herdado de filho animado — senão leitor de tela lê duas vezes, o Tab passa por links invisíveis e a cópia nasce escondida;
    - **o arrasto só captura o ponteiro depois de 5 px.** Capturar no `pointerdown` mandaria o clique para o miolo e mataria os links do marquee; e o clique que sucede um arrasto é engolido na fase de captura. O hover que desacelera ignora toque (`pointerType`), senão um toque deixa o marquee lento até tocar noutro lugar;
    - **no editor vale a regra 19.** O canvas não tem `data-wfan`; o marquee só monta pelo ▶ Testar/preview automático, e o `bind()` desmonta qualquer montagem anterior (o `wfanPlay` zera `el.__wfan` sem chamar `reset`). A esteira some quando o Elementor re-renderiza o widget, e é o que se quer.

---

## 3. Arquitetura

```
wooflow-animations.php   bootstrap: cabeçalho, constantes, requisitos, boot
includes/
  class-plugin.php          singleton; carrega e liga os módulos
  class-requirements.php    PHP/WP/Elementor; admin notice, nunca fatal
  class-keys.php            REGRA 1 e 3: dono único dos nomes de chave
  class-presets.php         catálogo (44 nossos + 36 nativas, REGRA 21), grupos, gatilhos, curvas, css_only; filtro wfan_presets
  class-controls.php        REGRA 2, 18, 21 e 22: injeta a seção na primeira aba dos 5 tipos
  class-overlay.php         REGRA 20: fundo animado por elemento; dono das custom properties
  class-control-picker.php  controle `wfan-picker` (template da grade)
  class-editor.php          assets do editor + config tipada (REGRA 17) + contrato do ▶ Testar (REGRA 19)
  class-render.php          REGRA 4, 18, 20 e 21: escreve data-wfan; resolve motor, gatilho e animação nativa
  class-assets.php          REGRA 5, 8, 17, 18, 20, 21 e 24: registry, carregamento sob demanda, pré-esconde, config tipada
  class-lenis.php           scroll suave global
  class-blur.php            blur progressivo global; efeito de borda, não preset
  class-backtotop.php       botão "voltar ao topo" global; controle de verdade, não decoração
  class-header.php          REGRA 27: barra fixa; fundo e sombra progressivos, cor lida do elemento
  class-popup.php           REGRA 28: popup nascendo de um círculo no ponto do clique (só Pro)
  (marquee: presets em class-presets.php, controles em class-controls.php, payload `mq` em class-render.php)
  class-lottie.php          REGRA 6: handle do lottie-web + guarda de URL
  class-settings.php        REGRA 16: ajustes globais; menu-pai resolvido em runtime
  class-library.php         "minhas animações" (option + ajax)
templates/admin/       settings-page.php (casca: nav + form) + tabs/tab-<aba>.php (REGRA 16)
assets/js/editor/      copy-paste.js (REGRA 10) · control-picker.js · panel.js (REGRA 23)
assets/js/frontend/    core.js (orquestrador, REGRA 26) · engine-{css,gsap,anime,lottie,marquee}.js · lenis-boot.js · overlay.js (REGRA 20) · backtotop.js · header.js (REGRA 27) · popup.js (REGRA 28)
assets/css/            editor.css (REGRA 13) · frontend.css · blur.css · backtotop.css · header.css · popup.css · admin.css (REGRA 16)
tests/                 smoke.php + stubs.php · trigger.js (REGRA 25) · marquee.js (REGRA 31) · backtotop.js · header.js (REGRA 27) · popup.js (REGRA 28)
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
php tests/smoke.php          # 574 verificações, sem WordPress e sem banco
node tests/trigger.js        # REGRA 25: quantas vezes a animação toca
node tests/backtotop.js      # quando o script acha o botão, e por onde a subida vai
node tests/header.js         # REGRA 27: de onde sai a cor da barra, e se o laço para
node tests/popup.js          # REGRA 28: de onde sai a origem do círculo, e quando a caixa é medida
node tests/scrub.js          # REGRA 29 e 30: percurso da revelação e do cartão no scroll
node tests/marquee.js        # REGRA 31: posição da esteira, laço que se apaga, clique após arrasto
php -l <arquivo>             # lint de qualquer PHP alterado
node --check <arquivo>       # lint de qualquer JS alterado
npm run vendor               # reconstrói assets/lib/
```

O smoke test ([tests/smoke.php](tests/smoke.php)) sobe o plugin inteiro contra
dublês do WordPress e do Elementor ([tests/stubs.php](tests/stubs.php)), injeta
controles num elemento falso, renderiza vários presets e confere as regras 1-7,
11, 12, 14, 15, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28 e 31. **Rodar antes de qualquer commit**: ele pega exatamente a classe de
erro que só apareceria dentro do editor.

O `tests/trigger.js` sobe o `core.js` de verdade num DOM de mentira e dirige o
`IntersectionObserver` à mão: é o único lugar onde se conta **quantas vezes** a
animação toca, que é a classe de defeito da regra 25.

O `tests/backtotop.js` faz o mesmo com o botão de voltar ao topo, e existe por
um defeito que o HTML não mostrava: o `<button>` sai no `wp_footer` com
prioridade 100 e os scripts de rodapé do WordPress saem na **20**, então o
script roda **antes** de o botão existir no DOM. O `querySelector` devolvia
`null`, nada era amarrado e o botão nascia morto — com markup, CSS e
configuração perfeitos na página. **Script de rodapé nosso nunca pode supor que
o markup já está lá**: ou espera o `DOMContentLoaded`, ou é impresso antes da
20. Esse arquivo também é o único lugar que confere por onde a subida vai (Lenis
ou tween) e que o anel não escreve nada quando está desligado.

O `tests/header.js` faz o mesmo com a barra fixa, e existe por duas coisas que
nenhuma leitura do HTML mostra: que a cor sai do **elemento**, não de um ajuste
— a medição tem de acontecer com a regra do script fora do caminho, senão ela
lê de volta o transparente que nós mesmos escrevemos —, e que o laço de
amortecimento **para** ao chegar no alvo. Um `requestAnimationFrame` eterno em
toda página do site não aparece na tela: aparece na bateria.

O `tests/popup.js` dirige o clique, o evento do Elementor e os quadros um a um.
Os dois defeitos que ele guarda não se vêem no HTML: que a origem do círculo sai
do clique lido na **fase de captura** — na borbulha o ponto chegaria depois da
abertura, e o círculo nasceria onde o dedo estava na vez anterior —, e que a
caixa só é medida no **segundo** quadro, porque na primeira abertura o Elementor
ainda está posicionando o popup. Esse segundo quadro é o que fazia o defeito
parecer fantasma: errava só na primeira abertura de cada carregamento.

O que nenhum deles cobre e exige o `woo.local` no ar: a aparência da
grade no painel, o preview automático, o ▶ Testar, os atalhos de teclado, o Lenis
junto do ScrollTrigger
e qualquer coisa que dependa de layout real (a quebra de linhas do texto mede
`offsetTop`, e o reagrupamento dela depende de a webfont ter chegado; o fundo
animado mede `clientWidth`/`clientHeight` para corrigir a proporção da mancha,
e só o navegador resolve a cor global que ele lê do CSS). O popup circular
(regra 28) também só se vê ali: precisa de um popup do Elementor Pro aberto por
um clique de verdade.
