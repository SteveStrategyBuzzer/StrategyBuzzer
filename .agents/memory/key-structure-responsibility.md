---
name: KEY_STRUCTURE — responsabilité exacte
description: KEY_STRUCTURE = gardien de la qualité du matériau taxonomique. Valide + élague l'arbre produit par Taxonomy. Frontière avec QuestionIntent (ks_hash/encodage) et KLD.
---

> ⛔ **SUPERSEDED 2026-08-11** — KLD/KEY_STRUCTURE retirés du flow canonique ; responsabilités absorbées par ValidationDominantIdeas. Ne pas rebrancher. Voir [canonical-kernel-flow.md](canonical-kernel-flow.md). Conservé pour historique.


# KEY_STRUCTURE — responsabilité exacte

⚠️ MAJ 2026-06-19 (voir `rotation-par-noyau-complet.md`) : ORDRE CHANGÉ — **KEY_STRUCTURE intervient AVANT KLD**. Rôle DOUBLE et élargi : (6A) **ÉGRAINAGE INTELLECTUEL** — vérifie que la cascade Depth→Domaine→Sous-domaine→Sujet→Idée est NATURELLE et peut REFUSER même sans collision (Everest→Mort PASS ; Everest→Frontière FAIL) ; (6B) **PRÉ-CODE** `yy-xx-xxx-xxx-xxx-zz` (yy=Depth, xx=Domaine [Rotation] ; sous-domaine/sujet/idée [Taxonomy] ; zz=collision, `00` si aucune), détecte les collisions structurelles, compare les `zz`, et **appelle KLD** pour arbitrer (FAIL=même sujet+idée → avancer ; PASS=différent → `zz` différent). Le scope « garde qualité + élagage du matériau taxonomique » ci-dessous reste valable et s'ajoute.

KEY_STRUCTURE = **gardien de la qualité structurelle finale**. Il reçoit la **production de Taxonomy** (un arbre Sous-domaine → Sujets → 5 Idées Dominantes), la valide, et **élague** ce qui n'est pas conforme. Objectif : produire un **arbre propre et exploitable**, PAS reconstruire.

## Séparation des deux gardiens (architecture propre)
- **KLD (KEY_LEARNING_DIRECTION)** = qualité de la **direction pédagogique** (anti-répétition Sujet+Idée+contexte).
- **KEY_STRUCTURE** = qualité du **matériau taxonomique** (cohérence, égrainage, Depth, progression, qualité des 5 idées).

