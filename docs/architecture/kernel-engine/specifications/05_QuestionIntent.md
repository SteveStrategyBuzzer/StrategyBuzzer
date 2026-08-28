# STRATEGYBUZZER — 05_QUESTIONINTENT / KERNELCODEENGINE

**Version :** 2.0  
**Date :** 28 août 2026  
**Statut :** OFFICIAL — CONTRAT ARCHITECTURAL VERROUILLÉ  
**Décision :** DEC-121  
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission

QuestionIntent reçoit le même `KernelBlueprint` canonique après l’écriture du territoire Taxonomy et construit son identité intellectuelle stable.

QuestionIntent :

- lit le territoire déjà décidé;
- produit et verrouille `kernel_code`;
- ne modifie aucune donnée intellectuelle;
- ne choisit aucun cognitif;
- ne crée aucune question;
- n’exécute aucune règle de gameplay.

Le `kernel_code` permet :

1. l’identification et la traçabilité du noyau durant tout son cycle;
2. le classement rapide du noyau dans `READY_BANK`;
3. la comparaison avec l’historique joueur afin d’éviter une répétition conceptuelle.

---

# 2. Position dans le pipeline

```text
KernelBlueprint canonique
↓
KRP écrit depth + domain
↓
Taxonomy écrit subdomain_active + subject_active + dominant_idea_active
↓
QuestionIntent écrit kernel_code
↓
Phase 1 crée les cognitifs et les questions
↓
Validations / traductions
↓
READY_BANK
↓
Gameplay
```

QuestionIntent est une étape d’encodage d’identité. Il n’est ni un moteur Taxonomy, ni un moteur cognitif, ni un moteur de sélection gameplay.

---

# 3. Entrées

QuestionIntent lit exactement :

```text
blueprint_id
depth
domain
subdomain_active
subject_active
dominant_idea_active
```

Préconditions :

- `blueprint_id` existe et est immuable;
- les cinq composantes intellectuelles sont remplies;
- `kernel_code` est vide, sauf lors d’un replay idempotent du même Blueprint.

Aucune Bank Taxonomy, mémoire Gemini, rotation KRP, donnée joueur ou donnée cognitive n’est une entrée de QuestionIntent.

---

# 4. Sortie et propriété

QuestionIntent écrit exactement :

```text
kernel_code
```

Format logique officiel :

```text
DD-DO-SUB-SUJ-IDE-VVVV
```

| Segment | Signification |
|---|---|
| `DD` | Depth |
| `DO` | Domain |
| `SUB` | Subdomain |
| `SUJ` | Subject |
| `IDE` | Dominant Idea |
| `VVVV` | version physique du noyau |

Le format décrit des segments logiques. Les tables d’encodage et les règles exactes de longueur doivent être déterministes, versionnées et testées par `KernelCodeEngine`; elles ne peuvent modifier la signification métier des segments.

Le stockage canonique demeure :

```text
kernel_blueprint_runs.kernel_code
```

Le slot `KernelBlueprint.kernel_code` appartient à QuestionIntent et devient immuable après sa première écriture réussie.

---

# 5. Deux niveaux d’identité

## 5.1 Identité conceptuelle

```text
DD-DO-SUB-SUJ-IDE
```

Elle représente le même territoire intellectuel jusqu’à la Dominant Idea.

Une nouvelle valeur `VVVV` ne crée pas automatiquement un nouveau concept pour un joueur.

## 5.2 Identité physique

```text
DD-DO-SUB-SUJ-IDE-VVVV
```

Elle identifie une version précise du noyau.

Deux versions physiques peuvent partager la même identité conceptuelle.

---

# 6. Frontière avec Phase 1

QuestionIntent ne connaît et ne choisit aucun cognitif.

Phase 1 reçoit le Blueprint portant `kernel_code`, puis produit les questions cognitives. Chaque question reçoit une identité complète distincte :

```text
question_code = DD-DO-SUB-SUJ-IDE-VVVV-COG-VAR
```

| Segment ajouté | Propriétaire | Signification |
|---|---|---|
| `COG` | Phase 1 | type cognitif utilisé |
| `VAR` | Phase 1 | original ou variante physique de la question |

`question_code` n’est pas un remplacement de `kernel_code`.

- `kernel_code` identifie le noyau;
- `question_code` identifie une question cognitive précise issue de ce noyau.

Une variante `VAR` différente du même `COG` ne constitue pas un nouveau cognitif.

---

# 7. Contrat READY_BANK

`READY_BANK` doit conserver et rendre interrogeables au minimum :

```text
kernel_code
question_code
depth
domain
subdomain
subject
dominant_idea
cognitive_code
variant_code
```

Les champs indexables peuvent être matérialisés séparément. Le gameplay ne doit pas dépendre d’un découpage fragile de chaîne si les segments sont déjà disponibles sous forme de colonnes structurées.

La chaîne complète demeure l’identité vérifiable; les colonnes servent à la sélection performante.

---

# 8. Contrat gameplay et historique joueur

Le gameplay applique successivement :

