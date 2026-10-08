# DW Animações para Elementor

Painel de animações pronto para aplicar em qualquer elemento do Elementor — e as
ações **Copiar animação** / **Colar animação** que deram origem ao plugin.

O Elementor clássico oferece uma lista de nomes de animação de entrada, sem
preview, sem controle de gatilho, sem scroll travado, sem cascata e sem timeline.
Este plugin preenche essa lacuna: **38 presets** escolhidos numa grade visual que
anima no hover, com gatilho, duração, curva e cascata configuráveis em cada
elemento.

> O módulo `interactions` do Elementor 4 resolve parte disso, mas **só para
> elementos atômicos**, atrás do experimento `e_atomic_elements`. Widget
> clássico continua sem nada — é aí que este plugin trabalha. Quando o elemento
> é atômico, o plugin sai do caminho e deixa o nativo trabalhar.

---

## Instalação

1. Baixe o zip pela aba *Releases* (ou rode `./build.sh` para gerar um).
2. **Plugins → Adicionar novo → Enviar plugin**, envie o zip e ative.
3. As bibliotecas de terceiros não vão no repositório. Clonando pelo git, rode:

```bash
npm run vendor
```

Precisa de Node 18+. Sem esse passo o plugin funciona, mas só com o motor
próprio (os presets de scroll, SVG e Lottie ficam inertes e o conteúdo aparece
normalmente).

**Requisitos:** WordPress 6.0+, PHP 7.4+, Elementor 3.5+. Testado com Elementor
4.3.4 e Elementor Pro 4.2.3.

---

## Como se usa

Selecione qualquer elemento e a seção **DW Animações** está logo na primeira
aba: **Layout** em container, seção e coluna (no topo, antes de tudo) e
**Conteúdo** nos widgets, abaixo dos controles próprios deles.

```
┌─ DW Animações ──────────────┐
│ [Buscar animação…        ]  │
│ Todas Entrada Texto Scroll  │
│ Ênfase SVG Lottie           │
│ ┌────┐ ┌────┐ ┌────┐        │
│ │ ↑  │ │ ↗  │ │ ⤢  │  ← animam no hover
│ │Fade│ │Slid│ │Zoom│        │
│ └────┘ └────┘ └────┘        │
│ [ ▶ Testar ]                │
│ [Copiar][Colar][Salvar]     │
│ Gatilho  [Ao entrar na tela]│
│ Duração  ▸━━━━━●━━  800ms   │
└─────────────────────────────┘
```

- **▶ Testar** reproduz a animação no preview sem salvar nada.
- **Copiar / Colar** levam a configuração inteira para outro elemento — inclusive
  a animação nativa do Elementor, se houver. Funcionam também pelo botão direito
  e por `Ctrl+Alt+C` / `Ctrl+Alt+V` (`Cmd+Alt+…` no Mac).
- **Salvar na biblioteca** guarda o conjunto com um nome, para reaplicar em um
  clique em qualquer elemento do site.

### Gatilhos

| Gatilho | Quando dispara |
|---|---|
| Ao entrar na tela | quando a porcentagem escolhida do elemento fica visível |
| Ao carregar a página | no primeiro quadro |
| Ao sair da tela | depois de já ter entrado uma vez |
| Travado no scroll | o progresso da animação segue a rolagem |
| No hover / No clique | interação direta |

Os nomes seguem o vocabulário do módulo nativo do Elementor 4 (`scrollIn`,
`scrollOut`, `hover`, `click`) de propósito: migrar depois fica trivial.

### Cascata

Qualquer preset de entrada pode animar **os filhos** em vez do elemento inteiro —
é o que dá vida a grades, listas e loops. O seletor padrão (`> *`) pega os filhos
diretos; aceita qualquer seletor CSS relativo. Os presets de texto fazem o mesmo
por linha, palavra ou letra, com o texto inteiro preservado no `aria-label` para
leitores de tela.

---

## Presets

| Grupo | Presets | Motor |
|---|---|---|
| **Entrada** (17) | fade, fade/slide nas 4 direções, zoom in/out, desfoque, girar, virar H/V, cortina vertical/horizontal | próprio |
| **Texto** (4) | revelar por linha, por palavra, por letra, cortina por linha | próprio |
| **Scroll** (9) | parallax H/V, fade/escala/giro/cortina travados no scroll, fixar na tela, barra de progresso, contador | GSAP |
| **Ênfase** (5) | pulsar, flutuar, tremer, balançar, brilhar | próprio |
| **SVG** (1) | desenhar traço | Anime.js |
| **Lottie** (2) | tocar, travado no scroll | lottie-web |

Para adicionar os seus:

```php
add_filter( 'dw_anim_presets', function ( $presets ) {
	$presets['meu-efeito'] = [
		'label'  => 'Meu efeito',
		'group'  => 'entrada',
		'engine' => 'css',
		'params' => [ 'distance', 'stagger' ],
	];

	return $presets;
} );
```

O card do novo preset entra na grade sozinho. Para dar a ele um preview no hover,
declare `@keyframes` e aponte `.dw-anim-card:hover .dw-pv-meu-efeito` no seu CSS
do editor.

---

## Desempenho

É o ponto em que mais se errou neste tipo de plugin, então aqui a conta é
explícita: **nenhuma biblioteca é carregada por padrão.**

| Página | O que carrega |
|---|---|
| Sem animação | nada (só ~400 bytes de CSS no `<head>`) |
| Fade, slide, texto, ênfase | `core.js` + motor próprio, ~10 KB |
| Scroll travado, pin, parallax, contador | \+ GSAP e ScrollTrigger, ~115 KB |
| Desenho de SVG | \+ Anime.js, ~115 KB |
| Lottie | \+ lottie-web, ~164 KB — ou **zero**, se o Elementor Pro já estiver ativo, porque a cópia dele é reusada |

