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

## Règle de sortie — modèle TAUX DE REJET + CIBLE PAR DEPTH (officiel, seuils exacts TBD)
KEY_STRUCTURE mesure la QUALITÉ DE PRODUCTION Taxonomy, pas un PASS binaire mou.

Cible « noyau plein » par Depth (le volume attendu DÉCROÎT quand Depth augmente) :
- Depth 2-7 → ≈ 50 Sujets
- Depth 8 → ≈ 40 Sujets
- Depth 9 → ≈ 35 Sujets
- Depth 10 → ≈ 30 Sujets

Bandes de taux de rejet (rejetés / produits), mêmes bandes quel que soit le Depth :
- < 5 % rejeté → NORMAL → PASS
- 5 %–25 % rejeté → PASS mais signal RECADRAGE à Taxonomy (dérive légère, noyau encore exploitable)
- > 25 % rejeté → PROBLÈME MAJEUR → **FAIL le noyau** + recadrage MAJEUR de Taxonomy

KEY_STRUCTURE ne juge plus le volume brut aux Depth élevés : il juge cohérence + égrainage + qualité des Sujets + qualité des 5 Idées. Le minimum acceptable devient progressif selon le Depth.

## Frontière verrouillée
```
KEY_STRUCTURE   = validation + élagage → arbre propre (PASS / FAIL)
QUESTIONINTENT  = verrouillage / encodage / ks_hash → PHASE 1 CRÉATION
```

**Why:** L'utilisateur a fixé KEY_STRUCTURE comme garde de la qualité du matériau taxonomique au niveau de l'arbre, avec droit d'élagage mais interdiction de reconstruire ; ks_hash explicitement retiré (= QuestionIntent). Confondre validation et reconstruction casserait la causalité Taxonomy → KEY_STRUCTURE → QuestionIntent.

**How to apply:** Entrée = production Taxonomy (arbre). Sortie = arbre élagué + PASS/FAIL selon seuil 20-25 Sujets/Sous-domaine. Détails encore à confirmer : nombre exact du seuil (20 vs 25 vs band), critères précis d'élagage Sujet vs Idée (un Sujet reste-t-il valide avec <5 idées ?), portée d'une passe (par domaine+depth ?), et si FAIL = drop du sous-domaine vs échec du batch entier.
