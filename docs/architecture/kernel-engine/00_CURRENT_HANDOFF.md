# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-30  
**Branche officielle :** `replit/intellectual-engine-current-2026-08-16`  
**Module actif unique :** `06_Phase1`  
**Spécification active :** `specifications/06_Phase1.md` v1.0  
**Frontière suivante verrouillée :** `07_ValidationPhase1.md` v1.0  
**Décision :** `DEC-122`  
**Prochain bloc exact :** `ALIGN-AUDIT-06-v1.0 → BUILD-06-v1.0`

> Ce fichier est un pointeur opérationnel. En cas de contradiction, `00_ArchitectureRegister.md + 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md + specifications/06_Phase1.md v1.0` priment.

---

# 1. Synchronisation obligatoire

Replit ne doit pas travailler depuis l’ancien HEAD `2c4fee75`.

Avant toute inspection ou modification :

```bash
git fetch origin
git switch replit/intellectual-engine-current-2026-08-16
git pull --ff-only origin replit/intellectual-engine-current-2026-08-16
```

Puis vérifier :

```text
HEAD local = HEAD origin
divergence = 0/0
working tree = propre
```

La branche distante doit contenir les contrats documentaires successifs se terminant par :

```text
06_Phase1 v1.0
07_ValidationPhase1 v1.0
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC v1.9.0
ce CURRENT_HANDOFF
```

Si le pull n’est pas strictement fast-forward ou si le working tree n’est pas propre : STOP.

# 2. Ne pas refaire

Ne pas réimplanter ni redéfinir :

- KernelBlueprint Section 1;
- KRP v4 / DEC-119;
- Taxonomy v1.1 / DEC-120;
- QuestionIntent / KernelCodeEngine / DEC-121;
- construction progressive du `kernel_code`;
- migrations DEC-121;
- tests KRP v4;
- masque joueur;
- ReadyBank;
- Quarantine complet;
- traductions Phase2.

Ne pas restaurer :

- QCM_RECOGNITION master;
- six cognitifs dérivés;
- `question_code`;
- segments `COG` ou `VAR`;
- limites de caractères par Depth;
- tests ou contrats KRP v3.

# 3. Mission Phase1 v1.0

Recevoir le même `KernelBlueprint` finalisé et produire les sept créations source autonomes :

```text
QCM_RECOGNITION
QCM_REASONING
QCM_TRAP
TRUE_FALSE_RECOGNITION_TRUE
TRUE_FALSE_RECOGNITION_FALSE
TRUE_FALSE_REASONING_TRUE
TRUE_FALSE_REASONING_FALSE
```

Un seul appel de création structuré peut demander les sept slots ensemble.

L’écriture demeure atomique par slot.

Phase1 possède :

```text
EMPTY
CREATED
CREATION_FAILED
```

Phase1 ne possède pas :

```text
NOT_VALIDATED
PASS
SUSPICION
READY
CONSUMED
```

# 3.1 Frontière de persistance canonique

Le `KernelBlueprint` est l’unique agrégat canonique.

```text
kernel_blueprint_runs
→ Section 1 immuable

kernel_blueprint_cognitive_slots
→ sept CognitiveSlots persistés séparément
```

Chaque slot est identifié par `(blueprint_id, cognitive_type)` et une
contrainte unique garantit une seule occurrence de chaque type par Blueprint.
L’écriture est atomique par slot.

`question_intents.frame_en` est legacy et non autoritaire. Phase1 ne réécrit
jamais un frame global, ne persiste aucune traduction et ne persiste aucune
donnée joueur. Le masque joueur et le mélange des choix restent externes au
Blueprint.

# 4. Contrat intellectuel obligatoire

