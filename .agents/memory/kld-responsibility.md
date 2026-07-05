---
name: KEY_LEARNING_DIRECTION — responsabilité exacte
description: Scope verrouillé de KEY_LEARNING_DIRECTION dans le pipeline noyau — anti-répétition de direction pédagogique, pas anti-question.
---

# KEY_LEARNING_DIRECTION — responsabilité exacte (verrouillée)

⚠️ MAJ 2026-06-19 (voir `rotation-par-noyau-complet.md`) : ORDRE CHANGÉ — **KEY_STRUCTURE intervient AVANT KLD** (auparavant KLD→KEY_STRUCTURE). KLD n'est plus la première garde : c'est KEY_STRUCTURE qui détecte une collision de pré-code `yy-xx-xxx-xxx-xxx` puis APPELLE KLD pour trancher (même sujet+idée = FAIL → idée/sujet/sous-domaine suivant ; sujet/idée réellement différent = PASS → KEY_STRUCTURE attribue un `zz`). KLD signale aussi « sous-domaine épuisé ». Le reste de ce fichier (scope anti-répétition pédagogique) reste valable mais s'exécute APRÈS KEY_STRUCTURE.

KLD est un **garde anti-répétition de direction pédagogique**, PAS un garde anti-question.

## Stade du pipeline
À ce point : aucune question, aucune réponse, aucun Saviez-vous n'existe encore. Tout cela appartient à APRÈS QuestionIntent / PHASE 1.

## Travaille UNIQUEMENT sur 3 champs
- Sous-domaine (sub_domain)
- Sujet (subject)
- Idée Dominante (dominant_idea)

Ne raisonne JAMAIS sur : QCM, V/F, Recognition, Reasoning, Deceptive Trap, réponses, Saviez-vous, ni sur depth/notoriété (knowledge_frequency = DepthContract, séparé).

## Ce qu'il vérifie
1. Sujet ≠ Idée Dominante (pas les mêmes mots).
2. Pas de répétition directe (Sujet + Idée Dominante déjà utilisé).
3. Pas de répétition inversée (Idée Dominante + Sujet déjà utilisé).
4. **Synonyme direct détecté via lexique** (voiture ≈ auto ≈ char ≈ bagnole → même dossier pédagogique → FAIL).
5. Sous-domaine différent + context_map silencieux → FAIL CONTEXT_NOT_DISTINCT.
6. Concept voisin NON synonyme strict (transport → camion) → KLD LAISSE PASSER → KEY_STRUCTURE analyse.

## Frontière KLD / KEY_STRUCTURE (verrouillée 2026-07-04)

| Cas | Responsable | Décision |
|---|---|---|
| transport + voiture → transport + auto | **KLD** | FAIL — synonyme direct (doublon caché) |
| transport + voiture → transport + camion | **KLD** passe | KEY_STRUCTURE analyse le contexte |
| idea hors depth | **KEY_STRUCTURE** | FAIL structure |
| découpage Domaine→Sous-domaine→Sujet→Idée illogique | **KEY_STRUCTURE** | FAIL structure |

**KLD = lexique de synonymes directs.**
**KEY_STRUCTURE = moteur de contexte complet (cohérence, depth, décorticage, collision structurelle).**

KLD ne juge jamais si une idée est pédagogiquement bonne pour un domaine.
Il juge seulement : même dossier pédagogique sous un nom différent = doublon caché.

**Why:** L'utilisateur a corrigé deux fois pour empêcher la confusion. KLD ne doit pas être contaminé par la couche question (variants/types) ni par la notoriété (DepthContract). C'est purement de l'anti-doublon sur la direction Sujet+Idée Dominante.

