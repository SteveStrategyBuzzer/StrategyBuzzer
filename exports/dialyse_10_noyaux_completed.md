# Dialyse complète — 10 noyaux

**Date :** 2026-05-22 15:20:43  
**Mode :** LIVE — modifications réelles appliquées  
**Durée :** 392.2s  

---

## Résumé global

| Noyau | Domaine | Depth | AVANT | APRÈS | Générés | Refusés | Statut |
|---|---|---|---|---|---|---|---|
| #4 | Histoire | 4 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #7 | Sport | 4 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #34 | Géographie | 5 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #46 | Cinéma | 5 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #64 | Cuisine | 6 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #67 | Science | 6 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #85 | Art | 7 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #100 | Histoire | 8 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #121 | Faune | 8 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |
| #139 | Science | 9 | 1/5 | 1/5 | 0 | 4 | ❌ INCOMPLET |

**Complets : 0/10 | Incomplets : 10/10 | Variantes générées : 0 | Refusées : 40**

---

## NOYAU 1 — #4 · Histoire · depth 4

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `histoire-guerre-independance-americaine` |
| subject | *(null)* | Guerre d'Indépendance américaine |
| angle_large | *(null)* | Conflits historiques |
| micro_angle | *(null)* | Chronologie et dates clés |
| answer_target | *(null)* | Année de la Déclaration d'Indépendance |
| potential_trap | *(null)* | Confusion 1776 vs 1783 (traité de Paris) |
| concept_family | guerre-independance-americaine | guerre-independance-americaine *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes

### 3. Variantes AVANT

**#8 — qcm/recognition** (`HI-D04-Q-R-4EFC3`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** En quelle année la Déclaration d'indépendance des États-Unis a-t-elle été signée ?
> **[A]✅** 1776
> **SV:** John Hancock a été le premier à signer la Déclaration d'indépendance, et sa signature est la plus grande et la plus

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.030 with question context (< 0.04) — likely cross-contaminated
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","keyIndex":0,"status":429,"message":"429 You exceeded your current quota, please check your plan and billing details. For more information on this error, read the docs: https://platform.openai.com/docs/guides/error-codes/api-errors."}]))
- **true_false/reasoning** — `missing_translations` : missing language: en

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes

### 6. Variantes APRÈS

- #8 · **qcm/recognition** · `HI-D04-Q-R-4EFC3` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `histoire-guerre-independance-americaine` |
| Problèmes résiduels | 1 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 2 — #7 · Sport · depth 4

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `sport-tennis-grand-slam-records` |
| subject | *(null)* | Records du Grand Chelem (tennis) |
| angle_large | *(null)* | Records et statistiques sportives |
| micro_angle | *(null)* | Titres en simple masculin |
| answer_target | *(null)* | Nombre de titres Grand Chelem |
| potential_trap | *(null)* | Confusion Federer/Djokovic/Nadal selon l'année |
| concept_family | tennis-grand-slam-records | tennis-grand-slam-records *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes
- ⚠️  groupe #11 : Saviez-vous tautologique (contient « Novak Djokovic »)

### 3. Variantes AVANT

**#11 — qcm/recognition** (`SP-D04-Q-R-B92B3`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel joueur de tennis détient le record du plus grand nombre de titres en Grand Chelem en simple messieurs?
> **[A]✅** Novak Djokovic
> **SV:** Novak Djokovic a remporté son 24ème titre du Grand Chelem à l'US Open 2023, battant Daniil Medvedev en finale. Il est

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `correct_answer_overused` : correct answer '3' already appears 18× in sub_domain 'Sport' (max 12)
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))
- **true_false/reasoning** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes
- ⚠️  groupe #11 : Saviez-vous tautologique (contient « Novak Djokovic »)

### 6. Variantes APRÈS

- #11 · **qcm/recognition** · `SP-D04-Q-R-B92B3` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `sport-tennis-grand-slam-records` |
| Problèmes résiduels | 2 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 3 — #34 · Géographie · depth 5

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `geographie-african-geography` |
| subject | *(null)* | Géographie africaine |
| angle_large | *(null)* | Géographie continentale |
| micro_angle | *(null)* | Pays et capitales africaines |
| answer_target | *(null)* | Nom de pays ou capitale africaine |
| potential_trap | *(null)* | Pays aux capitales non-intuitives |
| concept_family | african-geography | african-geography *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes
- ⚠️  groupe #38 : Saviez-vous tautologique (contient « Le Nil »)

