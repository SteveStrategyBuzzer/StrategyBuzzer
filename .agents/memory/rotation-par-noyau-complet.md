---
name: ROTATION PAR NOYAU COMPLET
description: Architecture officielle (VERROUILLAGE 2026-06-19) — Rotation = orchestrateur DÉTERMINISTE de progression des noyaux mères. Aucun hasard. KEY_STRUCTURE AVANT KLD. Source de vérité pour Rotation/Taxonomy/KEY_STRUCTURE/KLD.
---

# ROTATION PAR NOYAU COMPLET (VERROUILLÉ 2026-06-19)

Rotation n'est PAS un simple distributeur Depth/Domaine. C'est l'**orchestrateur de progression méthodique** des NOYAUX MÈRES. **Le hasard ne décide jamais** quels sujets/idées sont exploités.

## 1. Flux officiel
```
KERNEL BLUEPRINT
→ ROTATION PAR NOYAU COMPLET { BANK TARGET ; DEPTH NEED MATRIX ; DOMAIN CYCLE ; QUESTIONINTENT ; PROGRESSION DU NOYAU }
→ TAXONOMY
→ KEY_STRUCTURE
→ KLD
→ PHASE 1 → PHASE 2 → PHASE 3 → PHASE 4
→ READY_BANK
→ GAMEPLAY
```
⚠️ CHANGEMENT vs versions antérieures : **KEY_STRUCTURE intervient AVANT KLD** (auparavant la mémoire disait KLD→KEY_STRUCTURE). Voir §6-7.

## 2. Rôle réel de Rotation (déterministe)
Rotation détermine : quel Depth, quel Domaine, quel noyau actif, quel sous-domaine actif, quel sujet actif, quelle idée dominante active, QUAND avancer l'idée / le sujet / le sous-domaine. Rotation ne pige pas au hasard — elle fait progresser méthodiquement.

## 3. Logique de progression (par Depth)
Pour un Depth donné, Rotation traverse les Domaines, idée par idée :
```
Depth 2 : D1→sujet actif→Idée1, D2→sujet actif→Idée1, … D8→Idée1
puis      D1→même sujet→Idée2, … D8→Idée2   (puis Idée3, 4, 5)
```
- 5 idées dominantes du sujet actif exploitées → Rotation passe au **sujet suivant** du noyau ; Taxonomy génère les 5 idées du nouveau sujet.
- Tous les sujets du sous-domaine utilisés → **KLD signale sous-domaine épuisé** → Taxonomy change de sous-domaine pour ce domaine.
- Les autres domaines qui ont encore des sujets exploitables continuent leur progression.

## 4. Rôle de Taxonomy (fournisseur, pas décideur)
Fournit la matière : sous-domaines, jusqu'à 50 sujets, 5 idées dominantes du sujet actif. Taxonomy NE décide PAS la progression, NE choisit PAS quand avancer idée/sujet/sous-domaine. Elle répond aux besoins de Rotation.

## 5. Rôle de QUESTIONINTENT
Appelé DANS Rotation, quand Depth + Domaine sont inscrits. Encode l'identité STABLE du noyau mère. Ne choisit rien, ne valide rien, ne crée aucun contenu — encode seulement. But : que Taxonomy, KEY_STRUCTURE, KLD, Phases, Quarantaine, Ready_Bank, Gameplay travaillent toujours sur le MÊME noyau identifiable.

## 6. Rôle de KEY_STRUCTURE (AVANT KLD)
- Vérifie que les composantes nécessaires au code existent.
- Construit / vérifie le **pré-code**.
- Détecte les collisions structurelles.
- Compare les variantes `zz` existantes.
- Gère le différenciateur `zz` quand la collision est acceptée.

