# Dialyse complète — 10 noyaux

**Date :** 2026-05-22 19:11:37  
**Mode :** LIVE — modifications réelles appliquées  
**Durée :** 298.6s  

---

## Résumé global

| Noyau | Domaine | Depth | AVANT | APRÈS | Générés | Refusés | Statut |
|---|---|---|---|---|---|---|---|
| #4 | Histoire | 4 | 5/5 | 5/5 | 0 | 0 | ✅ COMPLET |
| #7 | Sport | 4 | 4/5 | 4/5 | 0 | 1 | ❌ INCOMPLET |
| #34 | Géographie | 5 | 4/5 | 5/5 | 1 | 0 | ✅ COMPLET |
| #46 | Cinéma | 5 | 2/5 | 3/5 | 1 | 2 | ❌ INCOMPLET |
| #64 | Cuisine | 6 | 3/5 | 4/5 | 1 | 1 | ❌ INCOMPLET |
| #67 | Science | 6 | 2/5 | 2/5 | 0 | 3 | ❌ INCOMPLET |
| #85 | Art | 7 | 3/5 | 4/5 | 1 | 1 | ❌ INCOMPLET |
| #100 | Histoire | 8 | 3/5 | 4/5 | 1 | 1 | ❌ INCOMPLET |
| #121 | Faune | 8 | 2/5 | 2/5 | 0 | 3 | ❌ INCOMPLET |
| #139 | Science | 9 | 3/5 | 4/5 | 1 | 1 | ❌ INCOMPLET |

**Complets : 2/10 | Incomplets : 8/10 | Variantes générées : 6 | Refusées : 13**

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

✅ Aucun problème AVANT

### 3. Variantes AVANT

**#2516 — qcm/reasoning** (`HI-D04-Q-S-08E6F`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quelle a été la principale cause du 'Siège de Constantinople' par les Ottomans en 1453 ?
> **[A]✅** Faiblesse militaire byzantine
> **SV:** Après la conquête, Mehmet II a déplacé sa capitale à Constantinople et a transformé la basilique Sainte-Sophie en 

**#2520 — qcm/deceptive_trap** (`HI-D04-Q-D-A81BE`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quand le 'Blocus de Berlin' a-t-il commencé ?
> **[A]✅** Juin 1948
> **SV:** Le blocus a conduit au pont aérien de Berlin, où des avions alliés ont livré des tonnes de fournitures à la ville c

**#2521 — true_false/reasoning** (`HI-D04-T-S-9EFD3`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** La 'Déclaration Balfour' de 1917 promettait un État palestinien indépendant.
> **[B]✅** Faux
> **SV:** La Déclaration Balfour était adressée à Lord Rothschild, un dirigeant de la communauté juive britannique, et fut un

**#2543 — true_false/recognition** (`HI-D04-T-R-9390D`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Le 'Boston Tea Party', une protestation contre la taxe sur le thé, a eu lieu en 1773.
> **[A]✅** Vrai
> **SV:** Les participants au Boston Tea Party se sont déguisés en Amérindiens pour protester contre la loi sur le thé et la d

**#8 — qcm/recognition** (`HI-D04-Q-R-4EFC3`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** En quelle année la Déclaration d'indépendance des États-Unis a-t-elle été signée ?
> **[A]✅** 1776
> **SV:** John Hancock a été le premier à signer la Déclaration d'indépendance, et sa signature est la plus grande et la plus

### 4. Variantes générées

*Aucune variante générée*
### 5. Problèmes résiduels APRÈS

✅ Aucun problème résiduel

### 6. Variantes APRÈS

- #2516 · **qcm/reasoning** · `HI-D04-Q-S-08E6F` · ✅ langues
- #2520 · **qcm/deceptive_trap** · `HI-D04-Q-D-A81BE` · ✅ langues
- #2521 · **true_false/reasoning** · `HI-D04-T-S-9EFD3` · ✅ langues
- #2543 · **true_false/recognition** · `HI-D04-T-R-9390D` · ✅ langues
- #8 · **qcm/recognition** · `HI-D04-Q-R-4EFC3` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 5/5 |
| Semantic key | `histoire-guerre-independance-americaine` |
| Problèmes résiduels | 0 |
| Statut dialyse | ✅ **COMPLETE** |

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