1. `DD-DO-SUB` pour trouver rapidement les noyaux admissibles selon la partie;
2. `SUJ-IDE` pour reconnaître l’identité conceptuelle;
3. `COG` pour savoir quel traitement cognitif le joueur a déjà reçu;
4. `VVVV-VAR` pour identifier la question physique exacte.

Clé logique anti-répétition :

```text
player_id
+ DD-DO-SUB-SUJ-IDE
+ COG
```

Règles verrouillées :

- même identité conceptuelle + même `COG` déjà joué : interdit;
- même identité conceptuelle + nouveau `COG` : permis tant que le plafond n’est pas atteint;
- maximum de **3 cognitifs distincts** par joueur pour une même identité `DD-DO-SUB-SUJ-IDE`;
- après trois `COG` distincts, cette identité conceptuelle est fermée pour ce joueur;
- changer `VVVV` ne remet pas le compteur cognitif à zéro;
- changer `VAR` ne remet pas le compteur cognitif à zéro;
- une variante du même cognitif ne contourne jamais l’anti-répétition.

Toute politique exceptionnelle de remise en circulation appartient à un futur contrat Gameplay explicite. Elle ne peut être supposée par QuestionIntent, Phase 1 ou `READY_BANK`.

---

# 9. Invariants

## QI-C01 — Territoire immuable

QuestionIntent ne modifie jamais :

```text
depth
domain
subdomain_active
subject_active
dominant_idea_active
```

## QI-C02 — Encodage déterministe

Les mêmes entrées canoniques et la même version produisent le même `kernel_code`.

## QI-C03 — Idempotence

Un replay du même Blueprint avec le même `kernel_code` est un NO-OP.

Un replay produisant un autre code est une anomalie et ne remplace jamais silencieusement le code existant.

## QI-C04 — Unicité physique

Le `kernel_code` complet identifie une seule version physique de noyau.

## QI-C05 — Identité conceptuelle stable

`DD-DO-SUB-SUJ-IDE` reste la base de comparaison conceptuelle malgré un changement de `VVVV`.

## QI-C06 — Séparation cognitive

QuestionIntent ne produit ni `COG`, ni `VAR`, ni `question_code`.

## QI-C07 — Limite joueur

Le plafond de trois cognitifs est appliqué par le gameplay à partir de l’historique joueur; il n’est pas appliqué par QuestionIntent.

## QI-C08 — Aucune seconde validation

QuestionIntent ne revalide ni KRP, ni Taxonomy, ni les règles de création Gemini.

---

# 10. États et erreurs

États contractuels minimaux de l’opération :

```text
À_ENCODER
↓ succès atomique
ENCODÉ
```

Cas invalides :

- territoire incomplet;
- segment impossible à encoder;
- collision d’unicité avec une autre identité;
- tentative de remplacer un code verrouillé;
- incohérence entre le code existant et les slots du Blueprint.

Dans ces cas :

- aucun code partiel n’est persisté;
- aucun cognitif n’est créé;
- aucune rotation KRP ou consommation Taxonomy n’est déclenchée;
- l’incident est rapporté comme blocage de préparation.

---

# 11. Persistance et concurrence

La création du `kernel_code` doit être :

- atomique;
- protégée par une contrainte d’unicité;
- sûre sous concurrence;
- idempotente pour le même `blueprint_id`;
- traçable jusqu’au Blueprint canonique.

Les copies de travail et éléments de Quarantine conservent la référence au noyau canonique. Ils ne deviennent jamais une nouvelle autorité d’identité.

---

# 12. Tests contractuels minimaux

1. territoire complet → `kernel_code` créé;
2. territoire incomplet → aucun code;
3. format logique `DD-DO-SUB-SUJ-IDE-VVVV`;
4. replay identique → NO-OP;
5. replay divergent → refus;
6. concurrence → une seule identité persistée;
7. QuestionIntent ne modifie aucun slot amont;
8. QuestionIntent ne produit aucun cognitif;
9. Phase 1 prolonge l’identité avec `COG-VAR`;
10. même concept + même COG → exclusion joueur;
11. même concept + nouveau COG → permis jusqu’à trois;
12. quatrième COG distinct → exclusion;
13. nouvelle version `VVVV` → compteur non réinitialisé;
14. nouvelle variante `VAR` → compteur non réinitialisé;
15. `READY_BANK` permet le filtrage structuré sans dépendre uniquement du parsing de chaîne.

---

# 13. Hors périmètre

Ce document ne définit pas :

- les sept cognitifs de Phase 1;
- leurs règles de création;
- la stratégie précise de sélection d’une manche;
- la durée de conservation de l’historique joueur;
- une éventuelle politique future de remise en circulation;
- les validations Phase 1/Phase 2;
- la traduction;
- la Quarantine;
- les objectifs quantitatifs de `READY_BANK`.

Ces contrats devront respecter les identités et invariants définis ici.

---

# 14. Statut

```text
Architecture :        VERROUILLÉE
Contrat :             VERROUILLÉ
Spécification :       OFFICIAL v2.0 / DEC-121
Implémentation :      À AUDITER
Validation terminale : NON
```

Prochaine opération autorisée :

```text
AUDIT-05-v2.0
↓
audit QuestionIntent + KernelCodeEngine + frontière Phase 1/READY_BANK/gameplay
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
```
