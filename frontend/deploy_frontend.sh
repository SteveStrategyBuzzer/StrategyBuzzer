#!/usr/bin/env bash
set -euo pipefail
FRONT="/home/stevegroupe/strategybuzzer_clean/strategybuzzer/frontend"
PUB="/home/stevegroupe/strategybuzzer_clean/strategybuzzer/public"

cd "$FRONT"
npm run build

rm -rf "$PUB/assets"
rm -f "$PUB/index.html" "$PUB/vite.svg" || true

cp -R "$FRONT/dist/assets" "$PUB/"
cp "$FRONT/dist/index.html" "$PUB/"
sed -i 's#\./assets/#/assets/#g' "$PUB/index.html"

echo "== FICHIERS =="
ls -l "$PUB/index.html"
ls -l "$PUB/assets" | sed -n '1,8p'

echo "== TESTS =="
curl -I http://34.47.63.38:8000/app || true