- ❌ 4/5 variantes présentes
- ⚠️  groupe #11 : Saviez-vous tautologique (contient « Novak Djokovic »)

### 3. Variantes AVANT

**#2523 — qcm/deceptive_trap** (`SP-D04-Q-D-5B95D`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quelle est la distance d'un marathon standard?
> **[A]✅** 42,195 kilomètres
> **SV:** La distance du marathon a été fixée en 1908 aux JO de Londres pour que le départ soit au château de Windsor et l'ar

**#2524 — true_false/recognition** (`SP-D04-T-R-78094`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Le filet de volley-ball est plus haut pour les hommes que pour les femmes.
> **[A]✅** Vrai
> **SV:** La hauteur du filet de volleyball a été modifiée plusieurs fois au fil des ans, évoluant avec les styles de jeu et l

**#2525 — true_false/reasoning** (`SP-D04-T-S-358D7`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Un joueur de tennis peut servir par le haut ou par le bas.
> **[A]✅** Vrai
> **SV:** Le service à la cuillère, un type de service par le bas, a été utilisé avec succès par des joueurs de haut niveau 

**#11 — qcm/recognition** (`SP-D04-Q-R-B92B3`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel joueur de tennis détient le record du plus grand nombre de titres en Grand Chelem en simple messieurs?
> **[A]✅** Novak Djokovic
> **SV:** Novak Djokovic a remporté son 24ème titre du Grand Chelem à l'US Open 2023, battant Daniil Medvedev en finale. Il est

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `saviez_vous_too_long` : ar saviez_vous=184 > max=140

### 5. Problèmes résiduels APRÈS

- ❌ 4/5 variantes présentes
- ⚠️  groupe #11 : Saviez-vous tautologique (contient « Novak Djokovic »)

### 6. Variantes APRÈS

- #2523 · **qcm/deceptive_trap** · `SP-D04-Q-D-5B95D` · ✅ langues
- #2524 · **true_false/recognition** · `SP-D04-T-R-78094` · ✅ langues
- #2525 · **true_false/reasoning** · `SP-D04-T-S-358D7` · ✅ langues
- #11 · **qcm/recognition** · `SP-D04-Q-R-B92B3` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 4/5 |
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

- ❌ 4/5 variantes présentes
- ⚠️  groupe #2544 : Saviez-vous tautologique (contient « L'Inde »)
- ⚠️  groupe #38 : Saviez-vous tautologique (contient « Le Nil »)

### 3. Variantes AVANT

**#2527 — true_false/reasoning** (`GE-D05-T-S-04730`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** La France métropolitaine possède plus de côtes que le Brésil.
> **[A]✅** Vrai
> **SV:** La longueur des côtes françaises est difficile à déterminer précisément, car elle dépend de la méthode de mesure

**#2544 — qcm/deceptive_trap** (`GE-D05-Q-D-4B661`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel pays compte le plus grand nombre de 'territoires disputés' au monde ?
> **[C]✅** L'Inde
> **SV:** Le différend frontalier entre l'Inde et la Chine a conduit à une guerre en 1962, et des escarmouches frontalières con

**#2546 — true_false/recognition** (`GE-D05-T-R-A0D68`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Le plus long fleuve d'Asie est le Yangtsé.
> **[A]✅** Vrai
> **SV:** Le Yangtsé abrite l'esturgeon de Chine, une espèce en danger critique d'extinction, dont l'existence remonte à l'épo

**#38 — qcm/recognition** (`GE-D05-Q-R-FA961`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le plus long fleuve d'Afrique ?
> **[A]✅** Le Nil
> **SV:** Le Nil a deux affluents principaux : le Nil Blanc et le Nil Bleu. Le Nil Bleu fournit la majorité de l'eau et du limon.

