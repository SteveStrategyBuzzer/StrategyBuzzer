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
`KernelBlueprint` canonique et vérifie que ses cinq données intellectuelles
sont déjà remplies. Il est l'unique propriétaire du
`kernel_code` complet : il attribue `VVVV`, construit le code complet, le
persiste et le verrouille dans une même opération atomique.

QuestionIntent :

- lit le territoire déjà décidé ;
- encode toutes les composantes du code à partir des slots métier persistés ;
- attribue `VVVV` ;
- construit, persiste et verrouille le `kernel_code` complet ;
- ne modifie aucune donnée intellectuelle ;
- ne choisit aucun cognitif ;
- ne crée aucune question ;
- n'exécute aucune règle de gameplay.

Le `kernel_code` permet :

1. l'identification et la traçabilité du noyau durant tout son cycle ;
2. le classement rapide du noyau dans `READY_BANK` ;
3. la comparaison avec l'historique joueur afin d'éviter une répétition
   conceptuelle.

`KernelCodeEngine`, s'il existe techniquement, est exclusivement un mécanisme
interne de QuestionIntent. Il n'est ni un module, ni une phase, ni un
copropriétaire, ni une porte supplémentaire, ni un destinataire autonome de
clé, ni une autorité d'écriture indépendante.

---

# 2. Position dans le pipeline

```text
KernelBlueprint canonique
↓
KRP écrit depth + domain
↓
Taxonomy écrit subdomain_active + subject_active + dominant_idea_active
↓
QuestionIntent
  ↳ attribue VVVV
  ↳ construit, persiste et verrouille kernel_code complet
↓
Phase 1 crée les cognitifs et les questions
↓
Validations / traductions
↓
READY_BANK
↓
Gameplay
```

QuestionIntent est l'étape d'identité persistante située après Taxonomy. Il
n'est ni un moteur Taxonomy, ni un moteur cognitif, ni un moteur de sélection
gameplay. KRP et Taxonomy écrivent uniquement leurs slots métier respectifs ;
ils ne construisent, ne projettent, ne persistent ni ne verrouillent une partie
du `kernel_code`.

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

- `blueprint_id` existe et est immuable ;
- les cinq composantes intellectuelles sont remplies ;
- `kernel_code` est vide, sauf lors d'un replay idempotent du même Blueprint.

Aucune Bank Taxonomy, mémoire Gemini, rotation KRP, donnée joueur ou donnée
cognitive n'est une entrée de QuestionIntent.

---

# 4. Sortie et propriété

QuestionIntent écrit une seule sortie autoritaire :

```text
kernel_code = DD-DO-SUB-SUJ-IDE-VVVV
```

Il construit ce code complet à partir de tous les slots métier déjà persistés,
puis le persiste et le verrouille atomiquement. Il ne persiste aucun code
partiel.

Format logique officiel :

```text
DD-DO-SUB-SUJ-IDE-VVVV
```

| Segment | Source métier lue par QuestionIntent | Encodage construit par QuestionIntent |
|---|---|---|
| `DD` | `depth` écrit par KRP | Depth sur 2 caractères, par exemple `2 → 02` |
| `DO` | `domain` écrit par KRP | 3 premières lettres normalisées du Domain |
| `SUB` | `subdomain_active` écrit par Taxonomy | 3 premières lettres normalisées du Subdomain |
| `SUJ` | `subject_active` écrit par Taxonomy | 3 premières lettres normalisées du Subject |
| `IDE` | `dominant_idea_active` écrit par Taxonomy | 3 premières lettres normalisées de la Dominant Idea |
| `VVVV` | compteur de bassin attribué par QuestionIntent | compteur base36 du bassin `Depth + Domain` |

Les tables d'encodage et les règles exactes de longueur sont déterministes,
versionnées et testées par QuestionIntent, y compris lorsqu'elles sont
exécutées par son mécanisme interne `KernelCodeEngine`. Elles ne peuvent
modifier la signification métier des slots.

Le stockage canonique est :

