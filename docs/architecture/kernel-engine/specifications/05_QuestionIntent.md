# STRATEGYBUZZER — 05_QUESTIONINTENT / KERNELCODEENGINE

**Version :** 2.2  
**Date :** 28 août 2026  
**Statut :** OFFICIAL — CONTRAT ARCHITECTURAL VERROUILLÉ  
**Décision :** DEC-121  
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission

QuestionIntent reçoit le même `KernelBlueprint` canonique après la construction progressive de ses cinq segments intellectuels et finalise son identité stable en attribuant uniquement le suffixe `VVVV`.

QuestionIntent :

- lit le territoire déjà décidé;
- vérifie les cinq segments déjà projetés par le KernelBlueprint;
- alloue uniquement `VVVV`;
- assemble et verrouille le `kernel_code` complet;
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
  ↳ le KernelBlueprint projette DD + DO
↓
Taxonomy écrit subdomain_active + subject_active + dominant_idea_active
  ↳ le KernelBlueprint projette SUB + SUJ + IDE
↓
QuestionIntent / KernelCodeEngine alloue VVVV
  ↳ assemble et verrouille kernel_code
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

Le KernelBlueprint construit progressivement les emplacements du code au moment où chaque propriétaire écrit ses propres slots :

```text
KRP       → DD + DO
Taxonomy  → SUB + SUJ + IDE
```

QuestionIntent écrit exactement :

```text
VVVV
```

Puis `KernelCodeEngine` assemble et verrouille atomiquement le `kernel_code` complet. KRP et Taxonomy ne deviennent jamais writers du code final : ils restent propriétaires de leurs slots métier, dont le KernelBlueprint projette les segments.

Format logique officiel :

```text
DD-DO-SUB-SUJ-IDE-VVVV
```

| Segment | Signification |
|---|---|
| `DD` | KRP via `depth` | Depth sur 2 caractères, par exemple `2 → 02` |
| `DO` | KRP via `domain` | 3 premières lettres normalisées du Domain |
| `SUB` | Taxonomy via `subdomain_active` | 3 premières lettres normalisées du Subdomain |
| `SUJ` | Taxonomy via `subject_active` | 3 premières lettres normalisées du Subject |
| `IDE` | Taxonomy via `dominant_idea_active` | 3 premières lettres normalisées de la Dominant Idea |
| `VVVV` | QuestionIntent / KernelCodeEngine | compteur base36 du bassin `Depth + Domain` |

Le format décrit des segments logiques. Les tables d’encodage et les règles exactes de longueur doivent être déterministes, versionnées et testées par `KernelCodeEngine`; elles ne peuvent modifier la signification métier des segments.

Le stockage canonique demeure :

```text
kernel_blueprint_runs.kernel_code
```

Le slot final `KernelBlueprint.kernel_code` est verrouillé par QuestionIntent après l’allocation de `VVVV`. Avant ce verrouillage, le KernelBlueprint conserve la projection progressive des cinq segments émis par les écritures KRP et Taxonomy.

## 4.1 Construction progressive

```text
KernelBlueprint créé
→ __-___-___-___-___-____

KRP écrit depth=2 + domain=Histoire
→ 02-HIS-___-___-___-____

Taxonomy écrit Rome + César + Conquête
→ 02-HIS-ROM-CES-CON-____

QuestionIntent alloue VVVV
→ 02-HIS-ROM-CES-CON-0000
→ code complet verrouillé
```

Chaque propriétaire écrit uniquement ses données métier. La projection dans les emplacements du code est une responsabilité du KernelBlueprint; aucun moteur ne reconstruit les segments appartenant aux autres.

## 4.2 Règles VVVV

`VVVV` respecte exactement :

- 4 caractères en base36;
- alphabet `0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ`;
- séquence `0000 → ZZZZ`;
- capacité de 1 679 616 valeurs par bassin;
- compteur indépendant pour chaque couple `Depth + Domain`;
- première allocation de chaque bassin = `0000`;
- incrément persistant à l’intérieur du même bassin;
- aucun reset lors d’un changement de cycle;
- aucune dépendance envers `SUB-SUJ-IDE` pour choisir le compteur;
- allocation transactionnelle avec verrou;
- aucune collision sous concurrence;
- suffixe immuable et jamais recyclé;
- replay du même Blueprint finalisé = même code, sans nouvelle allocation.

Exemples de bassins indépendants :

```text
premier noyau 02 + HIS → 02-HIS-...-...-...-0000
premier noyau 02 + GEO → 02-GEO-...-...-...-0000
premier noyau 04 + HIS → 04-HIS-...-...-...-0000
deuxième noyau 02 + HIS → 02-HIS-...-...-...-0001
```