### 4. Variantes générées

**qcm/reasoning** — ✅ group_id=#2695 · `GE-D05-Q-S-96BDF` · 10 langues · source=gemini

<details>
<summary><strong>FR — détail complet</strong></summary>

**Question :** Quelle capitale africaine est la plus proche de l'équateur ?

| Clé | Réponse | Correcte |
|---|---|---|
| A | Libreville | ✅ |
| B | Nairobi |  |
| C | Kampala |  |
| D | Brazzaville |  |

**Correcte :** [A] Libreville

**Explication :** Libreville, au Gabon, est la capitale la plus proche de l'équateur.

**Saviez-vous (163ch) ⚠️ *tautologique* :** Libreville signifie 'ville libre', nommée ainsi pour les esclaves libérés qui s'y sont installés au 19e siècle. Elle est située presque directement sur l'équateur.

</details>

### 5. Problèmes résiduels APRÈS

- ⚠️  groupe #2544 : Saviez-vous tautologique (contient « L'Inde »)
- ⚠️  groupe #2695 : Saviez-vous tautologique (contient « Libreville »)
- ⚠️  groupe #38 : Saviez-vous tautologique (contient « Le Nil »)

### 6. Variantes APRÈS

- #2527 · **true_false/reasoning** · `GE-D05-T-S-04730` · ✅ langues
- #2544 · **qcm/deceptive_trap** · `GE-D05-Q-D-4B661` · ✅ langues
- #2546 · **true_false/recognition** · `GE-D05-T-R-A0D68` · ✅ langues
- #2695 · **qcm/reasoning** · `GE-D05-Q-S-96BDF` · ✅ langues
- #38 · **qcm/recognition** · `GE-D05-Q-R-FA961` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 5/5 |
| Semantic key | `geographie-african-geography` |
| Problèmes résiduels | 3 |
| Statut dialyse | ✅ **COMPLETE** |

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

- ❌ 2/5 variantes présentes
- ⚠️  groupe #50 : Saviez-vous tautologique (contient « La Liste de Schindler »)

### 3. Variantes AVANT

**#50 — qcm/recognition** (`CI-D05-Q-R-2DBE2`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel film a remporté l'Oscar du meilleur film en 1994 ?
> **[A]✅** La Liste de Schindler
> **SV:** Steven Spielberg a renoncé à son salaire pour La Liste de Schindler, le considérant comme l'argent du sang.

**#2548 — true_false/reasoning** (`CI-D05-T-S-12A12`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Le film 'Le Dictateur' de Charlie Chaplin est-il une parodie d'Adolf Hitler ?
> **[A]✅** Vrai
> **SV:** Chaplin a regretté d'avoir fait le film, car il a dit qu'il n'aurait pas pu faire une satire des nazis s'il avait connu

### 4. Variantes générées

**qcm/deceptive_trap** — ✅ group_id=#2696 · `CI-D05-Q-D-71112` · 10 langues · source=gemini

<details>
<summary><strong>FR — détail complet</strong></summary>

**Question :** Quel film a remporté l'Oscar du meilleur film en 2012 ?

| Clé | Réponse | Correcte |
|---|---|---|
| A | The Artist | ✅ |
| B | Argo |  |
| C | Birdman |  |
| D | Spotlight |  |

**Correcte :** [A] The Artist

**Explication :** The Artist, un film muet en noir et blanc, a gagné en 2012.

**Saviez-vous (151ch) ⚠️ *tautologique* :** The Artist est le deuxième film muet à remporter l'Oscar du meilleur film depuis 'Wings' en 1927, et le premier film français à gagner cette catégorie.

</details>

### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `saviez_vous_too_long` : ar saviez_vous=144 > max=140
- **true_false/recognition** — `saviez_vous_too_long` : ar saviez_vous=168 > max=140

### 5. Problèmes résiduels APRÈS

