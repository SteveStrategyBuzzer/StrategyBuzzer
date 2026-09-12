# STRATEGYBUZZER — 05_QUESTIONINTENT

**Version :** 2.2
**Date :** 7 septembre 2026
**Statut :** OFFICIAL — CONTRAT ARCHITECTURAL VERROUILLÉ
**Décisions :** DEC-123 + DEC-124
**Décision connexe :** DEC-122 — OFFICIAL, inchangée
**Implémentation :** À AUDITER
**Validation terminale :** NON

> `DEC-121` est conservée comme historique **SUPERSEDED**. Elle est remplacée
> par `DEC-123`, qui fixe l'ownership exclusif de QuestionIntent sur le
> `kernel_code` complet et interdit toute projection progressive du code.

---

# 1. Mission

QuestionIntent reçoit uniquement `blueprint_id`, recharge le même
`KernelBlueprint` canonique et vérifie que les valeurs amont nécessaires sont
présentes. Il est le propriétaire exclusif de `kernel_code_vvvv` : il alloue
le suffixe `VVVV` et le persiste atomiquement.

QuestionIntent :

- lit le territoire déjà décidé ;
- attribue `VVVV` ;
- persiste uniquement `kernel_code_vvvv` ;
- ne modifie aucune donnée intellectuelle ;
- ne choisit aucun cognitif ;
- ne crée aucune question ;
- n'exécute aucune règle de gameplay.

Le `kernel_code` permet :

1. l'identification et la traçabilité du noyau durant tout son cycle ;
2. le classement rapide du noyau dans `READY_BANK` ;
3. la comparaison avec l'historique joueur afin d'éviter une répétition
   conceptuelle.

`kernel_code` est généré par PostgreSQL en lecture seule après présence de ses
six segments. QuestionIntent ne l'assemble, ne l'écrit et ne le verrouille
jamais. `KernelCodeEngine`, s'il existe techniquement, n'est ni un module, ni
une phase, ni un copropriétaire, ni une autorité d'écriture.

---

# 2. Position dans le pipeline

```text
KernelBlueprint canonique
↓
KRP écrit depth + domain_code + DD + DO
↓
Taxonomy écrit les trois valeurs complètes + SUB + SUJ + IDE
↓
QuestionIntent
  ↳ attribue et persiste VVVV
↓
PostgreSQL génère kernel_code (lecture seule)
↓
Phase 1 crée les cognitifs et les questions
↓
Validations / traductions
↓
READY_BANK
↓
Gameplay
```

QuestionIntent est l'étape d'allocation du suffixe située après Taxonomy. Il
n'est ni un moteur Taxonomy, ni un moteur cognitif, ni un moteur de sélection
gameplay. Rotation possède DD/DO, Taxonomy possède SUB/SUJ/IDE, et PostgreSQL
génère le code complet.

---

# 3. Entrées

QuestionIntent lit exactement :

```text
blueprint_id
depth
domain_code
subdomain_active
subject_active
dominant_idea_active
kernel_code_dd
kernel_code_do
kernel_code_sub
kernel_code_suj
kernel_code_ide
kernel_code_vvvv
kernel_code
```

Préconditions :

- `blueprint_id` existe et est immuable ;
- les cinq composantes intellectuelles et les cinq segments amont sont remplis ;
- `kernel_code_vvvv` est vide, sauf lors d'un replay idempotent du même Blueprint ;
- `kernel_code` est une colonne générée en lecture seule et reste `NULL` avant
  la présence des six segments.

Aucune Bank Taxonomy, mémoire Gemini, rotation KRP, donnée joueur ou donnée
cognitive n'est une entrée de QuestionIntent.

---

# 4. Sortie et propriété

QuestionIntent écrit une seule sortie autoritaire :

```text
kernel_code_vvvv = VVVV
```

