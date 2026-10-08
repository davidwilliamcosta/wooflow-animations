#!/usr/bin/env bash
#
# Gera o zip de release em release/.
#
# Uso: ./build.sh
set -euo pipefail

SLUG="wooflow-animations"
ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
VER="$( grep -m1 "^define( 'WFAN_VER'" "$ROOT/$SLUG.php" | sed -E "s/.*'([0-9.]+)'.*/\1/" )"
HEADER_VER="$( grep -m1 ' \* Version:' "$ROOT/$SLUG.php" | sed -E 's/.*Version: *([0-9.]+).*/\1/' )"

if [ "$VER" != "$HEADER_VER" ]; then
	echo "Erro: cabeçalho Version: ($HEADER_VER) e WFAN_VER ($VER) não batem. Ver CLAUDE.md." >&2
	exit 1
fi

if [ ! -f "$ROOT/assets/lib/manifest.json" ]; then
	echo "Erro: assets/lib está vazio. Rode 'npm run vendor' antes de empacotar." >&2
	exit 1
fi

echo "Rodando o smoke test…"
php "$ROOT/tests/smoke.php" > /dev/null

OUT="$ROOT/release"
STAGE="$OUT/$SLUG"

rm -rf "$STAGE"
mkdir -p "$STAGE"

rsync -a \
	--exclude '.git' \
	--exclude '.github' \
	--exclude 'node_modules' \
	--exclude 'release' \
	--exclude 'tests' \
	--exclude 'scripts' \
	--exclude '.gitignore' \
	--exclude 'package.json' \
	--exclude 'build.sh' \
	--exclude 'CLAUDE.md' \
	--exclude '.DS_Store' \
	--exclude '._*' \
	"$ROOT/" "$STAGE/"

find "$STAGE" -name '._*' -delete
find "$STAGE" -name '.DS_Store' -delete

ZIP="$OUT/$SLUG-$VER.zip"
rm -f "$ZIP"
( cd "$OUT" && zip -rq "$ZIP" "$SLUG" -x '*.DS_Store' '*._*' )
rm -rf "$STAGE"

echo "Pronto: ${ZIP#"$ROOT"/} ($( du -h "$ZIP" | cut -f1 ))"