- ❌ 3/5 variantes présentes
- ⚠️  groupe #50 : Saviez-vous tautologique (contient « La Liste de Schindler »)
- ⚠️  groupe #2696 : Saviez-vous tautologique (contient « The Artist »)

### 6. Variantes APRÈS

- #50 · **qcm/recognition** · `CI-D05-Q-R-2DBE2` · ✅ langues
- #2548 · **true_false/reasoning** · `CI-D05-T-S-12A12` · ✅ langues
- #2696 · **qcm/deceptive_trap** · `CI-D05-Q-D-71112` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 3/5 |
| Semantic key | `cinema-academy-awards-best-picture` |
| Problèmes résiduels | 3 |
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

- ❌ 3/5 variantes présentes
- ⚠️  groupe #68 : Question FR trop longue (148 chars)

### 3. Variantes AVANT

**#2528 — qcm/deceptive_trap** (`CU-D06-Q-D-634FC`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est l'intérêt principal de faire tremper des légumineuses sèches avant de les cuire?
> **[A]✅** Réduire le temps de cuisson et améliorer la digestion.
> **SV:** Les Aztèques utilisaient des cendres de bois dans l'eau de trempage des haricots pour accélérer le processus et amél

**#2550 — true_false/reasoning** (`CU-D06-T-S-6B880`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Est-il vrai que le miel cristallisé est impropre à la consommation ?
> **[B]✅** Faux
> **SV:** La cristallisation du miel est un indicateur de sa qualité naturelle. Le miel artificiel, souvent additionné de sucres

**#68 — qcm/recognition** (`CU-D06-Q-R-6CF06`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel type de champignon est traditionnellement utilisé pour préparer la Duxelles, une garniture fine souvent utilisée dans la cuisine française?
> **[A]✅** Champignons de Paris
> **SV:** La duxelles a été inventée au 17ème siècle par le cuisinier du Marquis d'Uxelles, François Pierre de la Varenne, e

### 4. Variantes générées

**qcm/reasoning** — ✅ group_id=#2697 · `CU-D06-Q-S-59A0F` · 10 langues · source=gemini

<details>
<summary><strong>FR — détail complet</strong></summary>

**Question :** Quelle herbe aromatique est essentielle à la préparation des herbes de Provence ?

| Clé | Réponse | Correcte |
|---|---|---|
| A | Sarriette | ✅ |
| B | Estragon |  |
| C | Ciboulette |  |
| D | Persil |  |

**Correcte :** [A] Sarriette

**Explication :** La sarriette est un ingrédient clé des herbes de Provence, avec le thym, l'origan, le romarin et parfois la lavande.

**Saviez-vous (144ch) ⚠️ *tautologique* :** La sarriette, autrefois réputée aphrodisiaque, était interdite dans les monastères médiévaux en raison de ses prétendues propriétés stimulantes.

</details>

### 4b. Variantes REFUSÉES par QualityGuards

- **true_false/recognition** — `question_too_long` : fr question_text=111 > max=110

### 5. Problèmes résiduels APRÈS

- ❌ 4/5 variantes présentes
- ⚠️  groupe #2697 : Saviez-vous tautologique (contient « Sarriette »)
- ⚠️  groupe #68 : Question FR trop longue (148 chars)

### 6. Variantes APRÈS

- #2528 · **qcm/deceptive_trap** · `CU-D06-Q-D-634FC` · ✅ langues
- #2550 · **true_false/reasoning** · `CU-D06-T-S-6B880` · ✅ langues
- #2697 · **qcm/reasoning** · `CU-D06-Q-S-59A0F` · ✅ langues
- #68 · **qcm/recognition** · `CU-D06-Q-R-6CF06` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 4/5 |
| Semantic key | `cuisine-french-cuisine-ingredients` |
| Problèmes résiduels | 3 |
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

- ❌ 2/5 variantes présentes
- ⚠️  groupe #71 : Saviez-vous tautologique (contient « La Grande Barrière de Corail »)
- ⚠️  groupe #71 : Question FR trop longue (115 chars)
- ⚠️  groupe #2551 : Saviez-vous tautologique (contient « Diazote »)