- sept mécanismes cognitifs autonomes;
- aucun master;
- aucune reformulation inter-slot;
- aucune conversion mécanique QCM ↔ Vrai/Faux;
- aucune négation mécanique vrai ↔ faux;
- QCM_RECOGNITION = rappel direct;
- QCM_REASONING = lien logique;
- QCM_TRAP = intuition/confusion plausible, jamais piège typographique;
- TF_RECOGNITION = fait atomique vrai/faux;
- TF_REASONING = relation logique vraie/fausse;
- texte de la question lisible en huit secondes ou moins;
- chaque bonne réponse et distracteur QCM = un mot, un nom propre, une valeur courte ou une expression courte représentant une seule idée indivisible;
- aucune phrase explicative, justification, énumération ou combinaison de plusieurs idées dans un choix;
- quatre choix QCM de même catégorie sémantique, forme grammaticale et concision comparables;
- SV explicatif lisible en trente secondes ou moins;
- difficulté portée par la connaissance ou le raisonnement;
- cohérence jusqu’au sous-domaine;
- QCM = quatre choix et une bonne réponse;
- pour les trois QCM, `choices.a` est toujours la bonne réponse et `correct_answer_key = a`;
- les distracteurs QCM restent canoniquement en `b`, `c`, `d`;
- Vrai/Faux = deux choix et polarité imposée;
- le mélange des choix appartient exclusivement au gameplay et ne réécrit jamais le Blueprint;
- le résultat joueur conserve la clé canonique du choix sélectionné, pas seulement sa lettre affichée;
- aucun contenu partiel déclaré CREATED.

# 5. Premier travail — ALIGN-AUDIT-06-v1.0

Avant le patch, comparer le code réel à `06_Phase1.md v1.0`.

Rapporter :

```text
composants Phase1 existants
pipeline d’appel actuel
client Gemini actuel réutilisable
structure actuelle des CognitiveSlots
persistance actuelle
tests actuels
KEEP
MODIFY
REMOVE
MISSING
UNRESOLVED
fichiers exacts nécessaires
migration réellement nécessaire : OUI/NON + preuve
```

Ne pas utiliser les anciens chats, `.agents/**`, `attached_assets/**` ou un document historique comme contrat.

# 6. Autorisation de Build

Si et seulement si l’audit ne révèle aucun `UNRESOLVED` architectural :

```text
BUILD-06-v1.0 autorisé
```

Le patch doit rester limité à Phase1 et aux adaptations indispensables des sept CognitiveSlots.

Une migration additive est autorisée uniquement si un blocage de schéma est démontré. Aucune migration historique ne peut être modifiée.

# 7. Gemini

Implémenter :

- contrat d’entrée versionné;
- schéma JSON `phase1.source.v1`;
- un appel pour les sept slots;
- preuves internes;
- idempotency key;
- maximum trois tentatives techniques au total;
- aucun retry intellectuel automatique;
- aucun appel Gemini réel pendant les tests;
- mocks/fixtures déterministes;
- aucune exposition de credential.

Gemini ne décide jamais le PASS officiel.

# 8. Persistance

- sept conteneurs permanents;
- écriture atomique par slot;
- identité Section 1 immuable;
- replay idempotent;
- slot techniquement invalide non persisté comme CREATED;
- autres slots valides conservés;
- aucun état joueur dans le Blueprint;
- aucune traduction.

# 9. Tests Phase1 obligatoires

Couvrir au minimum les vingt tests contractuels de `06_Phase1 v1.0`, notamment :

- discriminateurs des sept cognitifs;
- refus des mécaniques voisines;
- structure QCM/Vrai-Faux;
- polarités;
- absence de master;
- anti-conversion mécanique;
- lecture question/SV;
- Depth élevé avec question courte;
- cohérence sous-domaine;
- atomicité par slot;
- retries;
- idempotence;
- immutabilité Section 1.

Les tests PostgreSQL utilisent un schéma aléatoire isolé, jamais `public`, Neon ou la VM.

# 10. Frontière de sortie

Phase1 se termine après :

```text
réponse structurée
→ contrôles techniques locaux
→ écriture des slots créés
→ persistance des creation_status
→ passage vers ValidationPhase1
```

Phase1 ne décide ni PASS ni SUSPICION.

# 11. Git et livraison

Pendant le Build :

- aucun push automatique;
- aucun fichier `.agents/**`;
- aucun `attached_assets/**`;
- aucune modification documentaire hors correction démontrée;
- aucun secret;
- aucun workflow externe;
- aucun déploiement.

Après code et tests, produire un rapport avec :

```text
HEAD de départ
fichiers modifiés
migration éventuelle
tests exécutés
résultats
diff
working tree
commit local
UNRESOLVED
```

Le push nécessite une autorisation séparée.

# 12. Critère de sortie

```text
audit conforme
+
implémentation Phase1 v1.0
+
tests contractuels verts
+
aucune régression amont
+
diff limité
+
commit local propre
```

Ensuite seulement :

```text
ALIGN-AUDIT-07-v1.0
→ ValidationPhase1
```
