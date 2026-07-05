---
name: Pipeline officiel Kernel Blueprint (2026-07-04 v2)
description: Référence architecturale du moteur de création — Blueprint vivant, mécanismes exécutables par brique, vocabulaire officiel verrouillé.
---

# Pipeline officiel Kernel Blueprint — Référence architecturale v2

## Pipeline officiel (verrouillé)

```
KernelBlueprint (frame vide)
        │
        ▼
KernelRotationPlanner
        │  remplit : depth · domain_code · rotation_identifier
        ▼
Bassin Taxonomy
        │
        │  ┌─ Module domaine (depth + domain_code) ──────────────────────┐
        │  │  Chargeur de sujets : sous-domaine → jusqu'à 50 sujets      │
        │  │  Chargeur d'idées   : 5 slots, remplis SLOT PAR SLOT        │
        │  │                                                              │
        │  │    Pour chaque slot vide :                                   │
        │  │      Taxonomy propose 1 idée dominante                      │
        │  │         → KEY_LEARNING_DIRECTION (garde d'entrée)           │
        │  │         → PASS : remplir le slot                            │
        │  │         → FAIL : rejeter, proposer une autre idée           │
        │  │    Répéter jusqu'à 5 slots remplis                          │
        │  └──────────────────────────────────────────────────────────────┘
        │
        │  remplit : sub_domain · active_subject · active_dominant_idea
        │            · knowledge_frequency
        ▼
Consommation de l'idée active
        │  active_dominant_idea écrite dans Blueprint
        ▼
KEY_STRUCTURE                                                 [NEXT après KLD]
        │  remplit : structure_status · structure_reason
        ▼
QuestionIntent
        │  remplit : kernel_code · ks_hash · kld_hash · semantic_key · intent_key
        ▼
KernelBlueprint complètement rempli
        │
        ▼
PHASE 1 → Validation → Translations → READY_BANK
```

---

## Le KernelBlueprint est un support vivant

Chaque brique reçoit le Blueprint courant, lit uniquement les slots nécessaires,
écrit uniquement ses slots propriétaires, puis retourne le Blueprint.
Aucune brique ne modifie les slots d'une autre brique.
Le Blueprint est terminé **uniquement après QuestionIntent**.

---

## Mécanisme exécutable de chaque brique

### KernelBlueprint
- Crée la structure complète avec tous les slots initialisés à null/empty
- Slots : depth · domain_code · rotation_identifier · sub_domain · active_subject ·
  active_dominant_idea · knowledge_frequency · kld_status · kld_reason ·
  structure_status · structure_reason · kernel_code · ks_hash · kld_hash · semantic_key · intent_key
- Aucune logique métier. Aucun choix. Aucune validation.

---

### KernelRotationPlanner
Remplit **uniquement** : `depth` · `domain_code` · `rotation_identifier`

Mécanisme :
1. choisir le prochain depth requis (DepthNeedMatrix)
2. choisir le prochain domain_code (DomainCycle : histoire → geographie → sport → art → cuisine → science → cinema → faune)
3. générer rotation_identifier
4. écrire ces 3 valeurs dans Blueprint

Ne lit jamais Taxonomy. Ne touche jamais les chargeurs.

---

### Bassin Taxonomy (moteur Taxonomy)

Remplit **uniquement** : `sub_domain` · `active_subject` · `active_dominant_idea` · `knowledge_frequency`

#### Module domaine (un par domain_code × depth)

