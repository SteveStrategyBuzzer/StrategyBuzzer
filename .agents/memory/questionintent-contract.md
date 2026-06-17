---
name: QUESTIONINTENT contract (official)
description: Contrat verrouillé de QUESTIONINTENT — PUR ENCODEUR (zéro contrôle métier, pas de ruleset). Reçoit le NOYAU MÈRE complet déjà validé et produit son encodage officiel. Le NOYAU MÈRE est UNE entité complète indivisible — pas de fan-out, pas de puces, pas de découpage sujet/idée.
---

# QUESTIONINTENT — contrat officiel (VERROUILLÉ)

Position : `… → KEY_STRUCTURE (PASS) → QUESTIONINTENT → Phase 1 (7 cognitifs)`.
Causalité verrouillée (sens unique) : KEY_STRUCTURE autorise → QUESTIONINTENT encode le NOYAU MÈRE complet → Phase 1 remplit. INTERDIT : QUESTIONINTENT dépend de lui-même ; KEY_STRUCTURE dépend de QUESTIONINTENT.

## Rôle OFFICIEL — PUR ENCODEUR DU FICHIER NOYAU COMPLET
QUESTIONINTENT est UNIQUEMENT un encodeur. Il REÇOIT un noyau DÉJÀ VALIDÉ et encode le FICHIER NOYAU COMPLET (le conteneur entier, slots VIDES inclus) pour : PHASE 1, gameplay, retour des corrections, traçabilité.

INTERDICTIONS (gouvernance) :
- PAS de QUESTIONINTENT_RULESET. Cette couche ne doit pas exister.
- QUESTIONINTENT ne valide pas, ne compare pas, ne filtre pas, ne bloque pas, ne juge pas les Idées Dominantes.
- AUCUN contrôle métier.

