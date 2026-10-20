# WooFlow Animations for Elementor

Painel de animações pronto para aplicar em qualquer elemento do Elementor — e as
ações **Copiar animação** / **Colar animação** que deram origem ao plugin.

> Parte da família **WooFlow**. Até a versão 2.0.0 este plugin se chamava
> *DW Copiar Animação Elementor* e ficava na pasta `dw-copiar-animacao-elementor`;
> a 2.1.0 renomeou tudo. Quem vem da 1.x precisa **desativar e apagar** a versão
> antiga antes de instalar esta — são pastas diferentes, e o WordPress trata as
> duas como plugins distintos.

O Elementor clássico oferece uma lista de nomes de animação de entrada, sem
preview, sem controle de gatilho, sem scroll travado, sem cascata e sem timeline.
Este plugin preenche essa lacuna: **80 presets** escolhidos numa grade visual que
anima no hover, com gatilho, duração, curva e cascata configuráveis em cada
elemento — mais um **fundo animado** por elemento, que não é animação de entrada
e convive com uma.

As 36 animações de entrada do próprio Elementor estão lá dentro, no grupo
**Nativas**: escolhidas por aqui elas ganham preview no card, duração e curva
exatas (o painel do Elementor só oferece lento/normal/rápido) e não põem um byte
de JavaScript deste plugin na página — quem anima é o Elementor.

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

Selecione qualquer elemento e a seção **WooFlow Animations** está logo na primeira
aba: **Layout** em container, seção e coluna (no topo, antes de tudo) e
**Conteúdo** nos widgets, abaixo dos controles próprios deles.