Chaque module est autonome et contient :
- `current_sub_domain`
- `subjects_loader` (jusqu'à 50 sujets)
- `current_subject_index`
- `ideas_loader` (5 slots d'idées dominantes)
- `current_idea_index`
- `consumed_subjects` / `consumed_sub_domains`
- `status` (active | exhausted)

Les 8 modules d'un depth avancent de manière **autonome**, coordonnés par KernelRotationPlanner.

#### Chargeur de sujets
1. si vide : créer/sélectionner jusqu'à 50 sujets conformes au depth + sub_domain
2. définir active_subject = premier sujet non consommé
3. ordre stable — ne passe jamais au sous-domaine suivant tant que les 50 sujets ne sont pas épuisés

#### Chargeur d'idées — remplissage SLOT PAR SLOT

```
tant que slots_remplis < 5 :
    Taxonomy propose 1 idée dominante conforme au depth
    KEY_LEARNING_DIRECTION.check(active_subject, idée_proposée, sub_domain, ...)
    si PASS  → remplir le prochain slot vide
    si FAIL  → rejeter l'idée, recommencer
```

**KEY_LEARNING_DIRECTION est le garde d'entrée du chargeur d'idées.**
Il n'y a pas de deuxième KLD après consommation.

#### Consommation
1. prendre l'idée à `current_idea_index`
2. écrire dans `Blueprint.active_dominant_idea`
3. envoyer Blueprint vers KEY_STRUCTURE
4. si KEY_STRUCTURE + QuestionIntent réussissent : `current_idea_index++`, consommation confirmée
5. si `current_idea_index` > 5 : sujet consommé → sujet suivant → vider ideas_loader
6. si KEY_STRUCTURE ou QuestionIntent échouent : ne pas confirmer, même idée disponible

---

### KEY_LEARNING_DIRECTION (garde d'entrée du chargeur d'idées)   [NEXT]

**Position** : à l'intérieur du cycle de remplissage du chargeur d'idées — PAS étape standalone.

Entrée : `active_subject` · `proposed_dominant_idea` · `current_sub_domain` ·
         `domain_code` · `depth` · directions déjà validées pour ce contexte (LearningDirectionRegistry)

Sortie : PASS ou FAIL + reason

Mécanisme (6 règles, déterministe) :
1. normaliser subject + idée
2. KLD-1 subject == dominant_idea → INVALID_MINIMAL_PAIR
3. KLD-2 paire directe déjà validée, même sub_domain → DIRECT_PAIR_CONTEXT_DUPLICATE
4. KLD-3 paire inversée → REVERSED_PAIR_CONTEXT_DUPLICATE
5. KLD-4 équivalence métier (EquivalenceMap) → CONCEPTUAL_COLLISION
6. KLD-5 sub_domain différent + ContextMap silencieux → CONTEXT_NOT_DISTINCT
7. KLD-6 proximité > 0.85 (SimilarityRules) → PAIR_TOO_CLOSE_TO_EXISTING
8. sinon → PASS

Ne choisit rien. Ne remplit aucun slot. Ne lit pas la DB. Ne génère aucun hash.
Appelé autant de fois que nécessaire jusqu'à remplir les 5 slots.

---

### KEY_STRUCTURE (vérificateur de structure du noyau)

**Position** : après Taxonomy + consommation d'une idée — après KLD.

Entrée : Blueprint avec depth · domain_code · sub_domain · active_subject ·
         active_dominant_idea · knowledge_frequency

Sortie : PASS ou FAIL + reason

Remplit **uniquement** : `structure_status` · `structure_reason`

Mécanisme :
1. domain_code est officiel
2. sub_domain appartient au domain_code
3. active_subject appartient au sub_domain
4. active_dominant_idea appartient au active_subject
5. découpage Domaine→Sous-domaine→Sujet→Idée est logique
6. règles du depth respectées
7. aucun noyau existant avec exactement le même décorticage

Ne choisit rien. Ne remplace pas KLD. Ne modifie pas les chargeurs.

---

### QuestionIntent (encodeur passif de l'identité)

**Position** : après KEY_STRUCTURE PASS.

Remplit **uniquement** : `kernel_code` · `ks_hash` · `kld_hash` · `semantic_key` · `intent_key`

Ne valide pas la Taxonomy. Ne choisit rien. Ne remplit aucun chargeur.
Ne génère pas de contenu Phase 1.

---

## Règle de transaction officielle

**Flow de succès :**
```
1. Rotation remplit depth + domain_code
2. Taxonomy prépare chargeurs
3. KLD valide chaque idée AVANT insertion de slot (dans Taxonomy)
4. Chargeur d'idées fournit active_dominant_idea
5. KEY_STRUCTURE valide le noyau complet
6. QuestionIntent encode l'identité
7. consommation confirmée → current_idea_index++
8. prochaine idée au prochain passage
```

**Flow d'échec KLD (pendant remplissage slot) :**
```
idée proposée → KLD FAIL → idée rejetée → Taxonomy propose autre idée → recommencer
aucun slot rempli tant que KLD ne passe pas
```

**Flow d'échec KEY_STRUCTURE (après consommation) :**
```
idée déjà dans slot → consommation tentée → KEY_STRUCTURE FAIL
→ consommation NON confirmée → stratégie de rejet/remplacement (patch dédié à venir)
```

**Flow d'échec QuestionIntent :**
```
KEY_STRUCTURE PASS → QuestionIntent FAIL
→ consommation NON confirmée → même idée retentée au prochain passage
```

---

## Impact sur les briques existantes

### TaxonomyProgressManager (actuellement FERMÉ)
Doit être **rouvert** pour intégrer l'appel à KLD pendant le remplissage du chargeur d'idées.
L'implémentation actuelle (`peekNext()` sans KLD) est incomplète selon la nouvelle architecture.
Le patch sera défini après la fermeture de KEY_LEARNING_DIRECTION.

### KLD (KEY_LEARNING_DIRECTION)
- Contrat DTO/API **inchangé** : `check(LearningDirectionInput, LearningDirectionRegistry): LearningDirectionResult`
- Position dans le pipeline **changée** : à l'intérieur du chargeur d'idées de Taxonomy, pas étape standalone
- Tests : purs unitaires, zéro DB — **inchangés**

### KEY_STRUCTURE
- Reçoit Blueprint **après** Taxonomy + consommation (pas directement après KLD standalone)
- Valide le noyau complet (sub_domain + subject + dominant_idea + depth)

### QuestionIntent
- Unchanged — toujours la dernière étape avant Phase 1

---

## Cartouche candidate (noyau final léger)

Le Blueprint final ne transporte jamais : les 8 domaines, les 50 sujets, les 5 idées non consommées, l'état du bassin.

```
depth               int
domain_code         string
sub_domain          string
subject             string       (= active_subject)
dominant_idea       string       (= active_dominant_idea)
knowledge_frequency int
```

---

## Vocabulaire officiel verrouillé

| ✔ Utiliser | ✘ Ne plus utiliser |
|---|---|
| Bassin Taxonomy | — |
| Module domaine | — |
| Chargeur de sujets | Chargeur 1 |
| Chargeur d'idées | Chargeur 2 |
| Slot d'idée dominante | — |
| Cartouche candidate | "noyau" (avant QuestionIntent) |
| Blueprint vivant | Blueprint passif |
| Sujet consommé | — |
| Sous-domaine consommé | — |
| KLD = garde d'entrée du chargeur d'idées | KLD = étape standalone post-Taxonomy |
| KEY_STRUCTURE = vérificateur de structure du noyau | — |
| 8 chargeurs autonomes coordonnés par KernelRotationPlanner | 8 machines indépendantes |

---

## État des briques

| Brique | État | Note |
|---|---|---|
| KernelRotationPlanner | FERMÉ ✔ | — |
| TaxonomyProgressManager | **À ROUVRIR** | Intégrer KLD dans chargeur d'idées |
| KEY_LEARNING_DIRECTION | **NEXT** (contrat verrouillé) | Position : dans Taxonomy |
| KEY_STRUCTURE | À venir | — |
| QuestionIntent | À venir | — |

---

## Ordre des futurs patchs (proposé)

```
PATCH B1 : LearningDirectionInput + LearningDirectionResult DTOs
PATCH B2 : LearningKnowledgeBase + LearningDirectionRegistry + KeyLearningDirection (service pur)
PATCH C  : KeyLearningDirectionTest (zéro DB)
PATCH D  : Rouvrir TaxonomyProgressManager — intégrer KLD dans chargeur d'idées
PATCH E  : KEY_STRUCTURE
PATCH F  : QuestionIntent (identité + hashes via KernelIdentifierManager)
```

**Why:** KLD doit être fermé et testé avant d'être intégré dans TaxonomyProgressManager (PATCH D).
Un service pur est plus facile à tester en isolation. Une fois KLD fermé, PATCH D l'injecte dans
le chargeur d'idées existant sans modifier le contrat public de TaxonomyProgressManager.