Il alloue et persiste atomiquement `kernel_code_vvvv`. PostgreSQL génère
ensuite `kernel_code` en lecture seule lorsque les six segments sont présents.
QuestionIntent ne persiste aucun code complet ou partiel.

Format logique officiel :

```text
DD-DO-SUB-SUJ-IDE-VVVV
```

| Segment | Propriétaire | Source |
|---|---|---|
| `DD` | Rotation | `kernel_code_dd` |
| `DO` | Rotation | `kernel_code_do` |
| `SUB` | Taxonomy | `kernel_code_sub` |
| `SUJ` | Taxonomy | `kernel_code_suj` |
| `IDE` | Taxonomy | `kernel_code_ide` |
| `VVVV` | QuestionIntent | `kernel_code_vvvv`, compteur base36 du bassin `Depth + Domain` |

Les tables d'encodage et les règles exactes de longueur des segments amont sont
déterministes, versionnées et testées par leurs propriétaires. Elles ne peuvent
modifier la signification métier des slots.

Le stockage canonique des segments et du code généré est :

```text
kernel_blueprint_runs.kernel_code
```

`kernel_blueprint_runs.kernel_code_dd`, `kernel_code_do`, `kernel_code_sub`,
`kernel_code_suj`, `kernel_code_ide` et `kernel_code_vvvv` sont les segments
persistés par leurs propriétaires. `kernel_code` est généré par PostgreSQL,
en lecture seule, et reste `NULL` avant la présence des six segments.

## 4.1 Allocation de VVVV par QuestionIntent

```text
Blueprint avec les cinq valeurs métier et
`kernel_code_dd`, `kernel_code_do`, `kernel_code_sub`,
`kernel_code_suj`, `kernel_code_ide` déjà persistés
↓
QuestionIntent attribue VVVV = 0000
↓
QuestionIntent persiste uniquement :
kernel_code_vvvv = 0000
↓
PostgreSQL génère le kernel_code complet
```

KRP écrit atomiquement `depth + domain_code + DD + DO`. Taxonomy écrit
atomiquement `subdomain_active + subject_active + dominant_idea_active` et
`SUB + SUJ + IDE`. QuestionIntent n'assemble jamais le `kernel_code` complet.

## 4.2 Règles VVVV

`VVVV` respecte exactement :

- 4 caractères en base36 ;
- alphabet `0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ` ;
- séquence `0000 → ZZZZ` ;
- capacité de 1 679 616 valeurs par bassin ;
- compteur indépendant pour chaque couple `Depth + Domain` ;
- première allocation de chaque bassin = `0000` ;
- incrément persistant à l'intérieur du même bassin ;
- aucun reset lors d'un changement de cycle ;
- aucune dépendance envers `SUB-SUJ-IDE` pour choisir le compteur ;
- allocation transactionnelle avec verrou ;
- aucune collision sous concurrence ;
- suffixe immuable, unique et jamais recyclé ;
- replay du même Blueprint finalisé = même code, sans nouvelle allocation.

Exemples de bassins indépendants :

```text
premier noyau 02 + HIS → 02-HIS-...-...-...-0000
premier noyau 02 + GEO → 02-GEO-...-...-...-0000
premier noyau 04 + HIS → 04-HIS-...-...-...-0000
deuxième noyau 02 + HIS → 02-HIS-...-...-...-0001
```

Deux noyaux distincts du même bassin ne peuvent jamais recevoir le même
`VVVV`.

---

# 5. Deux niveaux d'identité

## 5.1 Identité conceptuelle

```text
DD-DO-SUB-SUJ-IDE
```

Elle représente le même territoire intellectuel jusqu'à la Dominant Idea.
Une nouvelle valeur `VVVV` ne crée pas automatiquement un nouveau concept pour
un joueur.

## 5.2 Identité physique

```text
DD-DO-SUB-SUJ-IDE-VVVV
```

Elle identifie une version précise du noyau. Deux versions physiques peuvent
partager la même identité conceptuelle.

