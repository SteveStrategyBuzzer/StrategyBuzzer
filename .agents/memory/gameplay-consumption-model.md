---
name: Gameplay Consumption Model (VERROUILLÉ)
description: Comment le Gameplay consomme un NOYAU MÈRE — l'unité consommée est le COGNITIF, pas le noyau entier. Familles (Recognition/Reasoning/Deceptive Trap), suivi joueur, classement noyaux (vierge/exploitable/back_support), ordre de sélection.
---

# GAMEPLAY CONSUMPTION MODEL (VERROUILLÉ 2026-06-16)

## Règle centrale
- Le Gameplay ne consomme PAS un NOYAU MÈRE complet d'un coup.
- Le Gameplay consomme **1 ou plusieurs cognitifs** d'un NOYAU MÈRE X.
- **NOYAU MÈRE = unité centrale ; COGNITIF = unité consommée.**
- (Répond au point OUVERT n°1 du contrat QUESTIONINTENT : l'identité/dedup gameplay se suit au niveau **(joueur × noyau × cognitif/famille)**.)

## 1. Demande Gameplay → READY_BANK
Le Gameplay demande : **Domaine X + Depth Y**.
READY_BANK retourne **tous les NOYAUX MÈRES ENCODÉS** de ce Depth + Domaine → disponibles pour sélection.

## 2. Familles cognitives & expérience minimale par noyau
3 familles (les 7 cognitifs s'y répartissent) :
- **Recognition** = QCM Recognition **OU** TF Recognition True/False
- **Reasoning** = QCM Reasoning **OU** TF Reasoning True/False
- **Deceptive Trap** = QCM Deceptive Trap

Cible minimale par noyau : **Recognition + Reasoning + Deceptive Trap** (ouvrir au minimum ces 3 familles au joueur).

## 3. Décalage par partie
Le Gameplay ne redonne pas toujours la même forme ; il décale d'une partie à l'autre.
Ex. Partie A : QCM Recognition / TF Reasoning True / Deceptive Trap. Partie B : TF Recognition False / QCM Reasoning / Deceptive Trap.
Le joueur PEUT éventuellement parcourir les 7 cognitifs d'un noyau, mais on l'évite autant que possible.

## 4. Banque de suivi joueur
Pour chaque joueur ET chaque NOYAU MÈRE, suivre :
- quels cognitifs ont été joués
- quelles formes ont été jouées
- quelles familles ont été consommées (Recognition / Reasoning / Deceptive Trap)

## 5. Classement des noyaux (par joueur)
- **Niveau 1 — 100 % vierge** : noyaux jamais touchés par le joueur. Priorité maximale.
- **Niveau 2 — touchés mais exploitables** : déjà touchés, mais cognitifs/familles encore non utilisés. Le Gameplay peut comparer avec les autres joueurs pour distinguer les meilleurs noyaux disponibles.
- **Niveau 3 — back_support** : un noyau passe en `back_support` pour un joueur quand les **3 familles minimales** sont utilisées (Recognition + Reasoning + Deceptive Trap).

## 6. Ordre officiel de sélection
1. Noyaux 100 % vierges
2. Si pas assez : noyaux touchés mais encore exploitables
3. Si pas assez : noyaux back_support

## 7. Impacts verrouillés
- **Anti-répétition** : basée sur les cognitifs/familles déjà consommés dans chaque noyau **par joueur**.
- **Progression** : le joueur progresse à travers les familles cognitives du noyau, pas forcément à travers tout le noyau d'un coup.
- **Statistiques** (par joueur) : noyaux vus, cognitifs vus, familles consommées, back_support atteint.
- **Historiques** : conserver `noyau_id, joueur_id, cognitif joué, famille, forme, date, mode, résultat`.
- **Taille effective de READY_BANK** : la taille brute vient des noyaux produits par Rotation ; la **capacité effective** dépend du nombre de cognitifs exploitables par noyau.
- **Besoins analytiques** : mesurer combien de questions/cognitifs sortent réellement par noyau, surtout pour combler les besoins en **Deceptive Trap**.

**Point à analyser plus tard** : combien de questions effectives sortent par NOYAU MÈRE selon — disponibilité Recognition / Reasoning / Deceptive Trap, taux de WARNING, taux de back_support, besoins par mode. (Recoupe le point OUVERT n°2 du contrat QUESTIONINTENT : recalibrage du comptage Ready_Bank.)

## 8. Règle finale
- READY_BANK contient des NOYAUX MÈRES ENCODÉS.
- Gameplay demande Depth + Domaine.
- Gameplay consomme des cognitifs internes du noyau.
- Le noyau reste l'unité centrale ; le cognitif est l'unité consommée.

**Why:** Verrouillé avec l'utilisateur le 2026-06-16, juste après la fixation du NOYAU MÈRE comme entité complète indivisible. Cohérent avec le modèle entité : on ne fragmente PAS le noyau en stockage, mais le gameplay LE LIT à granularité cognitif/famille, par joueur. C'est ce qui rend possible anti-répétition, progression et back_support sans découper le noyau lui-même.