### 3. Variantes AVANT

**#71 — qcm/recognition** (`SC-D06-Q-R-52DAF`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le nom de la plus grande structure biologique unique connue sur Terre, créée par des organismes vivants?
> **[A]✅** La Grande Barrière de Corail
> **SV:** La Grande Barrière de Corail est si vaste qu'elle est visible depuis l'espace et abrite une biodiversité marine except

**#2551 — qcm/deceptive_trap** (`SC-D06-Q-D-5D3FE`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le principal gaz utilisé pour gonfler les airbags des voitures ?
> **[A]✅** Diazote
> **SV:** Le diazote produit lors du déploiement d'un airbag est suffisamment pur pour être utilisé dans des applications indus

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `answer_too_long` : es.answer_a len=66 > max=60
- **true_false/recognition** — `saviez_vous_too_long` : it saviez_vous=230 > max=220
- **true_false/reasoning** — `question_too_long` : fr question_text=128 > max=110

### 5. Problèmes résiduels APRÈS

- ❌ 2/5 variantes présentes
- ⚠️  groupe #71 : Saviez-vous tautologique (contient « La Grande Barrière de Corail »)
- ⚠️  groupe #71 : Question FR trop longue (115 chars)
- ⚠️  groupe #2551 : Saviez-vous tautologique (contient « Diazote »)

### 6. Variantes APRÈS

- #71 · **qcm/recognition** · `SC-D06-Q-R-52DAF` · ✅ langues
- #2551 · **qcm/deceptive_trap** · `SC-D06-Q-D-5D3FE` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 2/5 |
| Semantic key | `science-coral-reef-ecosystem` |
| Problèmes résiduels | 4 |
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

- ❌ 3/5 variantes présentes
- ⚠️  groupe #89 : Question FR trop longue (141 chars)

### 3. Variantes AVANT

**#2532 — true_false/reasoning** (`AR-D07-T-S-2CB65`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Le Caravage a utilisé des modèles vivants du peuple pour ses figures religieuses.
> **[A]✅** Vrai
> **SV:** L'utilisation de modèles issus du peuple a souvent valu au Caravage des critiques pour son manque de décorum et son r�

**#2552 — qcm/reasoning** (`AR-D07-Q-S-EF05B`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel artiste a peint 'Le Cri', symbole de l'angoisse existentielle moderne?
> **[A]✅** Edvard Munch
> **SV:** Munch a réalisé plusieurs versions du 'Cri', en peinture et en lithographie. Il a décrit l'inspiration du tableau com

**#89 — qcm/recognition** (`AR-D07-Q-R-71D08`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel artiste est célèbre pour avoir peint 'Le Radeau de la Méduse', une œuvre monumentale représentant les conséquences d'un naufrage ?
> **[A]✅** Théodore Géricault
> **SV:** Géricault a réalisé de nombreuses études préparatoires en interrogeant des survivants et en étudiant des cadavres 

### 4. Variantes générées

**true_false/recognition** — ✅ group_id=#2698 · `AR-D07-T-R-B322F` · 10 langues · source=gemini

<details>
<summary><strong>FR — détail complet</strong></summary>

**Question :** Eugène Delacroix a-t-il peint 'Le Serment du Jeu de paume' ?

| Clé | Réponse | Correcte |
|---|---|---|
| A | Vrai |  |
| B | Faux | ✅ |

**Correcte :** [B] Faux

**Explication :** Non, c'est Jacques-Louis David qui a peint 'Le Serment du Jeu de paume'. Delacroix est célèbre pour 'La Liberté guidant le peuple'.

**Saviez-vous (124ch) :** Delacroix a utilisé des modèles réels pour 'La Liberté guidant le peuple', dont sa propre compagne pour incarner la Liberté.

</details>

### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/deceptive_trap** — `question_too_long` : ar question_text=80 > max=75

### 5. Problèmes résiduels APRÈS

- ❌ 4/5 variantes présentes
- ⚠️  groupe #89 : Question FR trop longue (141 chars)