```text
kernel_blueprint_runs.kernel_code
```

Le slot final `KernelBlueprint.kernel_code` est construit, persisté et
verrouillé par QuestionIntent uniquement. Il n'existe aucun état de
`kernel_code` progressif ni aucune projection de segments avant cette
opération.

## 4.1 Construction complète par QuestionIntent

```text
Blueprint avec depth=2, domain=Histoire,
subdomain_active=Rome, subject_active=César,
dominant_idea_active=Conquête
↓
QuestionIntent encode DD + DO + SUB + SUJ + IDE
↓
QuestionIntent attribue VVVV = 0000
↓
QuestionIntent persiste et verrouille :
02-HIS-ROM-CES-CON-0000
```

KRP n'écrit que `depth + domain`. Taxonomy n'écrit que
`subdomain_active + subject_active + dominant_idea_active`. QuestionIntent est
seul à construire le `kernel_code` complet à partir de ces données.

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

Phase1 reçoit le même Blueprint portant le `kernel_code` final, persistant et
verrouillé par QuestionIntent, puis remplit exactement les sept CognitiveSlots
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

## QI-C02 — Construction déterministe

Le même Blueprint déjà finalisé produit le même `kernel_code`. Un nouveau
Blueprint du même bassin reçoit la prochaine valeur `VVVV`, même si ses
segments intellectuels sont identiques.

## QI-C03 — Idempotence

Un replay du même Blueprint avec le même `kernel_code` est un NO-OP. Un replay
produisant un autre code est une anomalie et ne remplace jamais silencieusement
le code existant.

## QI-C04 — Unicité physique

Le `kernel_code` complet identifie une seule version physique de noyau.

## QI-C05 — Identité conceptuelle stable

`DD-DO-SUB-SUJ-IDE` reste la base de comparaison conceptuelle malgré un
changement de `VVVV`.

## QI-C06 — Ownership exclusif du code

QuestionIntent est le seul propriétaire du `kernel_code` complet : il en
construit tous les segments, l'attribue, le persiste et le verrouille.
QuestionIntent ne produit pas les données métier `depth`, `domain`,
`subdomain_active`, `subject_active` ou `dominant_idea_active`, mais les lit
pour construire le code. `KernelCodeEngine` n'a aucun ownership distinct.

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
À_ENCODER
↓ succès atomique
ENCODÉ
```

Cas invalides :

- territoire incomplet ;
- segment impossible à encoder ;
- collision d'unicité avec une autre identité ;
- tentative de remplacer un code verrouillé ;
- incohérence entre le code existant et les slots du Blueprint.

Dans ces cas :

- aucun code partiel n'est persisté ;
- aucun cognitif n'est créé ;
- aucune rotation KRP ou consommation Taxonomy n'est déclenchée ;
- l'incident est rapporté comme blocage de préparation.

---

# 11. Persistance et concurrence

La création du `kernel_code` doit être :

- atomique ;
- protégée par une contrainte d'unicité ;
- sûre sous concurrence ;
- idempotente pour le même `blueprint_id` ;
- traçable jusqu'au Blueprint canonique.

L'attribution `VVVV`, la construction du code complet, sa persistance et son
verrouillage forment une opération transactionnelle de QuestionIntent. Les
copies de travail et éléments de Quarantine conservent la référence au noyau
canonique ; ils ne deviennent jamais une nouvelle autorité d'identité.

---

# 12. Tests contractuels minimaux

1. Blueprint avec les cinq slots métier remplis → QuestionIntent construit,
   persiste et verrouille le `kernel_code` complet ;
2. territoire incomplet → aucune finalisation ni code partiel ;
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
14. QuestionIntent ne modifie aucun slot amont ;
15. KRP n'écrit que `depth + domain` et Taxonomy que son triplet ; ni l'un ni
    l'autre ne construit de segment de `kernel_code` ;
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
vérifier que QuestionIntent construit, persiste et verrouille seul le
kernel_code complet et que VVVV respecte ses invariants transactionnels
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
```