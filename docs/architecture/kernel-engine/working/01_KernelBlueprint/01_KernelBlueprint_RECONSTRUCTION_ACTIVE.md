# 01_KernelBlueprint — Reconstruction canonique active

**Statut du module :** VERROUILLÉ historiquement  
**Statut du document :** RECONSTRUCTION CANONIQUE EN COURS  
**But :** recueillir uniquement les règles actuelles vérifiées avant de recréer le document canonique final.

> Ce fichier ne raconte pas l’évolution des décisions. Il contient uniquement les règles actuelles déjà confirmées ou les brides inter-modules à vérifier/intégrer.

# 1. Organisation canonique du KernelBlueprint

Le `KernelBlueprint` est organisé en **trois sections fonctionnelles permanentes** correspondant aux trois grandes étapes de construction du noyau.

```text
SECTION 1 — CRÉATION INTELLECTUELLE
↓
SECTION 2 — CRÉATION GAMEPLAY
↓
SECTION 3 — TRADUCTION
```

Ces sections appartiennent au **même Blueprint canonique**. Elles ne créent pas trois Blueprints différents.

---

# 2. SECTION 1 — CRÉATION INTELLECTUELLE

## 2.1 Mission

Cette section porte l’identité et le territoire intellectuel du noyau, depuis la création du Blueprint jusqu’à `QuestionIntent`.

```text
KernelBlueprintFactory
↓
KernelBlueprint
↓
KernelRotationPlanner
↓
Taxonomy
  ↳ Gemini utilise les règles ValidationDominantIdeas
↓
QuestionIntent
```

## 2.2 Slots de création intellectuelle

```text
blueprint_id

depth
domain

subdomain_active
subject_active
dominant_idea_active

kernel_code
```

## 2.3 Ownership

```text
blueprint_id
→ KernelBlueprint / KernelBlueprintFactory

depth
→ KernelRotationPlanner

domain
→ KernelRotationPlanner

subdomain_active
→ Taxonomy

subject_active
→ Taxonomy

dominant_idea_active
→ Taxonomy

kernel_code
→ QuestionIntent = propriétaire fonctionnel
→ KernelCodeEngine = mécanisme d’implantation
```

## 2.4 Frontières connues

- `ValidationDominantIdeas` ne lit ni n’écrit directement dans le Blueprint.
- Ses règles sont utilisées par Gemini pendant la création des Dominant Ideas dans Taxonomy.
- Taxonomy lit `depth + domain` et écrit uniquement son triplet.
- QuestionIntent intervient après la construction intellectuelle Taxonomy et écrit `kernel_code`.

La **Section 1** doit être complète avant que la création gameplay puisse exploiter le noyau.

---

# 3. SECTION 2 — CRÉATION GAMEPLAY

## 3.1 Mission

Cette section reçoit le noyau intellectuel déjà construit et porte la création gameplay produite par `06_Phase1`.

```text
Section 1 complète
↓
06_Phase1
↓
création des 7 questions cognitives
↓
remplissage des 7 CognitiveSlots
```

## 3.2 Les 7 CognitiveSlots connus

```text
1. QCM Recognition
2. QCM Reasoning
3. QCM Deceptive Trap
4. TF Recognition True
5. TF Recognition False
6. TF Reasoning True
7. TF Reasoning False
```

## 3.3 Contenu fonctionnel de chaque CognitiveSlot

Chaque CognitiveSlot doit pouvoir porter la création gameplay correspondante :

```text
question
+
réponse(s)
+
Saviez-vous (SV)
```

Les **champs techniques exacts** à l’intérieur de chaque CognitiveSlot seront reconstruits/verrouillés avec `06_Phase1`; le Blueprint réserve cependant déjà ces 7 contrats permanents.

## 3.4 Ownership

```text
06_Phase1
→ lit le territoire / l’identité nécessaires de la Section 1
→ crée les 7 cognitifs
→ remplit les 7 CognitiveSlots
```

`06_Phase1` est le propriétaire initial du contenu gameplay des CognitiveSlots.

`07_ValidationPhase1` ne devient pas propriétaire initial de ces contenus. Son mode d’exécution exact reste à spécifier dans sa phase officielle.

---

# 4. SECTION 3 — TRADUCTION

## 4.1 Mission

Cette section porte la traduction de **tous les éléments de création gameplay** produits dans la Section 2.

```text
CognitiveSlots créés / admissibles à la traduction
↓
08_Phase2
↓
traduction de chaque élément gameplay
↓
TranslationSlots
```

## 4.2 Périmètre de traduction

Pour chacun des 7 CognitiveSlots, la traduction doit couvrir les éléments gameplay correspondants :

```text
question
+
réponse(s)
+
Saviez-vous (SV)
```

La Section 3 ne remplace jamais le contenu source de la Section 2. Elle lui ajoute ses traductions dans les emplacements prévus.

## 4.3 Ownership

```text
08_Phase2
→ lit les CognitiveSlots admissibles à la traduction
→ crée les traductions
→ remplit les TranslationSlots correspondants
```

`08_Phase2` est le propriétaire initial des TranslationSlots.

`09_ValidationPhase2` ne devient pas propriétaire initial des traductions. Son mode d’exécution exact reste à spécifier dans sa phase officielle.

---

# 5. Stations / destinations hors des trois sections de création

## 5.1 Quarantine

`10_Quarantine` n’est pas une quatrième section du Blueprint.

C’est une **station de correction contrôlée** :

```text
contenu ciblé en échec
↓
Quarantine travaille sur une copie de correction
↓
retour du contenu corrigé
↓
remplissage / remplacement contrôlé dans le Blueprint canonique
↓
reprise au point autorisé
```

Quarantine ne devient jamais propriétaire initial du slot corrigé.

## 5.2 ReadyBank

`11_ReadyBank` n’est pas une section de création du Blueprint.

```text
Blueprint canonique prêt
↓
ReadyBank stocke le noyau
↓
CURRENT_KERNEL_RECEIVED
↓
déclenchement de la création du prochain KernelBlueprint
```

`CURRENT_KERNEL_RECEIVED` n’est pas envoyé directement à KRP.

Le nouveau Blueprint créé est ensuite remis à KRP pour `fillRotation(depth, domain)`.

---

# 6. État de reconstruction par section

| Section | Périmètre | État documentaire actuel |
|---|---|---|
| **1 — Création intellectuelle** | `blueprint_id → depth/domain → triplet Taxonomy → kernel_code` | structure et ownership connus ; consolidation canonique en cours |
| **2 — Création gameplay** | 7 CognitiveSlots : questions + réponses + SV | structure fonctionnelle connue ; schéma interne exact à reconstruire avec Phase1 |
| **3 — Traduction** | traduction questions + réponses + SV des 7 CognitiveSlots | structure fonctionnelle connue ; schéma exact des TranslationSlots à reconstruire avec Phase2 |

---

# 7. À reconstruire avant canon final

- schéma technique exact des 7 CognitiveSlots ;
- états exacts des CognitiveSlots ;
- schéma exact des TranslationSlots ;
- états exacts des TranslationSlots ;
- contrat d’exécution `ValidationPhase1` ;
- contrat d’exécution `ValidationPhase2` ;
- contrat précis Quarantine → Blueprint ;
- persistance complète ;
- edge cases ;
- tests contractuels.