### 6. Variantes APRÈS

- #2532 · **true_false/reasoning** · `AR-D07-T-S-2CB65` · ✅ langues
- #2552 · **qcm/reasoning** · `AR-D07-Q-S-EF05B` · ✅ langues
- #2698 · **true_false/recognition** · `AR-D07-T-R-B322F` · ✅ langues
- #89 · **qcm/recognition** · `AR-D07-Q-R-71D08` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 4/5 |
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

- ❌ 3/5 variantes présentes

### 3. Variantes AVANT

**#2533 — true_false/recognition** (`HI-D08-T-R-2EF2E`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** La 'Révolution de velours' en Tchécoslovaquie en 1989 fut un conflit violent.
> **[B]✅** Faux
> **SV:** La 'Révolution de velours' a été déclenchée par une manifestation étudiante pacifique brutalement réprimée par l

**#2553 — qcm/deceptive_trap** (`HI-D08-Q-D-9604E`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quand la 'Nuit de Cristal', une série d'attaques antisémites en Allemagne, a-t-elle eu lieu?
> **[A]✅** Novembre 1938
> **SV:** Bien que présentée comme une réaction spontanée à l'assassinat d'un diplomate allemand, la Nuit de Cristal fut en r

**#104 — qcm/recognition** (`HI-D08-Q-R-94DFC`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel événement a marqué le début de la Première Guerre mondiale?
> **[A]✅** L'assassinat de l'archiduc François-Ferdinand d'Autriche
> **SV:** L'archiduc François-Ferdinand était l'héritier du trône austro-hongrois et son assassinat a été perpétré par Gav

### 4. Variantes générées

**qcm/reasoning** — ✅ group_id=#2699 · `HI-D08-Q-S-0CA6C` · 10 langues · source=gemini

<details>
<summary><strong>FR — détail complet</strong></summary>

**Question :** En quelle année la bataille de la Marne a-t-elle arrêté l'avance allemande sur Paris?

| Clé | Réponse | Correcte |
|---|---|---|
| A | 1914 | ✅ |
| B | 1915 |  |
| C | 1916 |  |
| D | 1917 |  |

**Correcte :** [A] 1914

**Explication :** La première bataille de la Marne a eu lieu en septembre 1914.

**Saviez-vous (161ch) :** Les taxis parisiens ont été réquisitionnés pour transporter des troupes sur le front de la Marne, symbolisant l'effort national pour arrêter l'avancée allemande.

</details>

### 4b. Variantes REFUSÉES par QualityGuards

- **true_false/reasoning** — `concept_family_share` : 0.67 > 0.40

### 5. Problèmes résiduels APRÈS

- ❌ 4/5 variantes présentes

### 6. Variantes APRÈS

- #2533 · **true_false/recognition** · `HI-D08-T-R-2EF2E` · ✅ langues
- #2553 · **qcm/deceptive_trap** · `HI-D08-Q-D-9604E` · ✅ langues
- #2699 · **qcm/reasoning** · `HI-D08-Q-S-0CA6C` · ✅ langues
- #104 · **qcm/recognition** · `HI-D08-Q-R-94DFC` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 4/5 |
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

- ❌ 2/5 variantes présentes

### 3. Variantes AVANT

**#2538 — true_false/reasoning** (`FA-D08-T-S-05543`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Un manchot empereur femelle ne pond qu'un seul œuf par saison de reproduction.
> **[A]✅** Vrai
> **SV:** La période d'incubation de l'œuf unique du manchot empereur dure environ 64 jours, pendant lesquels le mâle ne mange 

**#125 — qcm/recognition** (`FA-D08-Q-R-EF9B3`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le seul oiseau connu pour avoir des ailes munies de griffes fonctionnelles?
> **[A]✅** Le hoazin
> **SV:** Les jeunes hoazins utilisent leurs griffes sur les ailes pour grimper aux arbres afin d'échapper aux prédateurs, une c

### 4. Variantes générées

