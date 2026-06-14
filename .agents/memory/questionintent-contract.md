---
name: QUESTIONINTENT contract (official)
description: Contrat verrouillé de QUESTIONINTENT — PUR ENCODEUR (zéro contrôle métier, pas de ruleset). Reçoit un noyau déjà validé, produit son encodage officiel. Fan-out Option A (1 idée = 1 noyau).
---

# QUESTIONINTENT — contrat officiel (VERROUILLÉ)

Position : `… → KEY_STRUCTURE (PASS) → QUESTIONINTENT → Phase 1 (7 cognitifs)`.
Causalité verrouillée (sens unique) : KEY_STRUCTURE autorise → QUESTIONINTENT encode/pose la puce → Phase 1 remplit. INTERDIT : QUESTIONINTENT dépend de lui-même ; KEY_STRUCTURE dépend de QUESTIONINTENT.

## Rôle OFFICIEL — PUR ENCODEUR DU FICHIER NOYAU COMPLET
QUESTIONINTENT est UNIQUEMENT un encodeur. Il REÇOIT un noyau DÉJÀ VALIDÉ et encode le FICHIER NOYAU COMPLET (le conteneur entier, slots VIDES inclus) pour : PHASE 1, gameplay, retour des corrections, traçabilité.

INTERDICTIONS (gouvernance) :
- PAS de QUESTIONINTENT_RULESET. Cette couche ne doit pas exister.
- QUESTIONINTENT ne valide pas, ne compare pas, ne filtre pas, ne bloque pas, ne juge pas les Idées Dominantes.
- AUCUN contrôle métier.

Toutes les règles appartiennent À L'AMONT et nulle part ailleurs :
KLD → KEY_STRUCTURE_RULESET → KEY_STRUCTURE_EVALUATOR → KEY_STRUCTURE_MOTIF_BRIDGE → KEY_STRUCTURE_RECADRAGE_REPORT.
Quand le noyau arrive à QUESTIONINTENT, il est DÉJÀ propre/validé (KEY_STRUCTURE PASS). QUESTIONINTENT NE re-vérifie RIEN (pas de garde amont/emplacement/aval de son côté — ces conditions sont garanties par l'amont, jamais contrôlées ici).

## Comportement OFFICIEL — Option A FAN-OUT (verrouillé)
Éclatement mécanique (encodage, pas un jugement) :
- KEY_STRUCTURE valide les 5 Idées Dominantes EN LOT. QUESTIONINTENT les ÉCLATE en 5 noyaux indépendants.
- 1 Sujet actif validé (5 Idées Dominantes) → QUESTIONINTENT produit **5 question_intents distincts**.
- 1 noyau = 1 Sujet + 1 Idée Dominante (jamais 1 puce portant 5 idées).
- Chaque question_intent reçoit son PROPRE encodage (id, ks_hash, kernel_print).
- Comptage : 1 Sujet × 5 Idées Dominantes × 7 cognitifs = 35 créations potentielles.
- Phase 1 travaille TOUJOURS sur 1 noyau = 1 Sujet + 1 Idée Dominante.

## Ce que QUESTIONINTENT GÉNÈRE (par puce)
- question_intent_id
- ks_hash
- kernel_print
- status = CREATION_READY

Plus la charge du noyau reçue telle quelle (aligné flow verrouillé) : domain, sub_domain, subject, idee_dominante, difficulty_depth, knowledge_frequency, kld_hash, source='rotation', frame_status=NULL.

## ks_hash
Calculé et POSSÉDÉ par QUESTIONINTENT, JAMAIS par KEY_STRUCTURE. Hash déterministe normalisé de (Depth + Domaine + Sous-domaine + Sujet + Idée Dominante). Sceau : identité + dedup persistante. Distinct par puce (5 ks_hash pour un Sujet à 5 idées).

## kernel_print
Empreinte/encodage canonique normalisé du noyau (forme officielle sérialisée) servant la traçabilité et le retour des corrections. Produit par l'encodeur, pas un contrôle.

## Le FICHIER NOYAU COMPLET encodé par QUESTIONINTENT
QUESTIONINTENT encode le noyau comme un FICHIER/conteneur complet. À l'encodage, les slots de contenu sont VIDES (ils seront remplis/vérifiés par les Phases). Contenu du noyau :
- identité structurelle (question_intent_id, ks_hash, kernel_print, depth, domain, sub_domain, subject, dominant_idea, kld_hash, knowledge_frequency, source, status=CREATION_READY)
- règles (les règles applicables au noyau)
- mécanismes (mécaniques de jeu/génération)
- slots Questions
- slots Réponses
- slots Saviez-vous
- slots Traductions
QUESTIONINTENT ne remplit AUCUN slot (pur encodeur) ; il pose le conteneur prêt à travailler.

## Phases de travail/vérification SUR le noyau (remplissage + contrôle)
Après l'encodage, ce ne sont plus des décisions sur le noyau mais des PHASES de travail + vérification qui remplissent et valident les slots, avec des "opens" pour les slots NON CONFORMES et une traçabilité pour le retour des prints corrigés :
- PHASE 1 : création (remplit les slots Questions / Réponses / Saviez-vous — les 7 cognitifs).
- PHASE 2 : vérification de PHASE 1 — traçabilité ; slot non conforme → "Quarantaine" → vérification humaine → corrections (prints corrigés réinjectés).
- PHASE 3 : traduction (remplit les slots Traductions).
- PHASE 4 : vérification de PHASE 3 — traçabilité ; non conforme → même boucle Quarantaine → vérification humaine → corrections.
- Sortie finale conforme → stockage dans READY_BANK.
Boucle de correction (aligne Phase 6 Quarantaine + Phase 7 Correction du flow officiel) : WARNING/non-conforme → Quarantaine → humain → correction → re-vérification. Le partiel reste accepté (D5) ; un WARNING ne bloque pas les slots VALIDATED_OK.

## Transmission Phase 1
Chaque puce (status=CREATION_READY) → Phase 1 génère les 7 cognitifs (variant_keys) :
qcm_recognition, qcm_reasoning, qcm_deceptive_trap, tf_recognition_true, tf_recognition_false, tf_reasoning_true, tf_reasoning_false.
Contrainte UNIQUE(question_intent_id, variant_key). Phase 1 ne modifie JAMAIS key/subject/idee_dominante.

**Why:** QUESTIONINTENT est strictement un encodeur : séparation des responsabilités stricte (toute la logique de validation est en amont — KLD + chaîne KEY_STRUCTURE). Option A (fan-out) préserve la définition de noyau déjà figée (1 idée = 1 noyau) et UNIQUE(question_intent_id, variant_key).
