# assets/lib

Bibliotecas de terceiros. **Nada aqui é editado à mão** e nada aqui vai para o
git: a pasta é reconstruída por

```bash
npm run vendor
```

As versões estão fixadas no campo `dwVendor` do [package.json](../../package.json),
e `manifest.json` registra o que foi baixado, de onde e sob qual licença.

| Biblioteca | Para quê | Licença |
|---|---|---|
| GSAP + ScrollTrigger | presets de scroll travado, parallax, pin, barra de progresso e contador | GSAP Standard License — gratuita, **não** GPL |
| Anime.js | preset de desenho de traço em SVG | MIT |
| Lenis | scroll suave global | MIT |
| lottie-web | presets de Lottie, quando o Elementor Pro não está ativo | MIT |

A licença do GSAP é gratuita para este uso, mas não é compatível com GPL — por
isso este plugin é distribuído pelo GitHub e não pelo repositório oficial do
WordPress.
