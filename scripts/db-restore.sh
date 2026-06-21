#!/usr/bin/env bash
# =============================================================================
# ⚠️  RESTAURATION — OPÉRATION SENSIBLE (écrit dans la base de PRODUCTION).
#
# À n'exécuter QUE sur incident confirmé et APRÈS alerte explicite au user.
# Ce script peut écraser des données : il exige une DOUBLE confirmation manuelle
# et n'est JAMAIS appelé automatiquement (ni par les tests, ni par post-merge).
#
# 👉 PRIORITÉ : tenter d'ABORD la récupération Point-In-Time (PITR) de Neon
#    depuis la console Neon/Replit (restauration sans perte vers un instant T)
#    AVANT d'utiliser ce script de restauration par dump.
#
# Usage : bash scripts/db-restore.sh database/backups/strategybuzzer_AAAA-MM-JJ_HHMMSS.dump
# =============================================================================
set -euo pipefail

FILE="${1:-}"
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
  echo "❌ Fichier de sauvegarde introuvable." >&2
  echo "   Usage : bash scripts/db-restore.sh <fichier.dump>" >&2
  exit 1
fi
if [ -z "${DATABASE_URL:-}" ]; then
  echo "❌ DATABASE_URL absent de l'environnement. Abandon." >&2
  exit 1
fi

echo "⚠️  Vous allez RESTAURER « $FILE » dans la base de PRODUCTION."
echo "    Cette opération peut écraser des données actuelles."
read -r -p "    Tapez exactement RESTAURER pour continuer : " c1
[ "$c1" = "RESTAURER" ] || { echo "Abandon."; exit 1; }
read -r -p "    Confirmez une 2e fois (tapez OUI) : " c2
[ "$c2" = "OUI" ] || { echo "Abandon."; exit 1; }

echo "♻️  Restauration en cours…"
# --clean --if-exists : recrée les objets existants ; -1 : transaction unique (tout-ou-rien)
pg_restore --no-owner --no-privileges --clean --if-exists -1 -d "$DATABASE_URL" "$FILE"
echo "✅ Restauration terminée."
