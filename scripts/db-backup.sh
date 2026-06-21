#!/usr/bin/env bash
# =============================================================================
# Sauvegarde COMPLÈTE (lecture seule) de la base Postgres Neon de production.
#
# pg_dump ne fait QUE LIRE la base : il ne supprime, ne modifie et ne crée
# JAMAIS aucune donnée applicative. Aucun risque pour les données joueurs.
#
# Usage :   bash scripts/db-backup.sh
# Sortie :  database/backups/strategybuzzer_AAAA-MM-JJ_HHMMSS.dump (gitignoré)
# =============================================================================
set -euo pipefail

if [ -z "${DATABASE_URL:-}" ]; then
  echo "❌ DATABASE_URL absent de l'environnement. Abandon." >&2
  exit 1
fi

DIR="database/backups"
mkdir -p "$DIR"
TS="$(date +%Y-%m-%d_%H%M%S)"
OUT="$DIR/strategybuzzer_${TS}.dump"

echo "📦 Sauvegarde (lecture seule) en cours → $OUT"
# -Fc : format custom (compressé + restauration sélective via pg_restore)
# --no-owner / --no-privileges : portable entre environnements
pg_dump --no-owner --no-privileges -Fc "$DATABASE_URL" -f "$OUT"

echo "✅ Sauvegarde terminée : $OUT ($(du -h "$OUT" | cut -f1))"
echo "ℹ️  Fichiers gitignorés (database/backups/) — ne JAMAIS les committer (données joueurs)."
