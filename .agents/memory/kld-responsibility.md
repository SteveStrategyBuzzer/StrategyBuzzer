---
name: KEY_LEARNING_DIRECTION — responsabilité exacte
description: Scope verrouillé de KEY_LEARNING_DIRECTION dans le pipeline noyau — anti-répétition de direction pédagogique, pas anti-question.
---

# KEY_LEARNING_DIRECTION — responsabilité exacte (verrouillée)

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
4. Si une paire PROCHE existe : le Sous-domaine peut "sauver" le PASS s'il change réellement le contexte (questions totalement différentes possibles). Sinon FAIL.
5. Équivalences métier : ex. Capitale ≈ Statut → idées dominantes équivalentes selon contexte → traitées comme collision conceptuelle.

**Why:** L'utilisateur a corrigé deux fois pour empêcher la confusion. KLD ne doit pas être contaminé par la couche question (variants/types) ni par la notoriété (DepthContract). C'est purement de l'anti-doublon sur la direction Sujet+Idée Dominante.

**How to apply:** Le sub_domain est un discriminateur de contexte pour TOUTES les correspondances, y compris directe/inversée EXACTE. Pas de FAIL dur automatique : si la paire (exacte/inversée/équivalente/proche) existe déjà, on regarde le sub_domain. Même sub_domain → FAIL. sub_domain différent → CONTEXT_CHECK interne (contexte pédagogique distinct → PASS ; équivalent → FAIL CONTEXT_NOT_DISTINCT). "sub_domain différent ≠ automatiquement contexte différent" : ça donne une POSSIBILITÉ de PASS, pas une garantie.

**Déterministe, pas d'IA :** normalisation + equivalence_map (dictionnaire métier contrôlé par domaine/sous-domaine, ex. capitale≈statut, chef-lieu≈capitale administrative, métropole≠capitale selon contexte) + distance de tokens + seuil paramétrable (départ 0.85). Le résultat "proche" ne FAIL pas automatiquement si le sub_domain change → déclenche CONTEXT_CHECK. Le context check est résolu par un dictionnaire de contexte contrôlé (déclare les sous-domaines réellement distincts pour une idée dominante).

**Sortie :** PASS | FAIL uniquement (CONTEXT_CHECK = interne). Reasons : INVALID_MINIMAL_PAIR, DIRECT_PAIR_CONTEXT_DUPLICATE, REVERSED_PAIR_CONTEXT_DUPLICATE, CONCEPTUAL_COLLISION, PAIR_TOO_CLOSE_TO_EXISTING, CONTEXT_NOT_DISTINCT.