Deux noyaux distincts du même bassin ne peuvent jamais recevoir le même `VVVV`.

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

Phase1 reçoit le même Blueprint portant le `kernel_code` final et remplit exactement les sept CognitiveSlots définis par `06_Phase1`.

Aucun segment `COG`, `VAR` ou `question_code` n’est ajouté au `kernel_code` par QuestionIntent.

Les CognitiveSlots restent identifiés par leur emplacement permanent dans le Blueprint.

---

# 7. Frontière ReadyBank

QuestionIntent ne crée aucune donnée ReadyBank.

ReadyBank reçoit ultérieurement le Blueprint complet avec :

- son identité intellectuelle;
- ses sept CognitiveSlots source;
- leurs traductions;
- leurs validations;
- les éventuelles réconciliations Quarantine.

La responsabilité détaillée appartient à `11_ReadyBank` et DEC-122.

---

# 8. Frontière gameplay et historique joueur

Le `kernel_code` demeure commun à tous les joueurs et immuable.

L’état cognitif cumulatif est externe au Blueprint et peut être projeté visuellement après le code :

```text
kernel_code + masque joueur

06-HIS-TIT-RAP-EVA-0000-00n
```

Le masque `00n → 11o` appartient au contrat ReadyBank/Gameplay :

- premier caractère : famille QCM_RECOGNITION/QCM_REASONING;
- deuxième caractère : famille des quatre Vrai/Faux;
- troisième caractère : QCM_TRAP;
- maximum un cognitif par famille;
- maximum trois cognitifs par joueur pour la même identité conceptuelle;
- aucune remise à zéro par un changement de `VVVV`.

QuestionIntent ne lit, n’écrit et ne modifie jamais ce masque.

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

Le même Blueprint déjà finalisé produit le même `kernel_code`. Un nouveau Blueprint du même bassin reçoit la prochaine valeur `VVVV`, même si ses segments intellectuels sont identiques.

## QI-C03 — Idempotence

Un replay du même Blueprint avec le même `kernel_code` est un NO-OP.

Un replay produisant un autre code est une anomalie et ne remplace jamais silencieusement le code existant.

## QI-C04 — Unicité physique

Le `kernel_code` complet identifie une seule version physique de noyau.

## QI-C05 — Identité conceptuelle stable

`DD-DO-SUB-SUJ-IDE` reste la base de comparaison conceptuelle malgré un changement de `VVVV`.

## QI-C06 — Séparation cognitive

QuestionIntent ne produit ni `DD`, ni `DO`, ni `SUB`, ni `SUJ`, ni `IDE`, ni CognitiveSlot, ni masque joueur. Il alloue uniquement `VVVV` et verrouille l’assemblage final.

## QI-C07 — Limite joueur

Le plafond d’un cognitif par famille et de trois familles au total est appliqué par le gameplay à partir de l’historique joueur; il n’est pas appliqué par QuestionIntent.

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

1. écriture KRP → projection immédiate de `DD-DO`;
2. écriture Taxonomy → projection immédiate de `SUB-SUJ-IDE`;
3. territoire complet → QuestionIntent alloue uniquement `VVVV` et verrouille `kernel_code`;
4. territoire incomplet → aucune finalisation;
5. format logique `DD-DO-SUB-SUJ-IDE-VVVV`;
6. premier noyau de chaque bassin `Depth + Domain` → `0000`;
7. deuxième noyau du même bassin → `0001`;
8. changement de Domain au même Depth → bassin indépendant démarrant à `0000`;
9. même Domain à un autre Depth → bassin indépendant démarrant à `0000`;
10. replay identique → NO-OP;
11. replay divergent → refus;
12. concurrence → une seule identité persistée;
13. QuestionIntent ne modifie aucun slot amont;
14. QuestionIntent ne produit aucun cognitif;
15. Phase1 remplit les sept CognitiveSlots sans modifier `kernel_code`;
16. le masque joueur reste externe au Blueprint;
17. même famille cognitive déjà utilisée → famille exclue pour ce joueur;
18. trois familles utilisées → identité conceptuelle fermée pour ce joueur;
19. nouvelle version `VVVV` → masque non réinitialisé;
20. ReadyBank permet le filtrage structuré sans dépendre uniquement du parsing de chaîne.

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
Spécification :       OFFICIAL v2.2 / DEC-121 + DEC-122
Implémentation :      À AUDITER
Validation terminale : NON
```

Prochaine opération autorisée :

```text
ALIGN-AUDIT-05-v2.1
↓
vérifier la construction progressive KRP/Taxonomy dans le KernelBlueprint et l’allocation VVVV par QuestionIntent
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
```