---

# 6. Frontière avec Phase 1

QuestionIntent ne connaît et ne choisit aucun cognitif.

Phase1 reçoit uniquement `blueprint_id`, recharge le même Blueprint, dont le
`kernel_code` généré est disponible en lecture seule après allocation de `VVVV`,
puis remplit exactement les sept CognitiveSlots
définis par `06_Phase1`.

Aucun segment `COG`, `VAR` ou `question_code` n'est ajouté au `kernel_code` par
QuestionIntent. Les CognitiveSlots restent identifiés par leur emplacement
permanent dans le Blueprint.

---

# 7. Frontière ReadyBank

QuestionIntent ne crée aucune donnée ReadyBank. ReadyBank reçoit ultérieurement
le Blueprint complet avec son identité intellectuelle, ses sept CognitiveSlots
source, leurs traductions, leurs validations et les éventuelles réconciliations
Quarantine. La responsabilité détaillée appartient à `11_ReadyBank` et
DEC-122.

---

# 8. Frontière gameplay et historique joueur

Le `kernel_code` demeure commun à tous les joueurs et immuable. L'état cognitif
cumulatif est externe au Blueprint et peut être projeté visuellement après le
code :

```text
kernel_code + masque joueur

06-HIS-TIT-RAP-EVA-0000-00n
```

Le masque `00n → 11o` appartient au contrat ReadyBank/Gameplay :

- premier caractère : famille QCM_RECOGNITION/QCM_REASONING ;
- deuxième caractère : famille des quatre Vrai/Faux ;
- troisième caractère : QCM_TRAP ;
- maximum un cognitif par famille ;
- maximum trois cognitifs par joueur pour la même identité conceptuelle ;
- aucune remise à zéro par un changement de `VVVV`.

QuestionIntent ne lit, n'écrit et ne modifie jamais ce masque.

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

## QI-C02 — Allocation déterministe

Le même Blueprint déjà finalisé conserve le même `kernel_code` généré. Un nouveau
Blueprint du même bassin reçoit la prochaine valeur `VVVV`, même si ses
segments intellectuels sont identiques.

## QI-C03 — Idempotence

Un replay du même Blueprint avec le même `kernel_code_vvvv` est un NO-OP. Un
replay produisant un autre suffixe est une anomalie et ne remplace jamais
silencieusement le suffixe existant.

## QI-C04 — Unicité physique

Le `kernel_code` complet identifie une seule version physique de noyau.

## QI-C05 — Identité conceptuelle stable

`DD-DO-SUB-SUJ-IDE` reste la base de comparaison conceptuelle malgré un
changement de `VVVV`.

## QI-C06 — Ownership exclusif de VVVV

QuestionIntent est le seul propriétaire de `kernel_code_vvvv`. Rotation possède
`DD`/`DO`, Taxonomy possède `SUB`/`SUJ`/`IDE`, et PostgreSQL génère
`kernel_code` en lecture seule lorsque les six segments existent.
QuestionIntent n'assemble ni n'écrit le code complet. `KernelCodeEngine` n'a
aucun ownership distinct.

## QI-C07 — Séparation cognitive

QuestionIntent ne produit aucun CognitiveSlot ni masque joueur. Le plafond
d'un cognitif par famille et de trois familles au total est appliqué par le
gameplay à partir de l'historique joueur.

## QI-C08 — Aucune seconde validation

QuestionIntent ne revalide ni KRP, ni Taxonomy, ni les règles de création
Gemini.

---

# 10. États et erreurs

États contractuels minimaux de l'opération :

```text
À_ALLOUER
↓ succès atomique
VVVV_ALLOUÉ
```

Cas invalides :

- territoire incomplet ;
- bassin VVVV épuisé ;
- tentative de remplacer un suffixe verrouillé ;
- incohérence entre les segments amont et les slots du Blueprint.

Dans ces cas :