**How to apply:** Le sub_domain est un discriminateur de contexte pour TOUTES les correspondances, y compris directe/inversée EXACTE. Pas de FAIL dur automatique : si la paire (exacte/inversée/équivalente/proche) existe déjà, on regarde le sub_domain. Même sub_domain → FAIL. sub_domain différent → CONTEXT_CHECK interne (contexte pédagogique distinct → PASS ; équivalent → FAIL CONTEXT_NOT_DISTINCT). "sub_domain différent ≠ automatiquement contexte différent" : ça donne une POSSIBILITÉ de PASS, pas une garantie.

**Déterministe, pas d'IA :** normalisation + **lexique de synonymes directs par domaine** (voiture≈auto≈char≈bagnole ; capitale≈statut dans un contexte de gouvernance) + context_map (sous-domaines distincts). Le seuil de distance de tokens (0.85) est ABANDONNÉ : KLD ne juge plus par proximité statistique, il juge par synonymie stricte dans un lexique contrôlé. Les concepts voisins non synonymes (camion vs voiture) passent KLD et vont à KEY_STRUCTURE.

**Sortie :** PASS | FAIL uniquement (CONTEXT_CHECK = interne). Reasons : INVALID_MINIMAL_PAIR, DIRECT_PAIR_CONTEXT_DUPLICATE, REVERSED_PAIR_CONTEXT_DUPLICATE, CONCEPTUAL_COLLISION, PAIR_TOO_CLOSE_TO_EXISTING, CONTEXT_NOT_DISTINCT.

## Contrat v3 — VERROUILLÉ (validé utilisateur)

Défaut officiel : **context_map absent ou silencieux = FAIL CONTEXT_NOT_DISTINCT** (défaut conservateur). sub_domain différent ne donne JAMAIS PASS automatiquement — il ne fait qu'ouvrir le CONTEXT_CHECK. La réutilisation n'est autorisée que si le context_map déclare EXPLICITEMENT que les deux sous-domaines produisent un contexte pédagogique distinct.

Résolution CONTEXT_CHECK (paire directe/inversée/équivalente/proche, sous-domaine différent) :
- context_map déclare les 2 sous-domaines comme distincts pour cette idée dominante → PASS
- sinon → FAIL CONTEXT_NOT_DISTINCT

Récapitulatif verrouillé : KLD = garde anti-répétition de direction pédagogique ; travaille uniquement sur Sous-domaine + Sujet + Idée Dominante ; ne connaît ni question, ni réponse, ni Saviez-vous ; ne touche pas knowledge_frequency ; **lexique de synonymes directs** (getEquivalences) + context_map (arbitrage sous-domaines) ; 100% déterministe, sans IA ; sortie uniquement PASS/FAIL ; **seuil de proximité 0.85 ABANDONNÉ** — concepts voisins non synonymes passent KLD → KEY_STRUCTURE.

## KEY_LEARNING_DIRECTION_RULESET (CORRIGÉ 2026-07-04)
- KLD-1 Sujet ≠ Idée Dominante (Paris+Paris✗). Motif INVALID_MINIMAL_PAIR.
- KLD-2 Paire directe exacte déjà dans LearningDirectionRegistry → Motif DIRECT_PAIR_CONTEXT_DUPLICATE.
- KLD-3 Paire inversée exacte déjà dans registry → Motif REVERSED_PAIR_CONTEXT_DUPLICATE.
- KLD-4 **Synonyme direct** détecté via lexique contrôlé (voiture≈auto≈char≈bagnole) → Motif CONCEPTUAL_COLLISION.
- KLD-5 Sous-domaine différent ≠ PASS auto ; PASS seulement si context_map déclare les contextes distincts → sinon Motif CONTEXT_NOT_DISTINCT.
- KLD-6 **RETIRÉ** — le seuil de proximité token 0.85 est abandonné. Les concepts voisins non synonymes (camion ≠ voiture) passent KLD et vont à KEY_STRUCTURE.

Correction v4 (2026-07-04) : KLD-6 supprimé. KLD-4 recentré sur synonymes directs (lexique), pas sur proximité sémantique générale. KEY_STRUCTURE prend en charge l'analyse des concepts voisins.