Quem decide o motor é o servidor, na hora de renderizar: é isso que permite
enfileirar só o que a página realmente usa. Nos **Ajustes** é possível bloquear
qualquer biblioteca por completo.

### Nada de conteúdo invisível

Animação de entrada precisa esconder o elemento antes de mostrá-lo, e é assim que
sites quebram: o JS falha e o conteúdo nunca aparece. Três redes de proteção:

1. um `<script>` de 150 bytes no `<head>` revela tudo se o `core.js` não tiver
   carregado em 3 segundos;
2. um `<noscript>` revela tudo quando o JS está desligado;
3. `prefers-reduced-motion` revela tudo sem movimento — e nenhuma biblioteca é
   nem baixada.

---

## Scroll suave (Lenis)

Ligado em **Elementor → DW Animações**, com suavidade, duração e entradas
(roda do mouse, toque) configuráveis. O que normalmente quebra já está tratado:
ponte com o ScrollTrigger pelo ticker do GSAP (dois loops de `requestAnimationFrame`
produzem tremor), âncoras internas, offset da barra de administração, desligado
dentro do editor e sob `prefers-reduced-motion`.

---

## Ajustes

**Elementor → DW Animações**

- **Bibliotecas** — permitir ou bloquear GSAP, Anime.js e Lottie.
- **Scroll suave** — Lenis e seus parâmetros.
- **Acessibilidade** — respeitar "reduzir movimento" (ligado de fábrica).
- **Celular** — desligar todas as animações abaixo de uma largura.
- **Animação nativa** — desligar a entrada do Elementor onde houver animação DW,
  evitando as duas rodando no mesmo elemento.
- **Diagnóstico** — registrar no console cada animação registrada e disparada.

---

## Para desenvolvedores

### Filtros

| Filtro | Para quê |
|---|---|
| `dw_anim_presets` | adicionar, remover ou ajustar presets |
| `dw_anim_payload` | último ajuste no contrato enviado ao navegador |
| `dw_anim_lenis_active` | desligar o scroll suave em contextos específicos |
| `dw_anim_lottie_handle` | apontar para outra cópia do lottie-web |
| `dw_anim_controls_tab` | mover a seção para outra aba do painel |

Para devolver a seção à aba Avançado:

```php
add_filter( 'dw_anim_controls_tab', function () {
	return \Elementor\Controls_Manager::TAB_ADVANCED;
} );
```

### Contrato do front-end

Cada elemento animado sai com um JSON no wrapper:

```html
<div class="elementor-element dw-anim dw-anim-pending"
     data-dw-anim='{"v":1,"p":"fade-up","eng":"css",
                    "tr":{"t":"scroll-in","vp":20,"once":1},
                    "d":800,"dl":0,"e":"power2.out","dist":40}'>
```

O `core.js` lê o atributo, resolve o gatilho e entrega a um motor. Motor novo se
registra assim:

```js
window.dwAnim.register( 'meu-motor', {
	play: function ( el, spec, targets ) { /* … */ },
	bind: function ( el, spec, targets ) { /* opcional: gatilho travado no scroll */ },
	reset: function ( el, spec, targets ) { /* opcional: usado pelo ▶ Testar */ }
} );
```

### Testes

```bash
php tests/smoke.php
```

83 verificações sem WordPress, sem banco e sem Elementor instalado: nomes de
chave, condições de controle apontando para controle existente, motor resolvido
por preset, biblioteca enfileirada só quando pedida, reuso do Lottie do Pro, e o
contrato `data-dw-anim` conferido chave por chave entre o PHP e o `core.js`.

---

## Bibliotecas

| Biblioteca | Para quê | Licença |
|---|---|---|
| [GSAP](https://gsap.com) + ScrollTrigger | presets de scroll | GSAP Standard License — gratuita, **não** GPL |
| [Anime.js](https://animejs.com) | desenho de traço em SVG | MIT |
| [Lenis](https://lenis.darkroom.engineering) | scroll suave | MIT |
| [lottie-web](https://github.com/airbnb/lottie-web) | Lottie | MIT |

A licença do GSAP é gratuita para este uso mas não é compatível com GPL — por
isso o plugin é distribuído pelo GitHub e não pelo repositório oficial do
WordPress.

---

## Changelog

### 2.0.0

- Painel **DW Animações** na primeira aba de cada elemento, com 38 presets em grade visual, preview no hover, busca
  e grupos, em widget, container, seção e coluna.
- Gatilhos: entrar na tela, carregar, sair da tela, travado no scroll, hover e
  clique. Cascata em filhos, linhas, palavras ou letras.
- Quatro motores com carregamento sob demanda: próprio (Web Animations API),
  GSAP + ScrollTrigger, Anime.js e lottie-web — reusando a cópia do Elementor Pro.
- Scroll suave global com Lenis, com ponte para o ScrollTrigger.
- Página de ajustes, biblioteca de animações salvas e `prefers-reduced-motion`
  respeitado de fábrica.
- Copiar/colar reescrito: carrega também os ajustes DW, payload versionado,
  colagem em vários elementos em **uma** entrada de histórico, atalhos pelo
  `$e.shortcuts`.
- Plugin reorganizado de um arquivo único em 12 classes e 9 arquivos de asset;
  todas as strings traduzíveis.

### 1.0.0

- Copiar e colar a animação de entrada pelo menu de contexto e por atalho.

---

## Licença

GPL-2.0-or-later. Autor: [David William da Costa](https://davidwilliam.studio).