Toutes les règles appartiennent À L'AMONT et nulle part ailleurs :
KLD → KEY_STRUCTURE_RULESET → KEY_STRUCTURE_EVALUATOR → KEY_STRUCTURE_MOTIF_BRIDGE → KEY_STRUCTURE_RECADRAGE_REPORT.
Quand le noyau arrive à QUESTIONINTENT, il est DÉJÀ propre/validé (KEY_STRUCTURE PASS). QUESTIONINTENT NE re-vérifie RIEN (pas de garde amont/emplacement/aval de son côté — ces conditions sont garanties par l'amont, jamais contrôlées ici).

## PRINCIPE DE RESPONSABILITÉ — facilitateur passif (VERROUILLÉ 2026-06-16)
QUESTIONINTENT n'est **pas un acteur** du système. C'est un **facilitateur passif** qui rend le NOYAU MÈRE reconnaissable, retrouvable et manipulable par le reste de l'écosystème.

CE QU'IL FAIT (✓) : encode · identifie · rend traçable · rend clonable · rend remplaçable · rend référençable.
CE QU'IL NE FAIT PAS (✗) : ne calcule pas · n'analyse pas · ne détecte pas · ne décide pas · ne produit pas de statistiques · ne corrige pas · ne valide pas.

Formule officielle : `QUESTIONINTENT → crée une identité exploitable` (et **jamais** `QUESTIONINTENT → exploite lui-même cette identité`).

### Ce que l'encodage PERMET (à d'AUTRES composants — pas à QUESTIONINTENT)
- **Gameplay** : identifier les questions jouées ; éviter les répétitions ; retrouver l'historique d'un joueur ; savoir quel noyau a été utilisé.
- **Quarantaine** : retrouver le noyau concerné ; cloner le bon noyau ; réinjecter les corrections ; remplacer les WARNING.
- **Ready_Bank** : référencer les noyaux ; maintenir une source de vérité stable ; mettre à jour un noyau sans le recréer.
- **Analytique** : produire des statistiques ; mesurer la fréquence d'utilisation ; détecter des tendances ; identifier les noyaux problématiques ; alimenter l'amélioration continue.

Non-contradiction clé : « ✗ ne produit pas de statistiques » (QUESTIONINTENT) et « Analytique → produire des statistiques » coexistent car l'encodage rend la mesure POSSIBLE ; c'est un AUTRE composant qui mesure. Idem pour cloner/corriger/référencer : QUESTIONINTENT rend l'action possible, Quarantaine/Ready_Bank l'exécutent.

## Comportement OFFICIEL — encodage d'UNE entité complète (VERROUILLÉ 2026-06-16 — REMPLACE l'ancien « fan-out »)
CORRECTION MAJEURE : l'ancien modèle « fan-out / 5 puces / 1 idée = 1 noyau » est ABANDONNÉ. Le NOYAU MÈRE est UNE entité complète et indivisible :
- il n'existe PAS plusieurs noyaux dérivés ;
- il n'existe PAS de puces indépendantes ;
- il n'existe PAS de découpage sujet/idée.
QUESTIONINTENT encode le NOYAU MÈRE COMPLET comme UNE seule identité (un seul objet encodé), pas 5. Les subjects[1..50] et dominant_ideas[1..5] sont la STRUCTURE INTERNE du noyau, jamais des objets séparés.
Phase 1 travaille sur le noyau mère complet (tous ses cognitifs internes), pas sur une puce.

## Ce que QUESTIONINTENT GÉNÈRE (pour le NOYAU MÈRE)
- question_intent_id
- ks_hash
- kernel_print
- status = CREATION_READY

Plus la charge du noyau reçue telle quelle (aligné flow verrouillé) : depth, domain, sub_domain, subjects[1..50]×dominant_ideas[1..5], knowledge_frequency, kld_hash, source='rotation', frame_status=NULL.

## ks_hash
Calculé et POSSÉDÉ par QUESTIONINTENT, JAMAIS par KEY_STRUCTURE. Identifie le NOYAU MÈRE complet : hash déterministe normalisé de (Depth + Domaine + Sous-domaine). Sceau : identité + dedup persistante du noyau (UN ks_hash par noyau mère, plus de « 5 ks_hash »). ⚠️ OUVERT (décision utilisateur) : la granularité d'identité INTERNE (sujet/idée/cognitif) dont le gameplay a besoin pour « éviter les répétitions » et savoir quelle question précise a été jouée — voir note de réconciliation en bas.

## kernel_print
Empreinte/encodage canonique normalisé du noyau (forme officielle sérialisée) servant la traçabilité et le retour des corrections. Produit par l'encodeur, pas un contrôle.

## Le FICHIER NOYAU COMPLET encodé par QUESTIONINTENT
QUESTIONINTENT encode le noyau comme un FICHIER/conteneur complet. À l'encodage, les slots de contenu sont VIDES (ils seront remplis/vérifiés par les Phases). Contenu du noyau :
- identité structurelle (question_intent_id, ks_hash, kernel_print, depth, domain, sub_domain, subjects[1..50]×dominant_ideas[1..5], kld_hash, knowledge_frequency, source, status=CREATION_READY)
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

## QUARANTAINE — règle officielle (copie du NOYAU MÈRE, pas du slot)
CORRECTION CLÉ : la quarantaine ne reçoit JAMAIS une copie isolée d'un slot. Elle reçoit une COPIE COMPLÈTE du noyau mère, avec indication explicite des slots WARNING à corriger.
POURQUOI : un slot WARNING dépend du contexte complet du noyau (identité structurelle, sujet, idée dominante, autres cognitifs, réponses, Saviez-vous, traductions, trace des validations). La correction doit donc se faire dans un clone complet, jamais dans un slot isolé.

Deux entités officielles :
- NOYAU_MERE : référence officielle. Contient les slots VALIDATED_OK ET WARNING. SEULS les slots VALIDATED_OK sont utilisables en gameplay.
- QUARANTAINE_KERNEL_COPY : copie complète du NOYAU_MERE. Contient `warning_slots[]`. Sert à corriger les slots ouverts. Conserve la traçabilité.

Cycle par slot :
- Slot OK → VALIDATED_OK → slot fermé → READY_BANK → gameplay utilisable.
- Slot WARNING → slot ouvert → NON utilisable gameplay → copie complète vers QUARANTAINE_KERNEL_COPY (+ liste warning_slots) → correction dans le clone → revalidation :
  - si VALIDATED_OK : les slots corrigés REMPLACENT les slots WARNING correspondants DANS le NOYAU_MERE → slots fermés → READY_BANK mis à jour → gameplay autorisé.
  - si encore WARNING : NOYAU_MERE inchangé → slots restent ouverts → gameplay ne les utilise pas.
Règle : la quarantaine travaille sur un clone complet, mais ne remplace dans le noyau mère QUE les slots corrigés ET revalidés.

## STATUTS DE SLOT — vivent DANS le noyau, pas dans READY_BANK
Distinction officielle : le NOYAU porte les STATUTS ; READY_BANK porte les NOYAUX. Un statut (EMPTY / VALIDATED_OK / WARNING) est un ATTRIBUT d'un slot du noyau, jamais un élément de READY_BANK.
Exemple : NOYAU_MERE { slot A=VALIDATED_OK, slot B=VALIDATED_OK, slot C=WARNING, slot D=VALIDATED_OK, slot E=WARNING }.
Définitions :
- EMPTY = slot non rempli (selon les règles autorisées, ex. noyau partiel D5).
- VALIDATED_OK = slot conforme ; utilisable par les phases aval ; utilisable gameplay si applicable.
- WARNING = slot non conforme ; bloqué pour les phases aval ; bloqué gameplay ; ouvert à correction via Quarantaine.
READY_BANK : ne contient PAS de "WARNING" comme entrée. Il contient seulement des noyaux exploitables ; à l'intérieur d'un noyau, certains slots peuvent rester WARNING — ils sont simplement IGNORÉS par gameplay et par traduction jusqu'à correction. READY_BANK ne porte pas les statuts lui-même : c'est le noyau qui les porte.

## RÈGLE OFFICIELLE — WARNING création bloque AUSSI la traduction
Un slot création en WARNING est triplement qualifié :
- WARNING création = NON utilisable gameplay
- WARNING création = NON traduisible (PHASE 3 l'ignore totalement)
- WARNING création = ouvert pour QUARANTAINE (correction requise)
Quand PHASE 2 met un slot création en WARNING : le slot RESTE dans le NOYAU_MERE (le noyau continue d'exister), gameplay l'ignore, ET PHASE 3 l'ignore. La traduction ne fait RIEN sur un slot création WARNING.
PRÉ-REQUIS PHASE 3 : un slot création doit être VALIDATED_OK par PHASE 2 AVANT que PHASE 3 puisse le traduire. PHASE 3 ne travaille QUE sur du contenu création déjà validé.
Flux propre : PHASE 2 détecte WARNING → slot reste dans NOYAU_MERE (ni gameplay ni traduction) → QUARANTAINE corrige → retour PHASE 2 → si VALIDATED_OK → PHASE 3 autorisée à traduire → PHASE 4 vérifie traduction → READY_BANK / gameplay.
Récap : WARNING création = non gameplay + non traduction + correction requise ; VALIDATED_OK création = gameplay possible + traduction possible.

## Transmission Phase 1
Le NOYAU MÈRE (status=CREATION_READY) → Phase 1 génère, pour chaque (subject × idée dominante) INTERNE, les 7 cognitifs (variant_keys) :
qcm_recognition, qcm_reasoning, qcm_deceptive_trap, tf_recognition_true, tf_recognition_false, tf_reasoning_true, tf_reasoning_false.
Phase 1 ne modifie JAMAIS la structure (subjects/idées/cognitifs). ⚠️ L'unicité historique UNIQUE(question_intent_id, variant_key) supposait 1 puce = 1 (subject,idée) ; avec le noyau-entité elle doit devenir UNIQUE(noyau, subject, idée, variant_key) — à confirmer.

**Why:** QUESTIONINTENT est strictement un encodeur : séparation des responsabilités stricte (toute la logique de validation est en amont — KLD + chaîne KEY_STRUCTURE). CORRECTION 2026-06-16 : le NOYAU MÈRE est UNE entité complète indivisible (pas de fan-out, pas de puces, pas de découpage sujet/idée) ; l'ancien « Option A fan-out » est ABANDONNÉ.

## NOYAU MÈRE = entité complète (correctif 2026-06-16, voir noyau-mere-structure.md)
Le NOYAU MÈRE est UNE entité complète indivisible. Il n'existe PAS de « puce », PAS de noyau dérivé, PAS de découpage sujet/idée. Les subjects[1..50] × dominant_ideas[1..5] (+ 7 cognitifs × 4 slots, rules/mechanisms/constraints, statuts, traces) sont la STRUCTURE INTERNE d'un SEUL noyau. QUESTIONINTENT l'encode comme un seul objet. La « copie complète du NOYAU_MERE » en Quarantaine = clone de ce noyau complet.

CONSÉQUENCES :
1. ✅ **RÉSOLU (2026-06-16)** — Identité/dedup gameplay : l'unité CONSOMMÉE par le gameplay est le **COGNITIF** (le noyau reste l'unité centrale). Le suivi anti-répétition se fait par **(joueur × noyau × cognitif/famille)**. Détail complet : gameplay-consumption-model.md.
2. ⚠️ **DIFFÉRÉ (analyse plus tard)** — Unité de comptage Ready_Bank : ready-bank-growth-spec dit « Unité = noyau (7 cognitifs) » et vise 685K noyaux / 4.8M questions — ce calcul supposait noyau = 1 (sujet,idée). La taille effective dépend désormais du nombre de cognitifs exploitables par noyau (voir « Point à analyser plus tard » dans gameplay-consumption-model.md). À recalibrer.
3. ⚠️ **À CONFIRMER** — Clé d'unicité : UNIQUE(question_intent_id, variant_key) (cf. kernel-pipeline-architecture.md) doit probablement devenir UNIQUE(noyau, subject, idée, variant_key=cognitif).