```
┌─ WooFlow Animations ──────────────┐
│ [Buscar animação…        ]  │
│ Todas Entrada Nativas Texto │
│ Scroll Ênfase SVG Lottie    │
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

- **O preview toca sozinho**: escolheu a animação, ela acontece no canvas; mexeu
  na duração, na distância ou na curva, ela repete. O mesmo vale para o fundo
  animado, que aparece no instante em que o toggle liga.
- **▶ Testar** repete quando você quiser, sem salvar nada.
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

### Inverter cores no hover

O preset **Inverter cores no hover** (grupo *Ênfase*) é o caso clássico da lista
de itens em que o bloco inteiro troca de cor quando o mouse passa: fundo, títulos,
textos e ícones de uma vez, com transição suave.

Aplique-o **no bloco do item**, não na lista — e leve para os outros itens com
**Copiar / Colar**, que carrega as cores junto. Os controles são cinco cores e a
velocidade:

| Controle | O que pinta |
|---|---|
| Cor de fundo no hover | o próprio bloco |
| Cor dos títulos no hover | todo `.elementor-heading-title` dentro dele |
| Cor do número ou rótulo | só os títulos que estão **direto** no bloco — o `01`, `02`… de uma lista numerada |
| Cor dos textos no hover | os widgets de editor de texto, parágrafos e listas |
| Cor dos ícones e SVG | ícone de fonte e SVG, inclusive o que traz a cor dentro do arquivo |

Ele é o único preset que não liga motor nenhum: o efeito é um **estado**, não uma
linha do tempo, então sai inteiro no CSS que o Elementor já escreve para o
elemento — página que só usa este preset não baixa um byte de JavaScript. Em
troca, "desligar no celular", duração, espera e curva somem do painel: são
controles que só o JS saberia obedecer.

As mesmas cores respondem à classe **`wfan-hv-on`**: é por ela que o ▶ Testar
mostra o efeito sem mouse, e é como se deixa um item **já invertido** — o
"ativo" de uma lista. Basta adicioná-la em *Avançado → Classes CSS* do bloco.

---

## Fundo animado

Um degradê de ruído que se move sem parar atrás do conteúdo do elemento — o
efeito de abertura que temas como o Uncode usam no topo da página. **Não é
animação**: não tem gatilho, não começa nem termina, e por isso tem seção
própria, a **WooFlow — Fundo animado**, logo abaixo da de animações. O mesmo
elemento pode ter os dois ligados.

Vale em widget, container, seção e coluna. São seis controles:

| Controle | O que faz |
|---|---|
| Fundo animado | liga o efeito |
| Cor 1 / Cor 2 | as duas cores que o ruído mistura; aceitam cor global do Elementor. Repita a mesma nas duas para um fundo de uma cor só, que respira pela transparência |
| Velocidade | lenta, média ou rápida |
| Tamanho das manchas | grandes, médias ou pequenas |
| Opacidade | 0 a 100% |
| Mistura com o fundo | `normal`, multiplicar, tela, sobrepor, luz suave, subexposição, luminosidade — sobre uma imagem de fundo, *multiplicar* escurece e *tela* clareia |

O desenho é um `<canvas>` de 110 × 110 px, pintado pixel a pixel com ruído
simplex 3D e esticado pelo CSS até o tamanho do elemento: a resolução baixa é o
que torna o efeito barato, porque o borrão do upscale sai de graça. São ~0,5 ms
por quadro, a 30 fps no máximo, e **o laço para quando o elemento sai da tela**.
Com *reduzir movimento* ligado no sistema, o fundo é desenhado uma vez e
congela.

O canvas entra acima do fundo do elemento e abaixo de todo o conteúdo. Para
isso o elemento ganha um contexto de empilhamento próprio (`isolation: isolate`)
— o que significa que um filho que precise escapar por `z-index`, um submenu ou
um tooltip, fica contido nele. Não ligue o fundo em elementos assim.

---

## Presets

| Grupo | Presets | Motor |
|---|---|---|
| **Entrada** (17) | fade, fade/slide nas 4 direções, zoom in/out, desfoque, girar, virar H/V, cortina vertical/horizontal | próprio |
| **Nativas** (36) | as animações de entrada do Elementor: fade, zoom, quicar, deslizar, girar, velocidade da luz, rolar e os dez chamarizes (pulsar, tremer, tada…) | Elementor |
| **Texto** (6) | revelar por linha, por palavra, por letra, cortina por linha, desfoque por palavra, desfoque por letra | próprio |
| **Texto no scroll** (2) | revelar por palavra e por letra travado no scroll, acendendo a partir de uma opacidade de partida | GSAP |
| **Scroll** (10) | parallax H/V (com sentido invertível), fade/escala/giro/cortina travados no scroll, virar cartão (a seção encolhe e arredonda os cantos ao sair), fixar na tela, barra de progresso, contador | GSAP |
| **Ênfase** (6) | pulsar, flutuar, tremer, balançar, brilhar, inverter cores no hover | próprio |
| **SVG** (1) | desenhar traço | Anime.js |
| **Lottie** (2) | tocar, travado no scroll | lottie-web |

Para adicionar os seus:

```php
add_filter( 'wfan_presets', function ( $presets ) {
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
declare `@keyframes` e aponte `.wfan-card:hover .wfan-pv-meu-efeito` no seu CSS
do editor.

---

## Desempenho

É o ponto em que mais se errou neste tipo de plugin, então aqui a conta é
explícita: **nenhuma biblioteca é carregada por padrão.**

| Página | O que carrega |
|---|---|
| Sem animação | nada (só ~400 bytes de CSS no `<head>`) |
| Animação nativa | **nada do plugin** — só a folha de ~500 bytes que o Elementor já usa para aquela animação |
| Inverter cores no hover | **nenhum JS** — só o CSS que o Elementor já gera para o elemento |
| Fundo animado | `overlay.js`, ~7 KB, sem biblioteca nenhuma e sem o `core.js` |
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

Ligado em **Elementor → WooFlow Animations**, com suavidade, duração e entradas
(roda do mouse, toque) configuráveis. O que normalmente quebra já está tratado:
ponte com o ScrollTrigger pelo ticker do GSAP (dois loops de `requestAnimationFrame`
produzem tremor), âncoras internas, offset da barra de administração, desligado
dentro do editor e sob `prefers-reduced-motion`.

---

## Blur progressivo

Ligado em **Elementor → WooFlow Animations**: uma faixa fixa na borda da tela em
que o desfoque cresce aos poucos, em três camadas de `backdrop-filter` mascaradas
em degradê — é isso que evita a linha dura que um desfoque único deixa. Nenhum
HTML na página: a faixa é impressa no rodapé do site, com `aria-hidden` e sem
receber clique.

Configuráveis a borda (rodapé, topo ou as duas), a altura em porcentagem da tela,
o desfoque da camada mais forte — as outras duas acompanham na mesma proporção —,
o `z-index` e o desligamento em telas pequenas, onde desfocar a tela inteira sai
caro. O CSS são ~1 KB e só entra nas páginas em que o efeito está ligado; dentro
do editor a faixa não aparece, para não desfocar o que se está editando.

---

## Voltar ao topo

Um botão fixo que aparece depois de uma certa rolagem e volta ao topo da página.
Ao contrário da faixa de blur, que é decoração, este é um **controle de
verdade**: um `<button>` focável na ordem natural, com nome lido por leitor de
tela, que responde a Enter e Espaço — e que, ao subir, manda o foco para o começo
do conteúdo, para a ida ao topo valer também no teclado.

Configuráveis a posição (canto direito, canto esquerdo ou centralizado), o
afastamento das bordas, o tamanho, a forma, o ícone (três SVGs inline, sem
requisição e sem fonte de ícones), as cores do fundo, do ícone e do fundo no
hover, a rolagem a partir da qual ele aparece, a duração da subida, a animação de
entrada, o anel de progresso do scroll e o desligamento em telas pequenas.

A subida respeita quem está na página: com o **scroll suave** ligado ela passa
pela instância do Lenis, que é dona do scroll — sem isso a página subiria e
voltaria sozinha no quadro seguinte. Sem Lenis, é um tween próprio de vinte
linhas, interrompido no primeiro giro de roda ou toque, porque rolar no meio da
subida é o usuário dizendo que mudou de ideia. Com "reduzir movimento" no
sistema, a ida ao topo é um salto.

São ~1 KB de CSS e ~2 KB de JavaScript, que só entram nas páginas de um site onde
o botão está ligado: ele não passa pelo `core.js` nem carrega biblioteca nenhuma.
A camada começa em 1000, uma acima do blur progressivo, para o botão ficar nítido
sobre a faixa. Dentro do editor ele não aparece, para não cobrir a alça de um
elemento.

> As medidas e as cores saem em variáveis CSS impressas na página, como no blur:
> num site com cache de página inteira, trocar uma cor pede purge do cache.

---

## Barra fixa

O cabeçalho começa transparente e vai ganhando fundo e sombra conforme a página
desce, com amortecimento — em vez do salto seco de ligar e desligar uma classe.

Para escolher a barra, ponha a classe `wfan-header` no container do cabeçalho,
em *Avançado > Classes CSS*. **A cor é a que você já definiu no Elementor**, em
*Estilo > Fundo*, do tipo Clássico e sólida: o efeito só varia a transparência
dela. Não há campo de cor nos ajustes de propósito — o script lê a cor do próprio
elemento, e com um degradê no fundo ele não mexe em nada, em vez de deixar a
barra invisível.

Configuráveis a rolagem até o fundo cheio, o amortecimento, a força da sombra, a
camada e o posicionamento. Com o posicionamento ligado, desligue o Sticky do
Elementor nessa barra: os dois juntos disputam a mesma posição. Com "reduzir
movimento" no sistema, a cor acompanha a rolagem sem amortecimento.

São ~1 KB de CSS e ~2 KB de JavaScript, e o laço de quadros se apaga ao chegar no
alvo — nada de `requestAnimationFrame` girando para sempre em toda página.

---

## Popup circular

O popup deixa de aparecer de uma vez e passa a nascer de um círculo que cresce a
partir do ponto em que foi aberto — o ícone do mega menu, em geral. O site atrás
escurece e desfoca junto, com o degradê partindo do mesmo ponto.

**Precisa do Elementor Pro**: o efeito se pendura nos eventos de popup, que são
de lá. Sem o Pro, nada é carregado.

A origem do círculo não se configura: é o ponto do clique que abriu o popup.
Configuráveis as durações de abertura e fechamento, a cor da sobreposição, a
opacidade perto do ícone e nas bordas, o desfoque do site atrás e a saída do
desfoque em telas pequenas — onde ele é a parte cara, porque recalcula a página
inteira a cada quadro. Para os itens do menu entrarem escalonados depois do
círculo, ponha a classe `wfan-popup-item` neles.

Com "reduzir movimento" no sistema o círculo sai e sobra um fade; o conteúdo
aparece do mesmo jeito.

---

## Ajustes

Em **WooFlow → Animations**, no menu lateral. O menu **WooFlow** é o mesmo de
toda a linha: se nenhum outro plugin da família estiver instalado, este cria o
item; se já houver, ele só entra embaixo, ao lado dos outros. São sete abas, e
cada uma salva sozinha, sem mexer nas outras.

- **Bibliotecas** — permitir ou bloquear GSAP, Anime.js e Lottie.
- **Scroll suave** — Lenis e seus parâmetros.
- **Blur progressivo** — borda, altura, intensidade, camada e saída em telas pequenas.
- **Voltar ao topo** — posição, afastamento, tamanho, forma, ícone, cores, limiar
  de aparecimento, duração da subida, animação de entrada, anel de progresso e
  saída em telas pequenas.
- **Barra fixa** — rolagem até o fundo cheio, amortecimento, força da sombra,
  camada e posicionamento.
- **Popup circular** — durações de abertura e fechamento, cor e opacidades da
  sobreposição, desfoque do site atrás e saída do desfoque em telas pequenas.
- **Comportamento** — respeitar "reduzir movimento" (ligado de fábrica), desligar
  todas as animações abaixo de uma largura, desligar a animação de entrada do
  Elementor onde houver animação WooFlow, e registrar no console cada animação
  registrada e disparada.

---

## Para desenvolvedores

### Filtros

| Filtro | Para quê |
|---|---|
| `wfan_presets` | adicionar, remover ou ajustar presets |
| `wfan_payload` | último ajuste no contrato enviado ao navegador |
| `wfan_lenis_active` | desligar o scroll suave em contextos específicos |
| `wfan_blur_active` | desligar o blur progressivo em contextos específicos |
| `wfan_backtotop_active` | desligar o botão "voltar ao topo" em contextos específicos |
| `wfan_backtotop_label` | trocar o rótulo acessível do botão "voltar ao topo" |
| `wfan_header_active` | desligar a barra fixa em contextos específicos |
| `wfan_header_selector` | apontar a barra fixa para outro seletor |
| `wfan_popup_active` | desligar o popup circular em contextos específicos |
| `wfan_popup_ids` | restringir o popup circular a certos ids de popup |
| `wfan_lottie_handle` | apontar para outra cópia do lottie-web |
| `wfan_controls_tab` | mover a seção para outra aba do painel |

Para devolver a seção à aba Avançado:

```php
add_filter( 'wfan_controls_tab', function () {
	return \Elementor\Controls_Manager::TAB_ADVANCED;
} );
```

### Contrato do front-end

Cada elemento animado sai com um JSON no wrapper:

```html
<div class="elementor-element wfan wfan-pending"
     data-wfan='{"v":1,"p":"fade-up","eng":"css",
                    "tr":{"t":"scroll-in","vp":20,"once":1},
                    "d":800,"dl":0,"e":"power2.out","dist":40}'>
```

O `core.js` lê o atributo, resolve o gatilho e entrega a um motor. Preset do
grupo **Nativas** é a exceção: ele não sai com atributo nenhum, porque quem anima
é o Elementor — o wrapper leva só as classes `wfan wfan-native` e o campo
`_animation` preenchido.

Motor novo se registra assim:

```js
window.wfan.register( 'meu-motor', {
	play: function ( el, spec, targets ) { /* … */ },
	bind: function ( el, spec, targets ) { /* opcional: gatilho travado no scroll */ },
	reset: function ( el, spec, targets ) { /* opcional: usado pelo ▶ Testar */ }
} );
```

### Testes

```bash
php tests/smoke.php
node tests/trigger.js
node tests/backtotop.js
node tests/header.js
node tests/popup.js
```

491 verificações sem WordPress, sem banco e sem Elementor instalado: nomes de
chave, condições de controle apontando para controle existente, motor resolvido
por preset, biblioteca enfileirada só quando pedida, reuso do Lottie do Pro, o
contrato `data-wfan` conferido chave por chave entre o PHP e o `core.js`, a
entrega à animação nativa do Elementor sem levar asset nosso junto, o
`render_type` de cada controle, as medidas do blur progressivo, as medidas e o
markup acessível do botão de voltar ao topo, e a tela de
ajustes — padrão de UI, cobertura de classes e o recorte por aba que impede uma
aba de zerar as outras ao salvar.

O `tests/trigger.js` sobe o motor de verdade num DOM simulado e conta **quantas
vezes** cada animação toca ao entrar, sair e voltar à tela — a classe de defeito
que só aparece rolando a página. O `tests/backtotop.js` faz o mesmo com o botão
de voltar ao topo: quando o script acha o botão no rodapé, o limiar de aparição,
o anel e por onde a subida vai (Lenis ou tween próprio).

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

### 2.7.0

- **Parallax com sentido invertível.** O *Parallax vertical* e o *Parallax
  horizontal* ganharam a chave *Inverter direção*: ligada, o elemento anda contra
  a rolagem — sobe no vertical, vai para a esquerda no horizontal —, o que dá a
  sensação de uma camada mais próxima. A distância continua a mesma; só o sentido
  muda. Desligada, nada muda em relação à 2.6.0.

### 2.6.0

- **Quatro presets de texto novos**, no grupo *Texto*: desfoque por palavra e por
  letra (opacidade, deslocamento e desfoque entrando juntos, em cascata), e
  revelação por palavra e por letra **travada no scroll**, em que o texto nasce
  apagado numa opacidade de partida configurável e vai acendendo pedaço por
  pedaço conforme a página desce.
- **Quebra de texto consertada em três pontos.** As linhas passaram a ser
  medidas em duas passadas, antes de qualquer palavra mudar de lugar — medir
  enquanto move errava o agrupamento da segunda linha em diante, porque fechar
  uma linha reflui o resto. Cada palavra ganhou invólucro `nowrap`, então a
  quebra por letra não parte mais a palavra no meio. E as linhas são reagrupadas
  quando a largura muda ou quando a webfont chega, que é depois da primeira
  medida na maioria dos carregamentos. A cortina por linha também ganhou folga
  no recorte: ela cortava o rabo do g, do j e do p.
- **Barra fixa**, na aba nova *Barra fixa*: o cabeçalho ganha fundo e sombra
  progressivos conforme a página desce, com amortecimento. A cor é a que você já
  definiu no Elementor — não há campo de cor nos ajustes —, e com um degradê no
  fundo o efeito não mexe em nada em vez de deixar a barra invisível.
- **Popup circular**, na aba nova *Popup circular*: o popup nasce de um círculo
  que cresce do ponto em que foi aberto, com o site atrás escurecendo e
  desfocando junto. Precisa do Elementor Pro, e a origem não se configura — é o
  ponto do clique.
- **Virar cartão no scroll**, preset novo no grupo *Scroll*: a seção encolhe em
  direção à base e arredonda os cantos enquanto sai da tela — ou chega como
  cartão e se abre, ao entrar —, como a animação de linha do Uncode. Escala,
  raio, ponto fixo e percurso (em % da tela) são configuráveis. Num container,
  o fundo, a sobreposição e o vídeo de fundo arredondam juntos, sem
  `overflow:hidden`; um container que já tinha cantos arredondados parte do
  raio dele.
- Testes: 491 verificações no smoke test, mais o `tests/header.js` novo, que
  confere de onde sai a cor da barra e que o laço de amortecimento para, e o
  `tests/popup.js`, que confere de onde sai a origem do círculo e quando a caixa
  do popup é medida.

### 2.5.0

- **Botão "voltar ao topo"**, na aba nova *Voltar ao topo* dos ajustes: posição,
  afastamento, tamanho, forma, ícone, cores, limiar de aparecimento, duração da
  subida, animação de entrada, anel de progresso do scroll e saída em telas
  pequenas. É um `<button>` de verdade — focável, com nome acessível e com o foco
  indo para o começo do conteúdo depois da subida —, e não uma `<div>` com
  clique. Com o scroll suave ligado a subida passa pelo Lenis; sem ele, por um
  tween próprio que para assim que o usuário rola.
- Os campos de escolha e de cor dos ajustes passam a sair de dois mapas únicos
  (`WFAN_Settings::selects()` e `::colors()`), em vez de um `if` por campo: a
  lista que o painel oferece e a que o formulário aceita são conferidas uma
  contra a outra pelo smoke test.

### 2.4.3

- **Correção: a tela de ajustes não aparecia no menu** em sites sem o WooFlow
  Admin. Ela era registrada dentro do menu do Elementor, e o Elementor 4 esconde
  esse menu inteiro e remove de lá os itens de outros plugins. Agora, sem o hub
  sempre **WooFlow → Animations**: quando nenhum outro plugin da linha está
  instalado, este cria o menu *WooFlow* — do mesmo jeito, com o mesmo ícone e na
  mesma posição que os outros da família. A URL da tela não muda.

### 2.4.2

- **Correção: animação repetindo sozinha.** Com **Animar só uma vez** desligado,
  o efeito repetia a cada rolagem — e às vezes com a página parada. O gatilho de
  tela reagia a cada cruzamento do limiar de visibilidade, e a própria animação
  é um deles: um zoom ou um deslize mudam a caixa do elemento, a visibilidade
  oscila em volta do limiar e o efeito se realimenta. Agora ele só reage a
  **entrar** e **sair** da tela, e ignora o que acontece enquanto a animação
  roda. "Animar só uma vez" desligado quer dizer *toca de novo quando o elemento
  voltar à tela*, não *toca enquanto estiver visível*.
- Teste novo (`node tests/trigger.js`) que conta quantas vezes a animação toca,
  dirigindo o observador de tela num DOM simulado. O empacotamento não roda sem
  ele.

### 2.4.1

- **Correção: animação sumia em páginas com o cache de elementos do Elementor.**
  O Elementor guarda o HTML do documento em cache junto com a lista de arquivos
  que entraram na fila enquanto ele era montado, e devolve os dois no próximo
  acesso. O plugin enfileirava os scripts tarde demais, já fora dessa lista: a
  página vinha com as animações marcadas no HTML e sem o JavaScript que as faz
  acontecer. O conteúdo escondido só aparecia pela rede de segurança de 3
  segundos, e o defeito parecia intermitente — o primeiro acesso depois de
  salvar a página funcionava, os seguintes não.

### 2.4.0

- **Grupo "Nativas" no catálogo**: as 36 animações de entrada do próprio
  Elementor, com preview no card. Escolhida uma delas, o plugin preenche o campo
  *Avançado → Efeitos de movimento → Animação de entrada* e sai de cena —
  **nenhum JavaScript do WooFlow entra na página**. Duração, espera e curva
  continuam exatas, pelo CSS que o Elementor gera para o elemento.
- **Preview automático**: mexer em qualquer controle da seção reproduz a
  animação no canvas, como na animação de entrada nativa. O ▶ Testar continua,
  para repetir, e agora responde na hora — o contrato da configuração já veio
  junto com o preview.
- **Painel bem mais leve**: nenhum controle nosso re-renderiza mais o widget
  inteiro por Ajax a cada mexida, que era de onde vinham o atraso e os
  travamentos ao arrastar um slider.
- **Scroll travado**: novo controle **Começar quando** e a opção *terminar depois
  de a página rolar uma porcentagem da tela*, com o campo da porcentagem. Testar
  uma animação travada no scroll também não desamarra mais o gatilho dela.
- **Fundo animado ao vivo**: ligar o toggle mostra o canvas na hora, sem esperar
  um re-render do elemento.
- Correção: opções do campo **Gatilho** sumiam depois de trocar de elemento e só
  voltavam recarregando o editor.

### 2.3.0

- **Fundo animado por elemento**: degradê de ruído em `<canvas>` que roda sem
  parar atrás do conteúdo, com duas cores, velocidade, tamanho das manchas,
  opacidade e modo de mistura. Seção própria no painel, convive com uma animação
  de entrada no mesmo elemento e não carrega biblioteca nenhuma.

### 2.2.1

- Correção no preset **Inverter cores no hover**: ícones e SVG passam a inverter
  junto com o resto do bloco, pelo novo controle **Cor dos ícones e SVG** — que
  alcança também o SVG que traz a cor dentro do próprio arquivo.

### 2.2.0

- **Tela de ajustes em abas**, no padrão da linha WooFlow, em *Elementor →
  WooFlow Animations*. O menu-pai é resolvido em tempo de execução: o hub da
  família quando ele existe, o menu do Elementor quando o plugin está sozinho.
- **Blur progressivo** como efeito global de borda, com altura, intensidade e
  desligamento em telas pequenas.
- Preset **Inverter cores no hover**, o primeiro resolvido só em CSS: a página
  não baixa um byte de JavaScript por causa dele.
- O **▶ Testar** passa a reproduzir no preview. No canvas do editor o wrapper é
  construído pelo Elementor e nunca carregou o atributo que o plugin lê, então o
  botão não fazia nada desde que existe.
- Nenhuma configuração vai mais ao JavaScript por `wp_localize_script()`, que
  transforma todo valor em texto — e `"0"` é verdadeiro em JavaScript. Um toggle
  desligado chegava ligado, e era isso que apagava a animação inteira no celular.

### 2.1.0

- Renomeado para **WooFlow Animations for Elementor**, na família WooFlow: pasta e
  text domain `wooflow-animations`, prefixos `WFAN_`/`wfan_`/`wfan-`.
- Os filtros mudaram de nome (`dw_anim_*` → `wfan_*`), assim como o atributo do
  front-end (`data-dw-anim` → `data-wfan`) e as classes CSS (`dw-anim-*` →
  `wfan-*`). Quem estendia a 2.0.0 precisa ajustar — ela nunca foi publicada.
- Quem vem da 1.x: desinstale a versão antiga antes de instalar esta. A animação
  copiada no navegador pela 1.x continua colável.

### 2.0.0

- Painel **WooFlow Animations** na primeira aba de cada elemento, com 39 presets em grade visual, preview no hover, busca
  e grupos, em widget, container, seção e coluna.
- Gatilhos: entrar na tela, carregar, sair da tela, travado no scroll, hover e
  clique. Cascata em filhos, linhas, palavras ou letras.
- Quatro motores com carregamento sob demanda: próprio (Web Animations API),
  GSAP + ScrollTrigger, Anime.js e lottie-web — reusando a cópia do Elementor Pro.
- Scroll suave global com Lenis, com ponte para o ScrollTrigger.
- Página de ajustes, biblioteca de animações salvas e `prefers-reduced-motion`
  respeitado de fábrica.
- Copiar/colar reescrito: carrega também os ajustes WooFlow, payload versionado,
  colagem em vários elementos em **uma** entrada de histórico, atalhos pelo
  `$e.shortcuts`.
- Plugin reorganizado de um arquivo único em 12 classes e 9 arquivos de asset;
  todas as strings traduzíveis.

### 1.0.0

- Copiar e colar a animação de entrada pelo menu de contexto e por atalho.

---

## Licença

GPL-2.0-or-later. Autor: [David William da Costa](https://davidwilliam.studio).
