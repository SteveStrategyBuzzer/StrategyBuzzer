---
name: knowledge_frequency vs historique des paires
description: Two distinct kernel-pipeline responsibilities that must never be merged — notoriety scoring vs pedagogical anti-duplication.
---

> ⚠️ **Partiellement SUPERSEDED 2026-08-11** — toute mention de KLD/KEY_STRUCTURE dans ce fichier est obsolète (absorbés par ValidationDominantIdeas). Voir [canonical-kernel-flow.md](canonical-kernel-flow.md).


# Séparation verrouillée : knowledge_frequency ≠ historique des paires

Deux responsabilités distinctes du pipeline noyau. Ne JAMAIS les fusionner.

## 1. `knowledge_frequency` — appartient au `DepthContract`
- Rôle : mesurer la **notoriété du savoir** porté par l'Idée Dominante.
- Ex : Sujet=Paris, Idée Dominante=Capitale → knowledge_frequency très élevée (lien très connu).
- **N'est PAS un détecteur de doublons.** Sert uniquement à cadrer le niveau de notoriété attendu selon le Depth.

## 2. Historique des paires exploitées — appartient à `KEY_LEARNING_DIRECTION`
- Rôle : **anti-doublon pédagogique** — éviter de réutiliser une même direction d'apprentissage.
- Doit vérifier :
  - Sujet + Idée Dominante
  - Idée Dominante + Sujet (ordre inversé)
  - collision conceptuelle évidente
  - paire déjà utilisée OU trop proche d'une paire existante
- Ex : Noyau A (Paris/Capitale) vs Noyau B (Capitale/Paris) → FAIL même inversé (risque doublon en rendu trop élevé).

**Why:** L'utilisateur a explicitement corrigé une confusion : utiliser knowledge_frequency pour détecter des doublons est faux. La notoriété (DepthContract) et l'anti-doublon (KEY_LEARNING_DIRECTION) sont deux couches indépendantes.

**How to apply:** Quand on implémente/modifie KEY_LEARNING_DIRECTION, l'historique des paires y vit. knowledge_frequency reste dans DepthContract et ne participe jamais au dedup. Inversement, l'historique des paires ne participe jamais au cadrage de notoriété.