*Aucune variante générée*
### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `question_too_long` : ar question_text=91 > max=75
- **qcm/deceptive_trap** — `question_too_long` : it question_text=111 > max=110
- **true_false/recognition** — `concept_family_share` : 1.00 > 0.40

### 5. Problèmes résiduels APRÈS

- ❌ 2/5 variantes présentes

### 6. Variantes APRÈS

- #2538 · **true_false/reasoning** · `FA-D08-T-S-05543` · ✅ langues
- #125 · **qcm/recognition** · `FA-D08-Q-R-EF9B3` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 2/5 |
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

- ❌ 3/5 variantes présentes
- ⚠️  groupe #143 : Saviez-vous tautologique (contient « Le Grand Mur d'Hercule-Couronne boréale »)

### 3. Variantes AVANT

**#2540 — qcm/deceptive_trap** (`SC-D09-Q-D-A3E6B`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quelle est la principale raison pour laquelle l'eau mouille ?
> **[A]✅** La tension superficielle de l'eau est faible.
> **SV:** La tension superficielle de l'eau est due à la cohésion entre les molécules d'eau. Les gouttes d'eau sont sphériques

**#2541 — true_false/recognition** (`SC-D09-T-R-C02FF`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** La Lune est en rotation synchrone avec la Terre, nous montrant toujours la même face.
> **[A]✅** Vrai
> **SV:** Bien que nous ne voyions jamais la 'face cachée' directement, environ 59% de la surface lunaire est visible depuis la T

**#143 — qcm/recognition** (`SC-D09-Q-R-E96D2`) · ready_bank · langues: ar, de, el, en, es, fr, it, pt, ru, zh

> **Q (FR):** Quel est le nom donné à la plus grande structure connue dans l'univers?
> **[A]✅** Le Grand Mur d'Hercule-Couronne boréale
> **SV:** Le Grand Mur d'Hercule-Couronne boréale est si vaste que la lumière met environ 10 milliards d'années pour le travers

### 4. Variantes générées

**true_false/reasoning** — ✅ group_id=#2702 · `SC-D09-T-S-FFE20` · 10 langues · source=gemini

<details>
<summary><strong>FR — détail complet</strong></summary>

**Question :** Les filaments galactiques représentent-ils la plus grande structure connue dans l'univers ?

| Clé | Réponse | Correcte |
|---|---|---|
| A | Vrai | ✅ |
| B | Faux |  |

**Correcte :** [A] Vrai

**Explication :** Les filaments galactiques sont les plus grandes structures connues, s'étendant sur des centaines de millions d'années-lumière.

**Saviez-vous (176ch) :** L'un des plus grands filaments connus, le Grand Mur de Sloan, mesure environ 1,38 milliard d'années-lumière de long. La lumière met donc 1,38 milliard d'années à le traverser !

</details>

### 4b. Variantes REFUSÉES par QualityGuards

- **qcm/reasoning** — `answer_too_long` : fr.answer_a len=71 > max=60

### 5. Problèmes résiduels APRÈS

- ❌ 4/5 variantes présentes
- ⚠️  groupe #143 : Saviez-vous tautologique (contient « Le Grand Mur d'Hercule-Couronne boréale »)

### 6. Variantes APRÈS

- #2540 · **qcm/deceptive_trap** · `SC-D09-Q-D-A3E6B` · ✅ langues
- #2541 · **true_false/recognition** · `SC-D09-T-R-C02FF` · ✅ langues
- #2702 · **true_false/reasoning** · `SC-D09-T-S-FFE20` · ✅ langues
- #143 · **qcm/recognition** · `SC-D09-Q-R-E96D2` · ✅ langues

### 7. Résultat final

| | |
|---|---|
| Variantes présentes | 4/5 |
| Semantic key | `science-astronomy-cosmic-structures` |
| Problèmes résiduels | 2 |
| Statut dialyse | ❌ **INCOMPLETE** |

---

*Généré par `questions:bank:dialyse:run-test` le 2026-05-22 19:11:37*
