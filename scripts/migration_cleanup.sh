#!/bin/bash
# ============================================================
# NETTOYAGE MIGRATION VM→Neon
# À exécuter dans Replit APRÈS que l'import Neon est confirmé.
# ============================================================

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "=== ÉTAPE 1 : Suppression des fichiers CSV uploadés ==="
if [ -d "$PROJECT_ROOT/database/migration_exports" ]; then
    rm -rf "$PROJECT_ROOT/database/migration_exports"
    echo "✓ database/migration_exports/ supprimé"
else
    echo "  (dossier déjà absent)"
fi

echo ""
echo "=== ÉTAPE 2 : Suppression du controller temporaire ==="
CONTROLLER="$PROJECT_ROOT/app/Http/Controllers/MigrationUploadController.php"
if [ -f "$CONTROLLER" ]; then
    rm "$CONTROLLER"
    echo "✓ MigrationUploadController.php supprimé"
else
    echo "  (controller déjà absent)"
fi

echo ""
echo "=== ÉTAPE 3 : Suppression du bloc de routes temporaires ==="
ROUTES_FILE="$PROJECT_ROOT/routes/web.php"
# Supprime le bloc entre les marqueurs de migration
python3 - <<'PYEOF'
import re, sys

path = sys.argv[1] if len(sys.argv) > 1 else ""
import os
path = os.environ.get("ROUTES_FILE", "")

with open(path, "r") as f:
    content = f.read()

# Supprime le bloc complet de migration (marqueurs inclus)
pattern = r'\n// ={10,}\n// MIGRATION TEMPORAIRE.*?// ={10,}\nRoute::prefix\(\'_migration\'\).*?\}\);'
cleaned = re.sub(pattern, '', content, flags=re.DOTALL)

if cleaned != content:
    with open(path, "w") as f:
        f.write(cleaned)
    print("✓ Bloc migration supprimé de routes/web.php")
else:
    print("  (bloc migration déjà absent de web.php)")
PYEOF

export ROUTES_FILE="$ROUTES_FILE"
python3 - <<'PYEOF'
import re, os

path = os.environ.get("ROUTES_FILE", "")
with open(path, "r") as f:
    content = f.read()

pattern = r'\n// ={10,}\n// MIGRATION TEMPORAIRE[^\n]*\n// ={10,}\nRoute::prefix\(._migration.\).*?\}\);'
cleaned = re.sub(pattern, '', content, flags=re.DOTALL)

if cleaned != content:
    with open(path, "w") as f:
        f.write(cleaned)
    print("✓ Bloc migration supprimé de routes/web.php")
else:
    print("  (bloc migration déjà absent de routes/web.php)")
PYEOF

echo ""
echo "=== ÉTAPE 4 : Suppression du script de nettoyage lui-même ==="
SCRIPT_PATH="$PROJECT_ROOT/scripts/migration_cleanup.sh"

echo ""
echo "=== ÉTAPE 5 : Nettoyage cache Laravel ==="
cd "$PROJECT_ROOT"
php artisan route:clear   && echo "✓ route:clear"
php artisan config:clear  && echo "✓ config:clear"
php artisan cache:clear   && echo "✓ cache:clear"
php artisan optimize:clear && echo "✓ optimize:clear"

echo ""
echo "=== ÉTAPE 6 : Suppression de la Deploy Key SSH ==="
echo "  → Va sur : github.com/SteveStrategyBuzzer/StrategyBuzzer/settings/keys"
echo "  → Supprime la clé : replit-migration-temp"
rm -f ~/.ssh/id_ed25519 ~/.ssh/id_ed25519.pub
echo "✓ Clé SSH Replit supprimée localement"

echo ""
echo "=== ÉTAPE 7 : Suppression de la branche GitHub temporaire ==="
echo "  → Lance depuis ta VM :"
echo "    git push origin --delete migration/vm-data-2026"

echo ""
echo "=== NETTOYAGE TERMINÉ ==="
echo "  Aucune trace de la migration ne subsiste dans Replit."

# Auto-suppression du script
rm -- "$SCRIPT_PATH"
echo "✓ Script de nettoyage auto-supprimé"