- aucun suffixe partiel n'est persisté ;
- aucun cognitif n'est créé ;
- aucune rotation KRP ou consommation Taxonomy n'est déclenchée ;
- l'incident est rapporté comme blocage de préparation.

---

# 11. Persistance et concurrence

L'allocation de `kernel_code_vvvv` doit être :

- atomique ;
- protégée par une contrainte d'unicité ;
- sûre sous concurrence ;
- idempotente pour le même `blueprint_id` ;
- traçable jusqu'au Blueprint canonique.

L'attribution et la persistance de `VVVV` forment une opération transactionnelle
de QuestionIntent. PostgreSQL génère ensuite `kernel_code` en lecture seule
après la présence des six segments. Les copies de travail et éléments de
Quarantine conservent la référence au noyau canonique ; ils ne deviennent
jamais une nouvelle autorité d'identité.

---

# 12. Tests contractuels minimaux

1. Blueprint avec les cinq valeurs métier et les cinq segments amont remplis →
   QuestionIntent alloue et persiste `kernel_code_vvvv`, puis PostgreSQL génère
   le `kernel_code` complet en lecture seule ;
2. territoire ou segment amont incomplet → aucune allocation ni code partiel ;
3. format logique `DD-DO-SUB-SUJ-IDE-VVVV` ;
4. premier noyau de chaque bassin `Depth + Domain` → `0000` ;
5. deuxième noyau du même bassin → `0001` ;
6. changement de Domain au même Depth → bassin indépendant démarrant à `0000` ;
7. même Domain à un autre Depth → bassin indépendant démarrant à `0000` ;
8. VVVV base36, sur quatre caractères, jusqu'à `ZZZZ` ;
9. changement de cycle → aucun reset du bassin ;
10. mêmes `SUB-SUJ-IDE` → aucune influence sur le choix de `VVVV` ;
11. replay identique → NO-OP sans nouvelle allocation ;
12. replay divergent → refus ;
13. concurrence dans un même bassin → une allocation transactionnelle, unique
    et non recyclée par Blueprint ;
14. QuestionIntent ne modifie aucun slot amont et n'écrit que `VVVV` ;
15. KRP écrit atomiquement `depth + domain_code + DD + DO` et Taxonomy écrit
    atomiquement ses trois valeurs complètes + `SUB + SUJ + IDE` ;
16. Phase1 remplit les sept CognitiveSlots sans modifier `kernel_code` ;
17. le masque joueur reste externe au Blueprint ;
18. même famille cognitive déjà utilisée → famille exclue pour ce joueur ;
19. trois familles utilisées → identité conceptuelle fermée pour ce joueur ;
20. nouvelle version `VVVV` → masque non réinitialisé ;
21. ReadyBank permet le filtrage structuré sans dépendre uniquement du parsing
    de chaîne.

---

# 13. Hors périmètre

Ce document ne définit pas :

- les sept cognitifs de Phase 1 ;
- leurs règles de création ;
- la stratégie précise de sélection d'une manche ;
- la durée de conservation de l'historique joueur ;
- une éventuelle politique future de remise en circulation ;
- les validations Phase 1/Phase 2 ;
- la traduction ;
- la Quarantine ;
- les objectifs quantitatifs de `READY_BANK`.

Ces contrats devront respecter les identités et invariants définis ici.

---

# 14. Statut

```text
Architecture :        VERROUILLÉE
Contrat :             VERROUILLÉ
Spécification :       OFFICIAL v2.2 / DEC-123 + DEC-124 + DEC-122
DEC-121 :             SUPERSEDED (remplacée par DEC-123)
Implémentation :      À AUDITER
Validation terminale : NON
```

Prochaine opération autorisée :

```text
ALIGN-AUDIT-05-v2.2
↓
vérifier que QuestionIntent alloue et persiste uniquement VVVV, et que
PostgreSQL génère le kernel_code en lecture seule après les six segments
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
```