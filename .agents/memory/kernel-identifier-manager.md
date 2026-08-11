---
name: KernelIdentifierManager — futur composant
description: Composant futur identifié en 2026-07-04 — unique autorité de génération de tous les identifiants du noyau (kernel_code, ks_hash, kld_hash, semantic_key, intent_key).
---

> ⛔ **SUPERSEDED 2026-08-11** — KLD/KEY_STRUCTURE retirés du flow canonique ; responsabilités absorbées par ValidationDominantIdeas. Ne pas rebrancher. Voir [canonical-kernel-flow.md](canonical-kernel-flow.md). Conservé pour historique.


# KernelIdentifierManager — Futur composant (non implémenté)

## Raison d'être

Identifié le 2026-07-04 lors de l'audit KLD.

Actuellement, aucun service ne génère de hashes. Mais plusieurs services DOIVENT en générer :
- KLD → kld_hash
- KEY_STRUCTURE → ks_hash, kernel_code (préfixe yy-xx-xxx-xxx-xxx)
- QuestionIntent → intent_key, semantic_key
- Le suffixe zz complète kernel_code

Si chaque brique fabrique son propre sha256(), cela crée :
- dispersion de la logique de hashing
- risque d'incohérence (algorithme, format, normalisation)
- tests dupliqués

## Futur rôle

**KernelIdentifierManager = unique autorité de génération de tous les identifiants du noyau.**

Responsabilités :
- Normalisation des champs avant hashing (casse, accents, séparateurs)
- Génération de kld_hash
- Génération de ks_hash
- Génération de semantic_key
- Génération de intent_key
- Construction complète du kernel_code (préfixe + suffixe zz)

## Règle d'application

**Aucun service existant ou futur ne doit appeler sha256() / hash() directement.**

Séquence prévue dans le pipeline :
```
KLD::check() → retourne canonical_direction (pas de hash)
KEY_STRUCTURE::validate() → retourne canonical_structure (pas de hash)
KernelIdentifierManager::build() → génère tous les hashes + kernel_code complet
QuestionIntent::create() ← reçoit les hashes déjà construits
```

## État actuel (2026-07-04)
- Composant non implémenté
- Pas de migration à prévoir (les colonnes kld_hash/ks_hash/kernel_code existent déjà)
- À implémenter APRÈS KLD et KEY_STRUCTURE
- Fichier attendu : `app/Services/QuestionBank/KernelIdentifierManager.php`

**Why:** L'utilisateur a explicitement signalé ce besoin lors de l'audit KLD pour éviter que
chaque service fabrique ses propres SHA256 avec des algorithmes potentiellement divergents.
