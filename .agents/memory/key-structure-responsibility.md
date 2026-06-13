---
name: KEY_STRUCTURE — responsabilité exacte
description: KEY_STRUCTURE = gardien de la qualité du matériau taxonomique. Valide + élague l'arbre produit par Taxonomy. Frontière avec QuestionIntent (ks_hash/encodage) et KLD.
---

# KEY_STRUCTURE — responsabilité exacte

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

Catalogue reason → target_zone → taxonomy_action :
- SUBJECTS_TOO_GENERIC → SUBJECT → INCREASE_SPECIALIZATION
- SUBJECTS_TOO_SPECIFIC → SUB_DOMAIN → WIDEN_SUBDOMAIN
- DOMINANT_IDEAS_TOO_CLOSE_TO_SUBJECT → DOMINANT_IDEA → INCREASE_GRAINING_DISTANCE
- DOMINANT_IDEAS_TOO_SHALLOW → DOMINANT_IDEA → INCREASE_DEPTH_ALIGNMENT
- FORMAT_NOT_MINIMAL → niveau fautif → ENFORCE_MINIMAL_FORMAT
- INVALID_HIERARCHY → SUBJECT/DOMINANT_IDEA → REATTACH_TO_PARENT
- DEPTH_PRECISION_MISMATCH → SUBJECT/DOMINANT_IDEA → ALIGN_TO_DEPTH_PROFILE
- PRODUCTION_DEFICIT → SUB_DOMAIN → INCREASE_PRODUCTION_VOLUME
- STRUCTURAL_COLLAPSE → SUB_DOMAIN → RECENTER_SUBDOMAIN_CONSTRUCTION  (PAS de REBUILD)

## Frontière verrouillée
```
KEY_STRUCTURE   = validation + élagage → arbre propre (PASS / FAIL)
QUESTIONINTENT  = verrouillage / encodage / ks_hash → PHASE 1 CRÉATION
```

**Why:** L'utilisateur a fixé KEY_STRUCTURE comme garde de la qualité du matériau taxonomique au niveau de l'arbre, avec droit d'élagage mais interdiction de reconstruire ; ks_hash explicitement retiré (= QuestionIntent). Confondre validation et reconstruction casserait la causalité Taxonomy → KEY_STRUCTURE → QuestionIntent.

**How to apply:** Entrée = production Taxonomy (arbre). Sortie = arbre élagué + PASS/FAIL selon seuil 20-25 Sujets/Sous-domaine. Détails encore à confirmer : nombre exact du seuil (20 vs 25 vs band), critères précis d'élagage Sujet vs Idée (un Sujet reste-t-il valide avec <5 idées ?), portée d'une passe (par domaine+depth ?), et si FAIL = drop du sous-domaine vs échec du batch entier.