### 3. Variantes AVANT

**#38 — qcm/recognition** (`GE-D05-Q-R-FA961`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le plus long fleuve d'Afrique ?
> **[A]✅** Le Nil
> **SV:** Le Nil a deux affluents principaux : le Nil Blanc et le Nil Bleu. Le Nil Bleu fournit la majorité de l'eau et du limon.

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `correct_answer_overused` : correct answer 'Indonésie' already appears 27× in sub_domain 'Géographie' (max 12)
- **qcm/deceptive_trap** — `correct_answer_overused` : correct answer 'Indonésie' already appears 27× in sub_domain 'Géographie' (max 12)
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","keyIndex":0,"status":429,"message":"429 You exceeded your current quota, please check your plan and billing details. For more information on this error, read the docs: https://platform.openai.com/docs/guides/error-codes/api-errors."}]))
- **true_false/reasoning** — `missing_translations` : missing language: en

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes
- ⚠️  groupe #38 : Saviez-vous tautologique (contient « Le Nil »)

### 6. Variantes APRÈS

- #38 · **qcm/recognition** · `GE-D05-Q-R-FA961` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `geographie-african-geography` |
| Problèmes résiduels | 2 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 4 — #46 · Cinéma · depth 5

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `cinema-academy-awards-best-picture` |
| subject | *(null)* | Oscars — Meilleur Film |
| angle_large | *(null)* | Récompenses cinématographiques |
| micro_angle | *(null)* | Films primés années 2000–2020 |
| answer_target | *(null)* | Titre du film lauréat |
| potential_trap | *(null)* | Confusion film nominé vs film lauréat |
| concept_family | academy-awards-best-picture | academy-awards-best-picture *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes
- ⚠️  groupe #50 : Saviez-vous tautologique (contient « La Liste de Schindler »)

### 3. Variantes AVANT

**#50 — qcm/recognition** (`CI-D05-Q-R-2DBE2`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel film a remporté l'Oscar du meilleur film en 1994 ?
> **[A]✅** La Liste de Schindler
> **SV:** Steven Spielberg a renoncé à son salaire pour La Liste de Schindler, le considérant comme l'argent du sang.

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))
- **true_false/reasoning** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes
- ⚠️  groupe #50 : Saviez-vous tautologique (contient « La Liste de Schindler »)

### 6. Variantes APRÈS

- #50 · **qcm/recognition** · `CI-D05-Q-R-2DBE2` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `cinema-academy-awards-best-picture` |
| Problèmes résiduels | 2 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 5 — #64 · Cuisine · depth 6

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `cuisine-french-cuisine-ingredients` |
| subject | *(null)* | Ingrédients cuisine française |
| angle_large | *(null)* | Techniques et ingrédients culinaires |
| micro_angle | *(null)* | Herbes et épices régionales |
| answer_target | *(null)* | Ingrédient ou technique culinaire |
| potential_trap | *(null)* | Ingrédients similaires de régions différentes |
| concept_family | french-cuisine-ingredients | french-cuisine-ingredients *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes
- ⚠️  groupe #68 : Question FR trop longue (148 chars)

### 3. Variantes AVANT

**#68 — qcm/recognition** (`CU-D06-Q-R-6CF06`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel type de champignon est traditionnellement utilisé pour préparer la Duxelles, une garniture fine souvent utilisée dans la cuisine française?
> **[A]✅** Champignons de Paris
> **SV:** La duxelles a été inventée au 17ème siècle par le cuisinier du Marquis d'Uxelles, François Pierre de la Varenne, e

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","keyIndex":0,"status":429,"message":"429 You exceeded your current quota, please check your plan and billing details. For more information on this error, read the docs: https://platform.openai.com/docs/guides/error-codes/api-errors."}]))
- **true_false/reasoning** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes
- ⚠️  groupe #68 : Question FR trop longue (148 chars)

### 6. Variantes APRÈS