**Code conceptuel** : `yy-xx-xxx-xxx-xxx-zz`
- `yy` = Depth (de Rotation)
- `xx` = Domaine (de Rotation)
- `xxx` = Sous-domaine (de Taxonomy)
- `xxx` = Sujet actif (de Taxonomy)
- `xxx` = Idée dominante active (de Taxonomy)
- `zz` = variante de collision acceptée (de KEY_STRUCTURE/KLD après analyse)

Taxonomy ne génère PAS tout le code : seulement la matière + une partie du pré-code (sous-domaine, sujet, idée). Depth + Domaine viennent de Rotation ; `zz` vient de KEY_STRUCTURE/KLD après analyse de collision.

## 7. Rôle de KLD (arbitre des collisions intellectuelles)
KLD ne fait PAS la structure. KLD tranche les collisions. Quand KEY_STRUCTURE détecte un même pré-code `yy-xx-xxx-xxx-xxx`, il **appelle KLD**. KLD vérifie si c'est réellement même sujet + même idée :
- **Cas A — même sujet + même idée** → FAIL intellectuel → KLD demande la prochaine idée dispo du même sujet ; si 5 idées utilisées → prochain sujet ; si tous sujets utilisés → changement de sous-domaine.
- **Cas B — même pré-code mais sujet/idée réellement différent** → PASS → KEY_STRUCTURE attribue un `zz` différent (00, B4, C7, …).

## 8. Source de vérité interne (structure intellectuelle du noyau)
Contient : slot code `yy-xx-xxx-xxx-xxx-zz`, slot Depth, slot Domaine, slot Sous-domaine, 50 slots Sujets, 5 slots Idées dominantes, 7 cognitifs, Questions, Réponses, SV, Traductions, règles, mécanismes, contraintes, statuts, traces. Rotation/KernelRotationPlanner, Taxonomy, KEY_STRUCTURE, KLD et les Phases utilisent cette structure pour créer/vérifier/corriger/exploiter le noyau.

## 9. Objectif
Aucun sujet oublié, aucune idée dominante oubliée, progression équilibrée entre domaines, exploitation complète des noyaux, cohérence avec Gameplay/Ready_Bank/Quarantaine/Analytique.

## ÉCART AVEC LE CODE ACTUEL (audit 2026-06-19)
- **Sélection ALÉATOIRE, pas méthodique** : `BankWorker` utilise `->inRandomOrder()` (commentaire : « Pick randomly among candidates so the worker rotates across noyaux ») ; `QuestionBankPicker` utilise `mt_rand`. Contredit « Rotation ne pige pas au hasard ».
- **Pilotage par DÉFICIT, pas par progression** : `BankNeedsCalculator` trie par `deficit × depthWeight`. Aucun curseur Depth/Domaine/Sujet/Idée. Aucun état `sujet actif`/`idée active`.
- **Deux modèles de données incohérents** : le worker traite `domain = sub_domain` (8 thèmes plats, `general_sub_domains`) ; `taxonomy.json` a une vraie hiérarchie domaine→sous-domaine→sujet→idée.
- **taxonomy.json sous-dimensionné** : aujourd'hui 4 sous-domaines × 4 sujets × 5 idées par domaine — PAS « jusqu'à 50 sujets ».
- **Composants Rotation manquants** : KernelRotationPlanner, DepthNeedMatrix, DomainCycle, KEY_STRUCTURE, KLD, générateur de pré-code `yy-xx-xxx-xxx-xxx-zz`, différenciateur `zz` — AUCUN n'existe (commentaires seulement).
- **QUESTIONINTENT** : modèle+table existent mais créés tardivement (Phase0/dialyse), pas « encodés pendant la progression Rotation ».
- **7 cognitifs vs 5 variantes** : la spec dit 7 cognitifs ; le code `QuestionIntent::targetVariants()` cible 5 variantes. Divergence non tranchée.

**Why:** capturer fidèlement la cible (déterministe, par noyau complet) ET l'état réel (aléatoire, déficit-driven) pour éviter de re-confondre les deux lors de l'implémentation.