## Ce qu'il valide
- Cohérence taxonomique (sub_domain ∈ domain, subject ∈ sub_domain, idée ∈ subject)
- Égrainage / FORMAT_MINIMAL_IRREDUCTIBLE (chaque niveau = unité minimale, aucun niveau n'absorbe l'autre, pas de sous-domaine artificiel, idée ≠ phrase Phase 1)
- Respect du Depth (subject_profile du Sujet ↔ Depth ; knowledge_frequency de l'Idée ↔ Depth — lecture seule)
- Progression Domaine → Sous-domaine → Sujet
- Qualité des **5 Idées Dominantes**

## Ce qu'il fait / ne fait pas
- IL ÉLAGUE : supprime les Sujets et Idées Dominantes non conformes.
- IL NE RECONSTRUIT PAS : ne régénère pas, ne corrige pas, ne complète pas.
- PAS de ks_hash, PAS d'encodage (= QuestionIntent).
- PAS d'anti-doublon pédagogique (= KLD, déjà passé).
- Ne calcule pas knowledge_frequency (donnée taxonomy/DepthContract).

## Règle de sortie — DEUX CONTRÔLES SÉPARÉS (v4 VALIDÉE, seuils de capacité ouverts)
Portée : **1 passe KEY_STRUCTURE = 1 Sous-domaine**. Un FAIL ne rejette QUE ce Sous-domaine, jamais le batch/domaine entier.

KEY_STRUCTURE calcule 4 métriques par Sous-domaine :
- `capacité_attendue` — fournie par TAXONOMY, dépend de (Depth + Domaine + Sous-domaine). PAS un nombre fixe.
- `capacité_produite` — ce que Taxonomy a réellement sorti.
- `capacité_valide` — après élagage.
- `taux_élagage` = élagués / produits.

**Contrôle 1 — capacité structurelle (plancher, NON supprimé).** Regarde LES DEUX comparaisons (S2) :
- `capacité_produite` vs `capacité_attendue` → **déficit de PRODUCTION** (Taxonomy a-t-elle produit assez ?). Ex. attendue 50 / produite 20 / valide 20 = qualité propre mais pas assez produit.
- `capacité_valide` vs `capacité_attendue` → **déficit de QUALITÉ après élagage**. Ex. attendue 50 / produite 50 / valide 32 = assez produit mais mauvaise qualité.
Les deux déficits sont distincts et peuvent déclencher des recadrages Taxonomy différents.

**Contrôle 2 — taux d'élagage.** Bandes : <5% PASS · 5-25% PASS+signal recadrage · >25% FAIL+recadrage majeur. (Ex. 50 produits / 32 valides / 18 élagués = 36% → FAIL.)

**S1 (seuils de capacité) — OUVERTS, non figés.** Logique conservée sans chiffres définitifs : sous-production trop forte = recadrage Taxonomy ; sous-production critique = FAIL du Sous-domaine.

Cibles NON figées : Depth 2-7 → ≈50 ; Depth 8-10 → décroissance progressive (un Depth 10 peut légitimement valoir 22 ou 34). Ne jamais forcer 50 sur un sous-domaine ultra-spécialisé.

Phrase de contrat : « KEY_STRUCTURE ne juge pas seulement le taux d'élagage ; il juge aussi si Taxonomy a produit une quantité de matière cohérente avec la capacité structurelle attendue du Sous-domaine. »

## KEY_STRUCTURE_RECADRAGE_REPORT (VALIDÉ)
But : transformer un rejet en instruction exploitable pour Taxonomy. 1 rapport par Sous-domaine, émis si bande ≠ 🟢.
RÈGLE D'OR : KEY_STRUCTURE ne reconstruit JAMAIS et n'ordonne JAMAIS de reconstruction. Il donne un diagnostic actionnable ; Taxonomy reste le constructeur. → aucune action de type "REBUILD".

Champs (7, tous figés) : `severity`, `dominant_failure_reason`, `target_zone`, `taxonomy_action`, `examples`, `reason_distribution`, `secondary_actions[]`.
En-tête : domain, sub_domain, depth, metrics(4), deficits{production_deficit, quality_deficit}.

severity (vocabulaire FR conservé) : `RECADRAGE` (🟡 élagage 5-25% ou déficit prod modéré → PASS) | `RECADRAGE_MAJEUR` (🔴 élagage >25% ou déficit critique → FAIL).

Q1 tranché : action principale (dominant_failure_reason + taxonomy_action) = OBLIGATOIRE ; `secondary_actions[]` = optionnel mais RECOMMANDÉ si plusieurs dérives (Taxonomy corrige le principal d'abord, voit les secondaires pour ne pas répéter la dérive).

Q3 tranché — examples : max 5 total ; ≥3 pour la cause dominante si possible ; 1-2 secondaires seulement si utiles. Priorité à la cause dominante.

Catalogue reason → target_zone → taxonomy_action (canonique, aligné sur le BRIDGE) :
- SUBJECTS_TOO_GENERIC → SUBJECT → INCREASE_SPECIALIZATION
- SUBJECTS_TOO_SPECIFIC → SUB_DOMAIN → WIDEN_SUBDOMAIN
- FORMAT_NOT_MINIMAL → DOMINANT_IDEA → ENFORCE_MINIMAL_FORMAT
- DOMINANT_IDEAS_TOO_CLOSE_TO_SUBJECT → DOMINANT_IDEA → INCREASE_GRAINING_DISTANCE
- DOMINANT_IDEAS_TOO_SHALLOW → DOMINANT_IDEA → ALIGN_TO_DEPTH_EXPECTATION
- PRODUCTION_DEFICIT → SUB_DOMAIN → INCREASE_PRODUCTION_VOLUME
- STRUCTURAL_COLLAPSE → SUB_DOMAIN → RECENTER_SUBDOMAIN_CONSTRUCTION  (PAS de REBUILD)
Supprimés : INVALID_HIERARCHY/REATTACH_TO_PARENT (aucune règle KS ne les produit) ; DEPTH_PRECISION_MISMATCH (remplacé par DOMINANT_IDEAS_TOO_SHALLOW). QUALITY_DEFICIT = flag deficits{} uniquement ; KS-7 = BLOCK readiness (ni reason ni action).

## KEY_STRUCTURE_RULESET (VALIDÉ — base de validation/élagage)
But : KEY_STRUCTURE ne juge JAMAIS arbitrairement ; il applique ce ruleset. Chaque règle a un motif d'item.
- KS-1 Sujet = instance réelle du Sous-domaine (Capitales→Paris✓ ; "Ville européenne"/"Pays"✗). Motif SUBJECT_NOT_INSTANCE_OF_SUBDOMAIN.
- KS-2 Sujet MAL CONSTRUIT = déjà une affirmation/question déguisée ("Capitale de la France"✗). KS-1 = mauvais TYPE de sujet ; KS-2 = mauvaise CONSTRUCTION. Motif SUBJECT_NOT_MINIMAL.
- KS-3 Idée Dominante = axe minimal, court, stable, comparable (Centralisation✓ ; phrase✗). Motif DOMINANT_IDEA_NOT_MINIMAL.
- KS-4 Idée Dominante non absorbée par parent (Capitales/Paris/Idée="Capitale"✗). Motif DOMINANT_IDEA_ABSORBED_BY_PARENT.
- KS-5 Égrainage progressif : chaque niveau ajoute une précision réelle. Motif INSUFFICIENT_GRAINING.
- KS-6 Adéquation au Depth : plus le Depth monte, plus l'égrainage doit être précis. Motif INSUFFICIENT_GRAINING_FOR_DEPTH.
- KS-7 Sujet actif = EXACTEMENT 5 Idées Dominantes valides, sinon non prêt pour QUESTIONINTENT. Motif INSUFFICIENT_VALID_DOMINANT_IDEAS.
- KS-8 Capacité structurelle (Taxonomy fournit attendue+produite ; KS calcule valide+taux_élagage). Motifs PRODUCTION_DEFICIT / QUALITY_DEFICIT / STRUCTURAL_COLLAPSE.

## KEY_STRUCTURE_MOTIF_BRIDGE (VERROUILLÉ — map déterministe motif KS → reason → action)
Dernière pièce avant QUESTIONINTENT. Map 1↔1, chaîne entièrement traçable :
- SUBJECT_NOT_INSTANCE_OF_SUBDOMAIN (KS-1) → SUBJECTS_TOO_GENERIC → INCREASE_SPECIALIZATION
- SUBJECT_NOT_MINIMAL (KS-2) → SUBJECTS_TOO_SPECIFIC → WIDEN_SUBDOMAIN
- DOMINANT_IDEA_NOT_MINIMAL (KS-3) → FORMAT_NOT_MINIMAL → ENFORCE_MINIMAL_FORMAT
- DOMINANT_IDEA_ABSORBED_BY_PARENT (KS-4) → DOMINANT_IDEAS_TOO_CLOSE_TO_SUBJECT → INCREASE_GRAINING_DISTANCE
- INSUFFICIENT_GRAINING (KS-5) → DOMINANT_IDEAS_TOO_CLOSE_TO_SUBJECT → INCREASE_GRAINING_DISTANCE
- INSUFFICIENT_GRAINING_FOR_DEPTH (KS-6) → DOMINANT_IDEAS_TOO_SHALLOW → ALIGN_TO_DEPTH_EXPECTATION
- PRODUCTION_DEFICIT (KS-8) → PRODUCTION_DEFICIT → INCREASE_PRODUCTION_VOLUME
- STRUCTURAL_COLLAPSE (KS-8) → STRUCTURAL_COLLAPSE → RECENTER_SUBDOMAIN_CONSTRUCTION
NON mappés (volontaire) :
- INSUFFICIENT_VALID_DOMINANT_IDEAS (KS-7) → BLOCK readiness, AUCUNE action Taxonomy (déjà porté par KS-3..6).
- QUALITY_DEFICIT (KS-8) → FLAG deficits{} uniquement, jamais reason/action.
Chaîne verrouillée : RULESET → EVALUATOR → KS_DIAGNOSTICS → MOTIF_BRIDGE → RECADRAGE_REPORT → TAXONOMY_LEARNING_PROFILE.

## KEY_STRUCTURE_EVALUATOR (VALIDÉ — couche à implanter, n'existe pas en code)
Gouvernance : couche DÉFINIE maintenant, implantée plus tard. Chaîne officielle :
KEY_STRUCTURE_RULESET → KEY_STRUCTURE_EVALUATOR → KS_DIAGNOSTICS[] → KEY_STRUCTURE_RECADRAGE_REPORT → TAXONOMY_LEARNING_PROFILE.

Rôle : pur détecteur/normaliseur. Applique le RULESET, produit des motifs KS standardisés. NE produit PAS dominant_failure_reason ni taxonomy_action, n'écrit PAS le profil, ne reconstruit pas, n'élague pas lui-même (l'élagage = conséquence des REJECT). Portée 1 passe = 1 Sous-domaine. Déterministe, sans IA.

Entrée : { depth, domain, sub_domain, subjects_produced[{value, dominant_ideas[]}], capacité_attendue (fournie par Taxonomy, requise KS-8), ruleset }.

Sortie :
- ks_diagnostics[] : { level, subject?(si DOMINANT_IDEA), value, rule:"KS-x", motif, severity, also?[] }
- production_summary { capacité_attendue, capacité_produite, capacité_valide, taux_élagage }

level : SUB_DOMAIN | SUBJECT | DOMINANT_IDEA (aligné sur target_zone du report).
severity (niveau DIAGNOSTIC, distinct du report RECADRAGE/RECADRAGE_MAJEUR) :
- REJECT → élément élagué (KS-1..KS-6)
- BLOCK → Sujet non prêt QUESTIONINTENT (KS-7, <5 idées valides)
- DEFICIT → Sous-domaine, capacité (KS-8)

Priorisation multi-motifs sur un même élément → 1 motif PRIMAIRE (le plus fondamental) + reste dans also[] :
- Sujet : KS-1 (instance) → KS-2 (minimal)
- Idée : KS-3 (format) → KS-4 (absorption) → KS-5 (égrainage) → KS-6 (égrainage/Depth)

## AUDIT CHAÎNE — RÉSOLU (7 décisions verrouillées)
1. Action depth → `ALIGN_TO_DEPTH_EXPECTATION` (nom unique). `ALIGN_TO_DEPTH_PROFILE` supprimé.
2. KS-6 → reason canonique `DOMINANT_IDEAS_TOO_SHALLOW`, action `ALIGN_TO_DEPTH_EXPECTATION`. `DEPTH_PRECISION_MISMATCH` supprimé.
3. KS-1 = mauvais TYPE de Sujet (→ SUBJECTS_TOO_GENERIC) ; KS-2 = Sujet MAL CONSTRUIT/affirmation (→ SUBJECTS_TOO_SPECIFIC → WIDEN_SUBDOMAIN). Séparés.
4. KS-4 + KS-5 → DOMINANT_IDEAS_TOO_CLOSE_TO_SUBJECT → INCREASE_GRAINING_DISTANCE. Validé.
5. QUALITY_DEFICIT = flag deficits{} uniquement, jamais dominant_failure_reason. Validé.
6. KS-7 = état BLOCK (readiness), jamais une action Taxonomy. Validé.
7. Pont rendu explicite et figé → KEY_STRUCTURE_MOTIF_BRIDGE (section ci-dessus).

## Frontière verrouillée
```
KEY_STRUCTURE   = validation + élagage → arbre propre (PASS / FAIL)
QUESTIONINTENT  = verrouillage / encodage / ks_hash → PHASE 1 CRÉATION
```

**Why:** L'utilisateur a fixé KEY_STRUCTURE comme garde de la qualité du matériau taxonomique au niveau de l'arbre, avec droit d'élagage mais interdiction de reconstruire ; ks_hash explicitement retiré (= QuestionIntent). Confondre validation et reconstruction casserait la causalité Taxonomy → KEY_STRUCTURE → QuestionIntent.

**How to apply:** Entrée = production Taxonomy (arbre). Sortie = arbre élagué + PASS/FAIL selon seuil 20-25 Sujets/Sous-domaine. Détails encore à confirmer : nombre exact du seuil (20 vs 25 vs band), critères précis d'élagage Sujet vs Idée (un Sujet reste-t-il valide avec <5 idées ?), portée d'une passe (par domaine+depth ?), et si FAIL = drop du sous-domaine vs échec du batch entier.