- #68 · **qcm/recognition** · `CU-D06-Q-R-6CF06` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `cuisine-french-cuisine-ingredients` |
| Problèmes résiduels | 2 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 6 — #67 · Science · depth 6

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `science-coral-reef-ecosystem` |
| subject | *(null)* | Écosystème des récifs coralliens |
| angle_large | *(null)* | Écosystèmes marins |
| micro_angle | *(null)* | Symbioses et organismes clés |
| answer_target | *(null)* | Organisme ou relation écologique |
| potential_trap | *(null)* | Confusion corail / anémone / zooxanthelles |
| concept_family | coral-reef-ecosystem | coral-reef-ecosystem *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes
- ⚠️  groupe #71 : Saviez-vous tautologique (contient « La Grande Barrière de Corail »)
- ⚠️  groupe #71 : Question FR trop longue (115 chars)

### 3. Variantes AVANT

**#71 — qcm/recognition** (`SC-D06-Q-R-52DAF`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le nom de la plus grande structure biologique unique connue sur Terre, créée par des organismes vivants?
> **[A]✅** La Grande Barrière de Corail
> **SV:** La Grande Barrière de Corail est si vaste qu'elle est visible depuis l'espace et abrite une biodiversité marine except

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `missing_translations` : missing language: en
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.014 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))
- **true_false/reasoning** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes
- ⚠️  groupe #71 : Saviez-vous tautologique (contient « La Grande Barrière de Corail »)
- ⚠️  groupe #71 : Question FR trop longue (115 chars)

### 6. Variantes APRÈS

- #71 · **qcm/recognition** · `SC-D06-Q-R-52DAF` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `science-coral-reef-ecosystem` |
| Problèmes résiduels | 3 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 7 — #85 · Art · depth 7

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `art-french-romanticism` |
| subject | *(null)* | Romantisme français (peinture) |
| angle_large | *(null)* | Mouvements artistiques européens |
| micro_angle | *(null)* | Peintres et œuvres majeures |
| answer_target | *(null)* | Artiste ou œuvre du romantisme |
| potential_trap | *(null)* | Confusion romantisme / impressionnisme |
| concept_family | french-romanticism | french-romanticism *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes
- ⚠️  groupe #89 : Question FR trop longue (141 chars)

### 3. Variantes AVANT

**#89 — qcm/recognition** (`AR-D07-Q-R-71D08`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel artiste est célèbre pour avoir peint 'Le Radeau de la Méduse', une œuvre monumentale représentant les conséquences d'un naufrage ?
> **[A]✅** Théodore Géricault
> **SV:** Géricault a réalisé de nombreuses études préparatoires en interrogeant des survivants et en étudiant des cadavres 

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `correct_answer_overused` : correct answer 'Pablo Picasso' already appears 17× in sub_domain 'Art' (max 12)
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","keyIndex":0,"status":429,"message":"429 You exceeded your current quota, please check your plan and billing details. For more information on this error, read the docs: https://platform.openai.com/docs/guides/error-codes/api-errors."}]))
- **true_false/reasoning** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes
- ⚠️  groupe #89 : Question FR trop longue (141 chars)

### 6. Variantes APRÈS

- #89 · **qcm/recognition** · `AR-D07-Q-R-71D08` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `art-french-romanticism` |
| Problèmes résiduels | 2 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 8 — #100 · Histoire · depth 8

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `histoire-world-war-one` |
| subject | *(null)* | Première Guerre mondiale |
| angle_large | *(null)* | Guerres mondiales |
| micro_angle | *(null)* | Batailles décisives 1914–1918 |
| answer_target | *(null)* | Événement ou date de la Grande Guerre |
| potential_trap | *(null)* | Confusion WWI / WWII pour des batailles similaires |
| concept_family | world-war-one | world-war-one *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes

### 3. Variantes AVANT

**#104 — qcm/recognition** (`HI-D08-Q-R-94DFC`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel événement a marqué le début de la Première Guerre mondiale?
> **[A]✅** L'assassinat de l'archiduc François-Ferdinand d'Autriche
> **SV:** L'archiduc François-Ferdinand était l'héritier du trône austro-hongrois et son assassinat a été perpétré par Gav

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 3"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))
- **true_false/reasoning** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes

### 6. Variantes APRÈS

- #104 · **qcm/recognition** · `HI-D08-Q-R-94DFC` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `histoire-world-war-one` |
| Problèmes résiduels | 1 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 9 — #121 · Faune · depth 8

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `faune-avian-anatomy-adaptation` |
| subject | *(null)* | Anatomie et adaptations aviaires |
| angle_large | *(null)* | Biologie des oiseaux |
| micro_angle | *(null)* | Structures physiques et vol |
| answer_target | *(null)* | Structure anatomique ou adaptation |
| potential_trap | *(null)* | Adaptation partagée avec reptiles (évolution) |
| concept_family | avian-anatomy-adaptation | avian-anatomy-adaptation *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes

### 3. Variantes AVANT

**#125 — qcm/recognition** (`FA-D08-Q-R-EF9B3`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le seul oiseau connu pour avoir des ailes munies de griffes fonctionnelles?
> **[A]✅** Le hoazin
> **SV:** Les jeunes hoazins utilisent leurs griffes sur les ailes pour grimper aux arbres afin d'échapper aux prédateurs, une c

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","keyIndex":0,"status":429,"message":"429 You exceeded your current quota, please check your plan and billing details. For more information on this error, read the docs: https://platform.openai.com/docs/guides/error-codes/api-errors."}]))
- **true_false/reasoning** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 4"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes

### 6. Variantes APRÈS

- #125 · **qcm/recognition** · `FA-D08-Q-R-EF9B3` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `faune-avian-anatomy-adaptation` |
| Problèmes résiduels | 1 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

## NOYAU 10 — #139 · Science · depth 9

### 1. Métadonnées noyau

| Champ | AVANT | APRÈS |
|---|---|---|
| semantic_key | *(null)* | `science-astronomy-cosmic-structures` |
| subject | *(null)* | Structures cosmiques (astronomie) |
| angle_large | *(null)* | Cosmologie et astrophysique |
| micro_angle | *(null)* | Galaxies, amas et filaments |
| answer_target | *(null)* | Structure cosmique ou propriété |
| potential_trap | *(null)* | Confusion étoile / planète / galaxie à grande échelle |
| concept_family | astronomy-cosmic-structures | astronomy-cosmic-structures *(inchangé)* |

### 2. Problèmes détectés AVANT

- ⚠️  semantic_key non définie
- ⚠️  subject / angle_large vides
- ❌ 1/5 variantes présentes
- ⚠️  groupe #143 : Saviez-vous tautologique (contient « Le Grand Mur d'Hercule-Couronne boréale »)

### 3. Variantes AVANT

**#143 — qcm/recognition** (`SC-D09-Q-R-E96D2`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le nom donné à la plus grande structure connue dans l'univers?
> **[A]✅** Le Grand Mur d'Hercule-Couronne boréale
> **SV:** Le Grand Mur d'Hercule-Couronne boréale est si vaste que la lumière met environ 10 milliards d'années pour le travers

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **qcm/deceptive_trap** — `saviez_vous_off_topic` : saviez_vous Jaccard overlap=0.000 with question context (< 0.04) — likely cross-contaminated
- **true_false/recognition** — `missing_translations` : missing language: en
- **true_false/reasoning** — `generate` : router http error (master) (all_providers_exhausted: AI router: all providers exhausted ([{"provider":"gemini","keyIndex":0,"status":"invalid_contract","message":"translations[fr] expected 2 answers (true_false), got 3"},{"provider":"openai","error":"all keys quarantined or unavailable"}]))

### 5. Problèmes résiduels APRÈS

- ❌ 1/5 variantes présentes
- ⚠️  groupe #143 : Saviez-vous tautologique (contient « Le Grand Mur d'Hercule-Couronne boréale »)

### 6. Variantes APRÈS

- #143 · **qcm/recognition** · `SC-D09-Q-R-E96D2` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 1/5 |
| Semantic key | `science-astronomy-cosmic-structures` |
| Problèmes résiduels | 2 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

*Généré par `questions:bank:dialyse:run-test` le 2026-05-22 15:20:43*
