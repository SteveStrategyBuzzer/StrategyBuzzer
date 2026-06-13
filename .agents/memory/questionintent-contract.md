---
name: QUESTIONINTENT contract (official)
description: Contrat verrouillé de QUESTIONINTENT — pose des puces persistantes APRÈS KEY_STRUCTURE PASS. Fan-out Option A (1 idée = 1 noyau). 3 ceintures de garde + ks_hash + transmission Phase 1.
---

# QUESTIONINTENT — contrat officiel (VERROUILLÉ)

Position : `… → KEY_STRUCTURE (PASS) → QUESTIONINTENT → Phase 1 (7 cognitifs)`.
Causalité verrouillée (sens unique) : KEY_STRUCTURE autorise → QUESTIONINTENT pose la puce → Phase 1 remplit. INTERDIT : QUESTIONINTENT dépend de lui-même ; KEY_STRUCTURE dépend de QUESTIONINTENT.

## Rôle
Pose la PUCE persistante (question_intent) APRÈS autorisation de KEY_STRUCTURE. Ne valide rien, ne choisit rien, ne crée aucune question. Il SCELLE le noyau validé en identité persistante pour rattachement/dissociation (Phase 1, traductions, quarantaine, Ready_Bank, gameplay).

## Comportement OFFICIEL — Option A FAN-OUT (verrouillé)
KEY_STRUCTURE valide les 5 Idées Dominantes EN LOT. QUESTIONINTENT les ÉCLATE en 5 noyaux indépendants.
- 1 Sujet actif validé (= exactement 5 Idées Dominantes valides) → QUESTIONINTENT produit **5 question_intents distincts**.
- 1 noyau = 1 Sujet + 1 Idée Dominante (jamais 1 puce portant 5 idées).
- Chaque question_intent reçoit son PROPRE ks_hash.
- Comptage : 1 Sujet × 5 Idées Dominantes × 7 cognitifs = 35 créations potentielles.
- Phase 1 travaille TOUJOURS sur 1 noyau = 1 Sujet + 1 Idée Dominante.

## 3 ceintures de garde
**A — AMONT** (pré-conditions ; si UNE échoue → AUCUNE puce posée) :
- KLD = PASS · KEY_STRUCTURE = PASS · arbre nettoyé (élagage appliqué, aucun REJECT résiduel) · aucun recadrage majeur actif (pas de RECADRAGE_MAJEUR en attente sur le sous-domaine).

**B — PAR EMPLACEMENT** (chaque slot du noyau conforme) :
- Depth valide · Domaine valide · Sous-domaine valide · Sujet actif valide · exactement 5 Idées Dominantes valides · format minimal respecté · ordre logique respecté.

**C — AVAL** (readiness Phase 1) :
- noyau prêt pour Phase 1 · tous emplacements requis existent · chaque emplacement respecte son rôle · aucun champ vide · aucun élément hors structure.

→ readiness = READY seulement si A + B + C tous PASS.

## Ce que chaque puce encode (aligné flow verrouillé)
domain, sub_domain, subject, idee_dominante, difficulty_depth, knowledge_frequency, ks_hash, kld_hash, source='rotation', frame_status=NULL.

## ks_hash
Calculé et POSSÉDÉ par QUESTIONINTENT, JAMAIS par KEY_STRUCTURE. Hash déterministe normalisé de (Depth + Domaine + Sous-domaine + Sujet + Idée Dominante). C'est le sceau : identité + dedup persistante de la puce. Distinct par puce (donc 5 ks_hash pour un Sujet à 5 idées).

## Transmission Phase 1
Chaque puce → Phase 1 génère les 7 cognitifs (variant_keys) :
qcm_recognition, qcm_reasoning, qcm_deceptive_trap, tf_recognition_true, tf_recognition_false, tf_reasoning_true, tf_reasoning_false.
Contrainte UNIQUE(question_intent_id, variant_key). Phase 1 ne modifie JAMAIS key/subject/idee_dominante.

**Why:** Option A préserve la définition de noyau déjà figée (1 idée = 1 noyau, Ready_Bank growth spec) tout en gardant la validation en lot de KEY_STRUCTURE (5 idées). Évite de casser UNIQUE(question_intent_id, variant_key) et la séparation des responsabilités.
