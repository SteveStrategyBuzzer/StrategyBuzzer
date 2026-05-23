# StrategyBuzzer — Contrat Cognitif du Question Engine

> **Source de vérité officielle.**
> Ce document gouverne toute génération, validation et admission de questions
> dans la banque persistante de StrategyBuzzer.
> Aucun patch de génération massive ne peut être déployé sans être conforme
> à l'intégralité de ces règles.
>
> **Version :** 1.0 — 2026-05-22
> **Issu de :** Dialyse 10 noyaux P0→P5 + analyse correct_answer_overused
> **Fichiers en vigueur :** `question-api.js`, `QualityGuards.php`,
> `config/question_bank_profiles.php`, `BankAIGenerator.php`

---

## Table des matières

1. [Les 5 variantes cognitives](#1-les-5-variantes-cognitives)
2. [Différence réelle : recognition / reasoning / deceptive_trap](#2-différence-réelle-recognition--reasoning--deceptive_trap)
3. [Règles Vrai/Faux](#3-règles-vraifaux)
4. [Règles gameplay-first](#4-règles-gameplay-first)
5. [Budgets de longueur par langue](#5-budgets-de-longueur-par-langue)
6. [Règles Saviez-vous](#6-règles-saviez-vous)
7. [Règles anti-répétition cognitive](#7-règles-anti-répétition-cognitive)
8. [Définition d'un Cognitive Path](#8-définition-dun-cognitive-path)
9. [Règles de diversité réelle](#9-règles-de-diversité-réelle)
10. [Faux patterns interdits](#10-faux-patterns-interdits)
11. [Exemples bons vs mauvais](#11-exemples-bons-vs-mauvais)
12. [Règles mobile-first](#12-règles-mobile-first)
13. [Règles Duo buzzability](#13-règles-duo-buzzability)
14. [Règles deceptive_trap propres](#14-règles-deceptive_trap-propres)
15. [Règles de Semantic Nucleus Stability](#15-règles-de-semantic-nucleus-stability)

---

## 1. Les 5 variantes cognitives

Chaque **noyau** (`question_intent`) doit produire exactement **5 variantes** avant
d'être marqué `dialysis_status = complete`. Ces 5 variantes couvrent deux
`question_type` et trois `cognitive_type` :

| # | question_type | cognitive_type  | Code court   | Rôle gameplay                                  |
|---|---------------|-----------------|--------------|------------------------------------------------|
| 1 | `qcm`         | `recognition`   | QCM-R        | Ancrage : fait pur, score rapide               |
| 2 | `qcm`         | `reasoning`     | QCM-L        | Profondeur : déduction sous pression           |
| 3 | `qcm`         | `deceptive_trap`| QCM-D        | Tension : piège classique, split de l'audience |
| 4 | `true_false`  | `recognition`   | VF-R         | Rythme : décision binaire rapide               |
| 5 | `true_false`  | `reasoning`     | VF-L         | Nuance : vrai/faux qui exige une justification |

**Règles d'existence :**
- Une variante n'est admise en banque que si elle passe **tous** les guards.
- Un noyau avec < 5 variantes reste `incomplete` — il continue d'être alimenté
  par le bank worker jusqu'à complétude.
- Les 5 variantes d'un noyau **doivent rester dans le noyau** — même sujet,
  même `answer_target`, même axe sémantique. Voir §15.

**Mixes cognitifs par niveau (Solo) :**

| Niveau joueur | recognition | deceptive_trap | reasoning |
|---|---|---|---|
| 1–99 (étudiant) | 50 % | 30 % | 20 % |
| Boss battles | 40–60 % | 10–40 % | 20–30 % |

---

## 2. Différence réelle : recognition / reasoning / deceptive_trap

### 2.1 Recognition

**Définition :** rappel direct d'un fait stocké en mémoire. Le joueur sait ou
ne sait pas. Aucune opération cognitive intermédiaire requise.

**Marqueurs :** "Quelle est…", "Qui a…", "En quelle année…", "Lequel de ces…"

**Test de validation :** si la question peut être répondue par pure mémorisation
sans aucun raisonnement, c'est du recognition.

**Seuil depth :** fonctionne à tous les depths (1–10).

```
✅ BON  : "Qui a peint 'Les Tournesols' ?"
✅ BON  : "Quelle est la capitale du Japon ?"
✅ BON  : "Lequel de ces pays possède le plus grand nombre de volcans actifs ?"
```

### 2.2 Reasoning

**Définition :** la bonne réponse nécessite une déduction, comparaison, calcul
léger, ou enchaînement logique. Le joueur doit *construire* la réponse, pas
simplement la *réciter*.

**Marqueurs obligatoires dans la question FR :**
`parce que`, `car `, `pourquoi`, `résultat`, `conséquence`, `entraîne`,
`permet`, `raison`, `donc`, `ainsi`, `en raison de`, `ce qui explique`,
`sachant que`, `si … alors`, `quel est le minimum/maximum`.

**Test de validation :** si quelqu'un qui connaît le domaine mais PAS la réponse
exacte peut raisonner et arriver à la bonne réponse → c'est du reasoning.
Si c'est uniquement mémorisation → c'est du recognition mal étiqueté.

**Seuil depth :** le reasoning devient pertinent à partir de depth 4.
En dessous de depth 4, le reasoning doit rester très simple.

```
✅ BON  : "Lequel de ces pays a le plus grand nombre d'îles volcaniques,
           en raison de sa position sur la ceinture de feu du Pacifique ?"
✅ BON  : "Au tennis, si un joueur gagne les 4 premiers points sans que son
           adversaire en marque, quel est le nombre minimal de balles jouées ?"
✅ BON  : "Quel pays possède le plus grand nombre de mangroves car il combine
           une longue côte tropicale et un archipel dense ?"

❌ MAUVAIS : "Quel artiste a peint 'Guernica' ?"  [étiqueté reasoning → c'est recognition]
❌ MAUVAIS : "Pourquoi Picasso est-il célèbre ?"  [trop vague, pas de raisonnement précis]
```

### 2.3 Deceptive_trap

**Définition :** la question est conçue pour provoquer une erreur prévisible.
Les distracteurs sont des **confusions classiques documentées**, pas des options
arbitraires. Le joueur qui connaît *presque* le sujet se fait piéger.

**Principe fondamental :** le piège doit être *identifiable après coup* —
le joueur doit pouvoir dire "Ah oui, je confondais avec X, c'est la confusion
classique entre A et B."

**Test de validation :** si les distracteurs ne provoquent pas une confusion
réelle chez quelqu'un qui connaît approximativement le domaine → ce n'est pas
un deceptive_trap, c'est un QCM ordinaire mal catégorisé.

**Seuil depth :** le deceptive_trap est plus efficace à depth 5+.
En dessous de depth 4, la confusion doit être très universelle.

Voir §14 pour les règles détaillées.

---

## 3. Règles Vrai/Faux

### 3.1 Contrat de structure (obligatoire, guard enforcement)

```
answer_a = libellé "Vrai" dans la langue cible     (ex: "Vrai", "True", "Verdadero")
answer_b = libellé "Faux" dans la langue cible     (ex: "Faux", "False", "Falso")
answer_c = null
answer_d = null
correct_answer_key = "A" si l'énoncé est vrai, "B" si l'énoncé est faux
```

**Ce contrat est non-négociable.** `answer_c` et `answer_d` doivent être
`null` — pas `""`, pas `"null"` (string), pas une valeur quelconque.

**Libellés attendus par langue :**

| Langue | Vrai        | Faux      |
|--------|-------------|-----------|
| fr     | Vrai        | Faux      |
| en     | True        | False     |
| es     | Verdadero   | Falso     |
| de     | Wahr        | Falsch    |
| it     | Vero        | Falso     |
| pt     | Verdadeiro  | Falso     |
| ru     | Верно       | Неверно   |
| zh     | 真          | 假        |
| ar     | صحيح        | خطأ       |
| el     | Αληθές      | Ψευδές    |

### 3.2 Règles de formulation VF

**VF-recognition :**
- Énoncé factuel direct sur le sujet du noyau
- La vérité ou fausseté doit être tranchée, pas nuancée
- Longueur : plus courte que QCM, car pas de choix à comparer

```
✅ BON  : "L'Indonésie est le pays qui possède le plus grand nombre de volcans actifs."
✅ BON  : "Picasso a co-fondé le mouvement cubiste avec Georges Braque." [VRAI]
✅ BON  : "Le Déjeuner sur l'herbe a été peint par Claude Monet." [FAUX — c'est Manet]
```

**VF-reasoning :**
- L'énoncé inclut une causalité, une conséquence, ou une déduction
- Le joueur doit évaluer si le raisonnement présenté est correct
- La fausseté peut venir d'une erreur dans la chaîne logique

```
✅ BON  : "L'Indonésie possède autant de volcans actifs car elle est entièrement
           située sur la ceinture de feu du Pacifique." [FAUX — pas 'entièrement']
✅ BON  : "Au tennis, un joueur doit gagner deux points consécutifs après l'égalité
           pour remporter le jeu." [VRAI]
❌ MAUVAIS : "Picasso était espagnol." [VF-reasoning sans raisonnement → c'est VF-recognition]
```

### 3.3 Ce qui est interdit en VF

- Formuler un énoncé négatif ("Il est faux que…") — tautologique
- Utiliser "parfois", "souvent", "généralement" — vrai/faux non tranchable
- Énoncé dont la vérité dépend du contexte historique (sauf si précisé)
- Énoncé trivial dont la réponse est évidente sans aucune connaissance du sujet

---

## 4. Règles gameplay-first

### 4.1 Principe

Chaque question doit être **jouable** avant d'être savante. La lisibilité sous
pression temporelle prime sur l'exhaustivité encyclopédique.

**Contrainte temps :** le joueur dispose de ≤ 30 secondes par question en Solo,
moins en Duo (buzzer). Une question illisible en 5 secondes est une mauvaise
question, quelle que soit sa qualité factuelle.

### 4.2 Formulation positive obligatoire

**Interdit absolu — keywords détectés automatiquement :**
```
"n'est pas", "ne sont pas", "ne fut pas", "ne peut pas", "ne doit pas",
"n'a pas", "n'était pas", "jamais", " sauf ", " excepté ", " hormis ",
" à l'exception", "aucun de ces", "aucune de ces", "lequel ne", "laquelle ne"
```

**Règle :** toujours formuler en positif. Si le fait est négatif par nature,
reformuler autour de ce qui est vrai.

```
❌ MAUVAIS : "Lequel de ces pays n'est PAS en Europe ?"
✅ BON     : "Lequel de ces pays est situé en dehors de l'Europe ?"

❌ MAUVAIS : "Aucune de ces affirmations n'est vraie sauf…"
✅ BON     : Reformuler en question directe sur le fait vrai
```

### 4.3 Règle de la réponse univoque

La bonne réponse doit être :
- **Précise** : un seul fait valide, pas d'interprétation possible
- **Distincte** : clairement différente des distracteurs
- **Non vague** : interdit "Oui", "Non", "Les deux", "Aucune de ces réponses",
  "Cela dépend"

### 4.4 Règle du distractor plausible

Chaque distractor (réponse incorrecte) doit :
- Être plausible pour quelqu'un qui confond un fait proche
- Être **univoquement faux** — pas "partiellement correct dans un autre contexte"
- Ne pas être évident ("Je ne sais pas", réponse absurde)
- Ne pas être une reformulation de la bonne réponse

---

## 5. Budgets de longueur par langue

### 5.1 Guards actifs (seuils de rejet en base de données)

Ces valeurs sont les **seuils de rejet** dans `QualityGuards.php` et
`config/question_bank_profiles.php`. Tout dépassement = rejet immédiat.

| Champ            | FR/EN/DE/ES/IT/PT/RU/EL | AR    | ZH    |
|------------------|-------------------------|-------|-------|
| `question_text`  | **110** chars            | **75** | **60** |
| `answer_a/b/c/d` | **60** chars             | **40** | **30** |
| `saviez_vous`    | **220** chars            | **140** | **100** |

### 5.2 Contraintes prompt AI (seuils d'instruction — 5 chars sous les guards)

Ces valeurs sont injectées dans le prompt pour que l'IA génère en dessous
des guards dès le premier essai. Marge de sécurité = 5 chars.

| Champ            | FR/EN/DE/ES/IT/PT/RU/EL | AR     | ZH    |
|------------------|-------------------------|--------|-------|
| `question_text`  | ≤ **105** chars          | ≤ **70** | ≤ **55** |
| `answer_a/b/c/d` | ≤ **55** chars           | ≤ **35** | ≤ **25** |
| `saviez_vous`    | ≤ **215** chars          | ≤ **135** | ≤ **95** |

### 5.3 Rationale par champ

**question_text :**
Le joueur doit lire et comprendre la question en ≤ 5 secondes sur mobile.
À 15 chars/seconde de lecture, 110 chars = ~7 secondes — déjà à la limite.
L'arabe et le mandarin sont sémantiquement plus denses par caractère :
leurs scripts permettent plus d'information en moins de caractères,
d'où des plafonds plus bas en caractères mais équivalents en densité sémantique.

**answer_a/b/c/d :**
Les boutons de réponse font ~180 px de large sur un écran 375 px (iPhone SE).
60 chars = ~3 lignes de texte dans un bouton. Au-delà, le scan rapide devient
impossible sous pression temporelle.

**saviez_vous :**
L'écran RÉSULTAT auto-avance après ~4 secondes. 220 chars = ~1 500 ms à
vitesse de lecture moyenne — plafond absolu pour être lu avant l'avance.

### 5.4 Règle de l'équilibre inter-réponses

Dans un QCM, les 4 réponses doivent avoir des **longueurs comparables**.
Un distractor de 3 mots face à une bonne réponse de 15 mots crée un biais
de forme qui permet de deviner sans connaître.

Seuil indicatif : écart max entre la réponse la plus longue et la plus courte
≤ 2× la longueur de la plus courte.

---

## 6. Règles Saviez-vous

### 6.1 Définition

Le `saviez_vous` est la **récompense cognitive post-réponse**. Il est affiché
sur l'écran RÉSULTAT et doit donner au joueur l'envie d'avoir appris quelque
chose — qu'il ait répondu juste ou faux.

### 6.2 Obligations

- **Obligatoire** dans toutes les langues — une traduction vide invalide
  la question entière
- **Longueur minimum :** 40 caractères (guard actif)
- **Contenu :** fait NOUVEAU, surprenant, conséquence remarquable, contexte
  historique/scientifique inattendu, ou anecdote insolite
- **Lié à la réponse correcte** — exclusivement

### 6.3 Critères de qualité

**Le saviez_vous doit apporter une information absente de la question.**

Il doit répondre implicitement à : *"Et alors ? Pourquoi c'est intéressant ?"*

Marqueurs de qualité recherchés (au moins un doit être présent) :
`en réalité`, `en fait`, `cependant`, `pourtant`, `contrairement`,
`paradoxalement`, `surprenant`, `insolite`, `record`, `premier`, `jamais`,
`seulement`, `moins de`, `plus de`, `à l'époque`, `on ignore souvent`.

### 6.4 Interdictions absolues (causes de rejet automatique)

**Interdit 1 — Reformulation de la réponse correcte :**
```
❌ Question : "Qui a peint 'Les Tournesols' ?"
   Réponse : "Van Gogh"
   SV interdit : "Van Gogh a peint 'Les Tournesols'."
   ↳ Raison : reformulation de la réponse comme affirmation → tautologique
```

**Interdit 2 — Insérer la réponse dans une phrase reprenant la question :**
```
❌ Question : "Quelle est la capitale de l'Australie ?"
   Réponse : "Canberra"
   SV interdit : "Canberra est bien la capitale de l'Australie."
```

**Interdit 3 — Paraphrase de l'énoncé :**
```
❌ SV : "En effet, l'Indonésie possède le plus grand nombre de volcans actifs."
   ↳ Raison : répète le fait de la question sans apporter d'information nouvelle
```

**Interdit 4 — Citer un distractor dans le saviez_vous sans mentionner la bonne réponse :**
```
❌ Question : "Qui a peint 'Guernica' ?" → Réponse : Picasso
   SV interdit : "La bataille de Guernica a eu lieu en 1937 à cause du régime de Franco."
   ↳ Raison : ne mentionne ni Picasso ni la peinture → saviez_vous hors sujet
```
*(Guard `saviez_vous_contradicts_answer` actif.)*

**Interdit 5 — Saviez_vous qui mentionne un distractor sans mentionner la bonne réponse :**
Rejeté automatiquement par guard `saviez_vous_contradicts_answer`.

### 6.5 Exemples bons

```
✅ Question : "Quel pays possède le plus grand nombre de volcans actifs ?"
   Réponse : Indonésie
   SV BON : "En réalité, l'Indonésie abrite 127 volcans actifs —
             soit environ 13 % du total mondial — mais seulement
             1/3 sont surveillés en continu faute de moyens."
   ↳ Nouvelle info (13 %, surveillance), surprenant, lié à la réponse.

✅ Question : "Qui a co-fondé le cubisme ?"
   Réponse : Pablo Picasso
   SV BON : "Picasso n'a jamais vendu 'Les Demoiselles d'Avignon' de son
             vivant — l'œuvre est restée dans son atelier 9 ans avant
             d'être acquise par le MoMA en 1939."
   ↳ Fait inattendu, non présent dans la question, lié à Picasso.
```

---

## 7. Règles anti-répétition cognitive

### 7.1 Le problème

La répétition cognitive n'est **pas** la répétition brute d'une réponse.
La répétition cognitive, c'est poser **le même fait sous des angles différents
qui reviennent à la même chose** pour le joueur.

**Répétition cognitive = même answer_target + même angle sémantique.**
La répétition cognitive n'est **pas** = même texte de réponse dans des
contextes cognitifs différents.

### 7.2 Guard actuel (à évoluer — voir §8 et §9)

Guard `correct_answer_overused` actuel :
```
Scope  : sub_domain (ex: "Art", "Géographie")
Clé    : texte exact de la réponse FR (ex: "Indonésie")
Seuil  : 12 occurrences → rejet
```

**Limitation connue :** ce guard produit ~16 faux positifs pour 1 vrai positif
(analyse du 2026-05-22). Il sera remplacé par le modèle d'entropie décrit en §9.

### 7.3 Exemples de répétition acceptable

Picasso peut apparaître de nombreuses fois si les contextes sont réellement distincts :

| # | Noyau (concept_family) | cognitive_type | Verdict |
|---|---|---|---|
| 1 | `cubism-art-history` | recognition | ✅ Cubisme / Les Demoiselles |
| 2 | `20th-century-spanish-art` | recognition | ✅ Guernica / guerre civile |
| 3 | `20th-century-art-collaboration` | deceptive_trap | ✅ Ballet Parade / Diaghilev |
| 4 | `modern-art-movements` | reasoning | ✅ Co-fondation cubisme avec Braque |
| 5 | `20th-century-spanish-art` | recognition | ❌ Encore Guernica — même path |

### 7.4 Exemples de répétition inacceptable

Édouard Manet réel en banque (avant correction) :
- 14 questions, 2 familles quasi-identiques
- 5 variantes : "Qui a peint Olympia ?" sous différentes formulations
- 6 variantes : "Qui a peint Le Déjeuner sur l'herbe ?" reformulé
- Coverage 14 % : faux positif clair, vrai problème

---

## 8. Définition d'un Cognitive Path

### 8.1 Définition formelle

Un **cognitive path** est le triplet :

```
cognitive_path = (answer_text, concept_family, cognitive_type)
```

Deux questions partagent le même cognitive path si et seulement si ces trois
dimensions sont identiques.

**Questions partageant un cognitive path = cognitivement redondantes.**

### 8.2 Exemples

```
("Indonésie", "world-geography-volcanoes", "recognition")
   → path #A
("Indonésie", "world-geography-volcanoes", "reasoning")
   → path #B  ← différent de #A par cognitive_type
("Indonésie", "world-geography-archipelagos", "recognition")
   → path #C  ← différent de #A par concept_family
("Chine", "world-geography-volcanoes", "recognition")
   → path #D  ← différent de #A par answer_text

("Édouard Manet", "19th-century-french-painting", "deceptive_trap")
   → path #E  [SATURÉ — 4 questions dedans, toutes sur Olympia/Déjeuner]
```

### 8.3 Capacité maximale par path

**Règle future (à implémenter en bank worker) :**
- Maximum **2 questions par cognitive path**
- Au-delà de 2, la génération dans ce path est bloquée et le worker
  doit cibler un path différent

**Ratio de couverture minimum (à implémenter) :**
- Si `COUNT(*) ≥ 6` pour un `answer_text` dans un `sub_domain` :
  - `DISTINCT(concept_family) / COUNT(*) ≥ 0.25`
  - Sinon → rejet (concentration excessive)

### 8.4 Cas spéciaux : réponses génériques

Les réponses numériques pures (`1`, `2`, `3`, etc.) ou très courtes (< 4 chars)
n'ont **pas d'identité cognitive stable**. Le chiffre `2` dans le contexte
tennis-scoring n'a aucun lien cognitif avec le `2` dans golf-scoring.

Pour ces réponses, **seul le path-level cap s'applique** — pas de cap global
par `sub_domain`.

---

## 9. Règles de diversité réelle

### 9.1 Diversité au niveau du noyau

Les 5 variantes d'un noyau doivent couvrir **des angles réellement distincts**
du même fait central (`answer_target`).

Diversité minimale attendue dans un noyau complet :
- ≥ 3 `concept_family` différentes sur 5 variantes
- Les 3 `cognitive_type` (recognition, reasoning, deceptive_trap) représentés
- Pas de deux questions avec > 70 % de similarité textuelle sur la question FR
  (guard `text_similarity` actif, seuil Jaccard)

### 9.2 Diversité au niveau de la banque

**Règle de couverture des concept_families :**
Pour un `answer_text` donné dans un `sub_domain` :
- Si > 6 questions totales → au moins 30 % de familles distinctes
- `distinct_concept_families / total ≥ 0.30`

**Règle de cap par cognitive path :**
- Maximum 2 questions par `(answer_text × concept_family × cognitive_type)`
- Cette règle remplacera `correct_answer_text_max_freq = 12` dans une version future

### 9.3 Concept_family diversity — guard existant

Guard `concept_family_share` actif :
```
Scope : (domain, sub_domain, cognitive_type, difficulty_level)
Seuil : (count+1) / (total+1) > 0.40 → rejet
```
Ce guard empêche qu'une famille concentre > 40 % d'un segment.
**Ne pas l'affaiblir.** Il est correct et prouvé par la dialyse P5.

---

## 10. Faux patterns interdits

Ces patterns sont des erreurs systématiques de l'IA qu'il faut activement
prévenir dans les prompts et détecter dans les guards.

### 10.1 Le "Guernica overload" — répétition sémantique déguisée

**Définition :** plusieurs questions dans des `concept_family` différentes
qui posent essentiellement le même fait avec un habillage différent.

```
❌ Variante 1 (20th-century-spanish-art/recognition) :
   "Quel artiste a peint Guernica, œuvre dénonçant la guerre civile espagnole ?"

❌ Variante 2 (20th-century-art-history/deceptive_trap) :
   "Quel artiste a peint Guernica, qui a été exposé au MoMA 40 ans avant
   de retourner en Espagne ?"

❌ Variante 3 (20th-century-painting/recognition) :
   "Quel artiste est célèbre pour Guernica, qui représente les horreurs de
   la guerre civile espagnole ?"
```

Ces 3 questions demandent toutes "Qui a peint Guernica ?" — différents habillages,
même cognitive path réel. **Interdits simultanément.**

### 10.2 La "fausse diversité Indonésie" — même fait reformulé

```
❌ "Quel pays a le plus de volcans actifs ?"
❌ "Quel pays possède le plus grand nombre de volcans actifs (ayant éclaté
    dans les 10 000 dernières années) ?"
❌ "Quel pays compte le plus grand nombre de volcans actifs (volcans entrés
    en éruption dans l'histoire récente) ?"
```

Ces 3 questions demandent le même fait (nombre de volcans Indonésie) avec
uniquement des précisions definitionnelles différentes. **Même answer_target,
même angle — répétition cognitive.**

### 10.3 La "question reasoning sans raisonnement"

```
❌ Type : qcm/reasoning
   Question : "Quel artiste a peint 'Les Tournesols' ?"
   ↳ Problème : rappel pur, aucun raisonnement requis → mislabelled recognition
```

### 10.4 Le "deceptive_trap sans piège"

```
❌ Type : qcm/deceptive_trap
   Question : "Quel est le pays le plus peuplé du monde ?"
   Réponses : Chine ✅, Inde, États-Unis, Indonésie
   ↳ Problème : les distracteurs ne piègent pas — la confusion Chine/Inde existe
     mais n'est pas utilisée, et les autres options sont clairement fausses
```

### 10.5 La "vraie/fausse question VF sans tranchant"

```
❌ VF : "L'Indonésie est souvent considérée comme ayant beaucoup de volcans."
   ↳ Problème : "souvent considérée" rend le vrai/faux non tranchable
   
❌ VF : "Certains historiens pensent que Picasso était espagnol."
   ↳ Problème : "certains historiens pensent que" dilue le fait — non jouable
```

### 10.6 La "dérive sémantique" — semantic drift (P4 target)

```
❌ Noyau : sport-tennis-grand-slam-records
   Question générée : "Quel pays a le plus de médailles olympiques en marathon ?"
   ↳ Problème : le noyau cible le tennis/Grand Chelem, pas le marathon olympique
   
❌ Noyau : geographie-african-geography
   Question générée : "Quel est le plus long fleuve d'Asie ?"
   ↳ Problème : le noyau est la géographie africaine, pas asiatique
```

Prévention : `concept_hint` injecté en P4 (voir BankAIGenerator.php).

### 10.7 La question à formulation négative

```
❌ "Lequel de ces pays n'est PAS en Afrique ?"
❌ "Aucun de ces artistes n'est connu pour le cubisme SAUF…"
❌ "Lequel de ces faits n'est PAS vrai concernant la Tour Eiffel ?"
```

Guard `negative_framing_keywords` actif — rejet automatique.

### 10.8 Le saviez_vous tautologique

```
❌ SV : "En effet, l'Indonésie est bien le pays avec le plus grand nombre
         de volcans actifs."
         → Reformulation directe de la question
         
❌ SV : "Picasso a peint de nombreuses œuvres célèbres."
         → Vague, sans information nouvelle
```

---

## 11. Exemples bons vs mauvais

### 11.1 Noyau : sport-tennis-grand-slam-records (depth 4)

**QCM-R (recognition) ✅**
```
Q : "Quel joueur de tennis détient le record du plus grand nombre de titres
     en Grand Chelem en simple messieurs ?"
A : Novak Djokovic ✅ | Rafael Nadal | Roger Federer | Pete Sampras
SV : "Djokovic a surpassé Federer et Nadal en 2021 à l'Open d'Australie —
      un record longtemps considéré inatteignable tant les trois joueurs
      se disputaient le sommet simultanément."
```

**QCM-L (reasoning) ✅**
```
Q : "Si un joueur a remporté 3 des 4 tournois du Grand Chelem la même année,
     quel titre lui manque-t-il pour réaliser le Grand Chelem calendaire ?"
     [Sachant que les 4 sont : Roland-Garros, Wimbledon, US Open, Open d'Australie]
A : Wimbledon ✅ | Roland-Garros | US Open | Open d'Australie
↳ [La question doit préciser lesquels il a gagnés pour être soluble]
SV : "Le Grand Chelem calendaire n'a été réalisé qu'une seule fois en messieurs :
      par Rod Laver en 1969 — et il l'avait déjà réalisé en 1962 en amateur."
```

**QCM-D (deceptive_trap) ✅**
```
Q : "Quel est le seul Grand Chelem disputé sur surface en terre battue ?"
A : Roland-Garros ✅ | Open d'Australie | Wimbledon | US Open
↳ Piège : beaucoup confondent surface (gazon Wimbledon, dur US Open/AO, terre RG)
SV : "Depuis 1988, l'Open d'Australie se joue sur surface dure (Plexicushion) —
      avant cette date, il était disputé sur gazon, comme Wimbledon."
```

### 11.2 Noyau : geographie-african-geography (depth 5)

**QCM-R ✅**
```
Q : "Quel est le pays africain avec la plus grande superficie totale ?"
A : Algérie ✅ | Soudan | République Démocratique du Congo | Libye
SV : "L'Algérie est devenue le plus grand pays africain en 2011 lors de la
      séparation du Soudan du Sud — auparavant, le Soudan détenait ce titre."
```

**QCM-L ✅**
```
Q : "Lequel de ces pays africains est entièrement entouré par un seul autre pays ?"
A : Lesotho ✅ | Swaziland | Gambie | Djibouti
↳ Raisonnement : le joueur doit déduire "entouré par UN SEUL" → Lesotho (dans Afrique du Sud)
SV : "Le Lesotho et le Vatican sont les deux seuls États au monde entièrement
      enclavés dans un unique autre pays — le Vatican dans l'Italie."
```

### 11.3 Cas à éviter — Manet (vraie répétition cognitive)

```
❌ Variante 1 : "Qui a peint Olympia ?" → Manet
❌ Variante 2 : "Qui a peint Le Déjeuner sur l'herbe ?" → Manet
❌ Variante 3 : "Qui a peint Olympia, œuvre controversée du Salon ?" → Manet
❌ Variante 4 : "Quel artiste du XIXe siècle est connu pour ses nus réalistes
                controversés ?" → Manet
```

Ces 4 questions demandent toutes d'identifier Manet à partir de ses œuvres
controversées. Contextes sémantiquement distincts sur le papier, cognitivement
identiques pour le joueur.

**Variantes réellement différentes pour Manet :**
```
✅ "Quel peintre est souvent confondu avec Monet en raison de leurs noms
    similaires ?" [piège classique phonétique]
✅ "Quel artiste, bien qu'influencé par les impressionnistes, n'a jamais
    officiellement appartenu au mouvement ?" [fait peu connu]
✅ VF : "Édouard Manet et Claude Monet ont travaillé ensemble sur des
         tableaux communs." [FAUX — ils se sont inspirés mutuellement mais
         n'ont pas peint ensemble]
```

---

## 12. Règles mobile-first

### 12.1 Contexte d'affichage

La majorité des parties de StrategyBuzzer sont jouées sur mobile (375–428 px
de largeur). Toutes les questions doivent être conçues pour cet environnement.

### 12.2 Règles d'affichage

**Question text :**
- Doit être lisible en 3–5 secondes sur écran 375 px
- Pas de sous-phrases, de parenthèses longues, de listes imbriquées
- Une seule idée par question — pas de question composée ("X et Y sont…")
- Éviter les guillemets doubles dans les langues RTL (arabe) qui peuvent
  créer des problèmes d'affichage

**Answer buttons :**
- Les 4 boutons doivent avoir une taille comparable (voir §5.4)
- Pas de noms propres très longs qui tronquent le bouton
- Les nombres doivent être en chiffres, pas en lettres ("27" pas "vingt-sept")

**Saviez_vous :**
- Une seule phrase ou deux phrases courtes — pas un paragraphe
- Pas de listes à puces — le format est une phrase fluide
- Le chiffre clé (record, date, proportion) doit apparaître tôt dans la phrase

### 12.3 Règles RTL (arabe)

- `question_text` AR ≤ 75 chars (guard) / ≤ 70 (prompt)
- `answer` AR ≤ 40 chars (guard) / ≤ 35 (prompt)
- `saviez_vous` AR ≤ 140 chars (guard) / ≤ 135 (prompt)
- Pas de mélange LTR/RTL dans la même phrase (ex : un nombre latin au milieu
  d'un texte arabe — utiliser les chiffres arabes-indiens si nécessaire)

### 12.4 Règles CJK (mandarin)

- `question_text` ZH ≤ 60 chars (guard) / ≤ 55 (prompt)
- `answer` ZH ≤ 30 chars (guard) / ≤ 25 (prompt)
- `saviez_vous` ZH ≤ 100 chars (guard) / ≤ 95 (prompt)
- Les caractères chinois étant plus denses, ces plafonds correspondent à
  des volumes sémantiques comparables aux plafonds occidentaux

---

## 13. Règles Duo buzzability

### 13.1 Contexte Duo

En mode Duo, les joueurs **buzzent** pour répondre avant l'adversaire. La
question doit déclencher un processus de décision rapide — savoir ou buzzer
rapidement, pas analyser longuement.

### 13.2 Règles buzzability

**La question doit permettre une décision précoce.**
Le joueur doit pouvoir savoir s'il connaît la réponse **avant d'avoir tout lu**.

Structure idéale : le **sujet de la question est identifiable dans les 5 premiers
mots**. Les qualificatifs viennent après.

```
✅ BUZZABLE  : "Quel pays possède le plus grand nombre de volcans actifs…
                [le joueur sait déjà s'il connaît la réponse]"

❌ PAS BUZZABLE : "Dans le contexte de la géologie mondiale, en tenant compte
                   de la définition 'volcan actif' comme ayant éclaté dans les
                   10 000 dernières années, quel pays possède…"
                   [le sujet arrive trop tard]
```

**La réponse doit être précise et non ambiguë.**
Un buzzer qui donne une réponse approximative doit être invalide. Les réponses
floues ("une grande puissance", "un pays européen") ne sont pas buzzables.

### 13.3 Longueur Duo

Pour le Duo, la contrainte mobile est encore plus serrée (pression adversariale) :
- `question_text` : viser ≤ 90 chars même pour FR/EN (sous le guard de 110)
- `answer` : viser ≤ 45 chars même pour FR/EN (sous le guard de 60)
- Pas de questions composées avec des `sachant que` ou `si … alors` longs

### 13.4 Cognitive types en Duo

**recognition** : idéal pour Duo — décision rapide, savoir pur.

**deceptive_trap** : excellent pour Duo — le piège crée de la tension
stratégique (buzzer vite ou attendre pour éviter le piège ?).

**reasoning** : délicat en Duo — le raisonnement est difficile sous pression
de buzzer. Le reasoning Duo doit être simple et déductible en < 5 secondes.

**VF reasoning** : à éviter en Duo si le raisonnement est complexe.

---

## 14. Règles deceptive_trap propres

### 14.1 Anatomie d'un piège propre

Un `deceptive_trap` propre comporte trois éléments :

1. **La confusion documentée** : un fait que beaucoup confondent avec un autre
2. **Le distracteur principal** : la réponse qui piège (plausible, connue, fréquemment choisie)
3. **La bonne réponse** : contre-intuitive mais factuellement inattaquable

### 14.2 Types de pièges valides

**Piège de proximité temporelle :**
```
Q : "Qui a peint 'Olympia', exposée au Salon de Paris en 1865 ?"
Piège : Claude Monet (contemporain, même époque)
Réponse : Édouard Manet ✅
↳ Confusion Manet/Monet — piège phonétique classique
```

**Piège de proximité géographique :**
```
Q : "Quelle est la capitale de l'Australie ?"
Piège : Sydney (la plus grande ville)
Réponse : Canberra ✅
↳ Confusion grande ville / capitale — piège universel
```

**Piège d'association forte :**
```
Q : "Quel pays a inventé le hamburger ?"
Piège : États-Unis (association forte hamburger → américain)
Réponse : Allemagne ✅ (Hamburg → Hambourg)
↳ Association nom-produit vs origine réelle
```

**Piège de première intention :**
```
Q : "Quel est le pays le plus grand du monde par superficie ?"
Piège : Canada (visuellement très grand sur la plupart des projections Mercator)
Réponse : Russie ✅
↳ Distorsion Mercator crée une impression erronée
```

### 14.3 Ce qui n'est PAS un deceptive_trap

```
❌ Q : "Quel est le pays le plus peuplé du monde ?"
   Réponses : Chine, Inde, USA, Indonésie
   ↳ Pas un piège — tout le monde sait que c'est Chine ou Inde.
     La confusion Chine/Inde n'est pas utilisée comme piège intentionnel.
     C'est un QCM ordinaire.

❌ Q : "Qui a peint la Joconde ?"
   Réponses : Léonard de Vinci, Michel-Ange, Raphaël, Botticelli
   ↳ Pas un piège — la réponse est trop connue pour piéger qui que ce soit.

❌ Q : "Lequel de ces artistes est surréaliste ?"
   Réponses : Salvador Dalí ✅, Léonard de Vinci, Claude Monet, Paul Cézanne
   ↳ Distracteurs non plausibles — le piège est inexistant.
```

### 14.4 Règles de construction des distracteurs

- **Distractor 1 (principal)** : la confusion la plus classique et documentée
- **Distractor 2** : plausible pour quelqu'un qui connaît vaguement le domaine
- **Distractor 3** : plausible pour quelqu'un qui connaît bien le domaine
  mais manque ce détail précis

Les trois distracteurs doivent être de la **même nature grammaticale** que
la bonne réponse (nom propre vs nom propre, nombre vs nombre, pays vs pays).

Longueurs comparables — voir §5.4.

### 14.5 Post-piège : le saviez_vous deceptive_trap

Le `saviez_vous` d'un `deceptive_trap` doit **expliquer pourquoi le piège
fonctionne** — valider la surprise et consolider la bonne réponse.

```
✅ Q deceptive_trap : "Quelle est la capitale de l'Australie ?"
   SV : "Canberra a été choisie comme compromis entre Sydney et Melbourne en
         1908 — les deux rivales refusant que l'autre soit la capitale,
         une ville nouvelle a été construite entre elles."
   ↳ Explique le piège (Sydney ≠ capitale) ET donne la raison fascinante.
```

---

## 15. Règles de Semantic Nucleus Stability

### 15.1 Définition du Noyau Sémantique

Un **noyau sémantique** (`question_intent`) est défini par ses métadonnées
invariantes :

```
semantic_key  : identifiant stable (ex: "sport-tennis-grand-slam-records")
subject       : le sujet principal (ex: "tennis")
angle_large   : l'axe principal (ex: "Grand Chelem")
micro_angle   : la précision (ex: "records de titres")
answer_target : ce que la réponse doit être (ex: "joueur avec le plus de titres")
concept_family: famille kebab-case (ex: "tennis-grand-slam-winners")
```

### 15.2 La règle de stabilité

**Toutes les 5 variantes d'un noyau doivent rester dans le noyau.**

Une variante "sort du noyau" si sa question FR n'a plus de lien factuel direct
avec le `subject + angle_large + micro_angle + answer_target` du noyau.

**Test de stabilité :** si on supprime la question et qu'on ne peut pas deviner
le `semantic_key` du noyau à partir de la question seule, la variante a dérivé.

### 15.3 La dérive sémantique — définition

**Dérive sémantique** = une variante générée qui est topiquement correcte
(même domaine) mais sémantiquement hors-noyau (différent sujet/angle).

```
Noyau : sport-tennis-grand-slam-records
Dérive : "Quel pays a remporté le plus de médailles d'or en marathon olympique ?"
→ Sport ✅, mais tennis/Grand Chelem ❌

Noyau : geographie-african-geography
Dérive : "Quel est le plus long fleuve d'Asie ?"
→ Géographie ✅, mais Afrique ❌
```

### 15.4 Prévention : concept_hint (P4)

Le mécanisme `concept_hint` injecte dans le prompt le noyau exact :
```
"Sujet: [subject]. Angle: [angle_large]. Précision: [micro_angle].
 Cible: [answer_target]. Reste STRICTEMENT dans ce noyau."
```

Ce hint est construit par `buildConceptHint()` dans
`QuestionsDialyseRunTestCommand.php` et transmis à `BankAIGenerator.php` →
`question-api.js`.

### 15.5 Prévention : forbidden_families (P5)

Le mécanisme `forbidden_families` liste les `concept_family` déjà
sur-représentées dans le segment avant génération, forçant l'IA à choisir
un angle de famille différent.

Calculé par `forbiddenFamilies()` dans `QuestionsDialyseRunTestCommand.php` :
```
Scope : (domain, sub_domain, cognitive_type, difficulty_level)
Seuil : (count+1) / (total+1) > 0.40
```

### 15.6 Règle de la `semantic_key` stable

La `semantic_key` d'un noyau est un identifiant en kebab-case anglais qui
ne doit **jamais changer** après la création du noyau.
Format : `{domain-slug}-{angle_large-slug}[-{micro_angle-slug}]`

```
✅ histoire-guerre-independance-americaine
✅ sport-tennis-grand-slam-records
✅ geographie-african-geography
✅ art-french-romanticism
✅ science-coral-reef-ecosystem
```

La `concept_family` de chaque variante est libre mais doit être taxonomiquement
stable et en kebab-case anglais, 1–4 mots.

---

## Appendice A — Guards actifs en production

| Guard code                       | Déclencheur                                          | Champ concerné          |
|----------------------------------|------------------------------------------------------|-------------------------|
| `missing_translations`           | Langue requise absente                               | translations            |
| `missing_saviez_vous`            | saviez_vous vide ou < 40 chars                       | saviez_vous             |
| `answer_key_misaligned`          | correct_answer_key incohérent entre langues          | correct_answer_key      |
| `dup_concept_id`                 | concept_id déjà en banque dans le segment            | concept_id              |
| `concept_family_share`           | concept_family > 40 % du segment                    | concept_family          |
| `text_similarity`                | Jaccard > seuil vs question existante               | question_text FR        |
| `saviez_vous_off_topic`          | Jaccard < 0.04 ET sans mot-clé commun               | saviez_vous FR          |
| `cognitive_mismatch`             | Heuristique détecte mauvais cognitive_type          | cognitive_type          |
| `depth_incoherent`               | depth 9-10 + question < 40 chars                    | question_text           |
| `correct_answer_overused`        | Même texte réponse ≥ 12× dans sub_domain            | answer FR               |
| `question_too_long`              | question_text > 110/75/60 chars                     | question_text           |
| `answer_too_long`                | answer > 60/40/30 chars                             | answer_a/b/c/d          |
| `saviez_vous_too_long`           | saviez_vous > 220/140/100 chars                     | saviez_vous             |
| `negative_framing`               | Keyword négatif dans question FR                    | question_text FR        |
| `saviez_vous_contradicts_answer` | Distractor dans SV sans bonne réponse               | saviez_vous FR          |
| `saviez_vous_tautological`       | SV reformule la question                            | saviez_vous FR          |

---

## Appendice B — Patches appliqués (historique)

| Patch | Composant               | Problème ciblé                                    | Résultat mesurable       |
|-------|-------------------------|---------------------------------------------------|--------------------------|
| P0    | `question-api.js`       | true_false : answer_a/b non initialisés à Vrai/Faux | Contract V/F stable       |
| P1    | `QualityGuards.php`     | Jaccard `saviez_vous_off_topic` faux positifs     | 2 vrais off-topics / run  |
| P4    | `QuestionsDialyseRunTestCommand.php` + `BankAIGenerator.php` | Dérive sémantique (tennis→marathon, africa→Asie) | +1 noyau complet (2/10)  |
| P3    | `question-api.js`       | Limites prompt > guards réels → rejet systématique | +6 noyaux (8/10)         |
| P5    | `QuestionsDialyseRunTestCommand.php` | `concept_family_share` rejet en production | +2 noyaux (10/10) |
| **LOT1** | `QualityGuards.php` + `config/question_bank_profiles.php` + migration | Guard `correct_answer_overused` remplacé par Entropy Engine (E1/E2/E3) | 16 faux positifs éliminés ; 4 cas manqués (Barry Lyndon, David, Hopper) couverts |

---

## Appendice C — Évolutions planifiées (non encore patchées)

| Évolution | Problème ciblé                               | Composant cible                    | Statut   |
|-----------|----------------------------------------------|------------------------------------|----------|
| LOT2 — concept_hint worker-safe | Dérive sémantique dans le vrai worker (P4 uniquement en dialyse) | `BankAIGenerator.php` | Planifié |
| LOT3 — forbidden_families worker-safe | Porte P5 dans le bank worker production | `BankNeedsCalculator.php` ou segment builder | Planifié |
| LOT4 — Cognitive Guards | Fake reasoning, fake deceptive_trap, VF sans raisonnement, noyau forcé | `QualityGuards.php` | Planifié |
| LOT5 — Saviez-vous renforcé | Non tautologique, non Wikipédia-lite, budget langue | `QualityGuards.php` + `question-api.js` | Planifié |
| LOT6 — Duo Buzzability | Score/guard buzzabilité, longueur Duo cible | `QualityGuards.php` | Planifié |
| E5 — Prefix collision guard | Fragmentation artificielle concept_family (seismic vs seismic-activity) | `QualityGuards.php` | Planifié (post LOT6) |

---

## Appendice D — Entropy Engine LOT1 (2026-05-22)

### Seuils actifs

| Paramètre config | Valeur | Rôle |
|---|---|---|
| `correct_answer_path_max_freq` | **2** | E1 — cap par cognitive path |
| `correct_answer_family_min_ratio` | **0.25** | E2 — ratio minimum familles/total |
| `correct_answer_family_min_count` | **6** | E2 — minimum total avant activation |
| `correct_answer_soft_alert_freq` | **30** | E3 — seuil alerte non-bloquant |

### Comportement guard `correct_answer_overused` (nouveau)

```
1. E1 — Si concept_family et cognitive_type sont présents :
         COUNT(answer × concept_family × cognitive_type) ≥ 2 → rejet
         Code : correct_answer_overused
         Detail : "path 'X×Y×Z' already has N questions (max 2)"

2. E2 — Si total ≥ 6 ET réponse non-générique (non-numérique, len > 3) :
         distinct_families / total < 0.25 → rejet
         Code : correct_answer_overused
         Detail : "answer 'X' in 'Y': family_ratio=N% (D/T) < min=25%"

3. E3 — Si total ≥ 30 :
         Log::warning('qb.guard.answer_soft_alert', [...])
         NON-BLOQUANT — alerte humaine seulement
```

### Validation sur la banque actuelle (2026-05-22)

| Métrique | Valeur |
|---|---|
| Paths cognitifs totaux | 3 001 |
| Paths sains (count=1) | 2 872 (95,7 %) |
| Paths à la limite (count=2) | 97 (3,2 %) |
| Paths pathologiques bloqués par E1 (count≥3) | 32 (1,1 %) |
| Réponses bloquées par E2 | Manet (14%), Hopper (17%) |
| Alertes E3 actives | Chine (37), Indonésie (30) |
| Faux positifs éliminés vs ancien guard | 16/17 → 0/17 |

### Risque documenté : fragmentation artificielle (E5 futur)

L'IA peut contourner E1 en micro-fragmentant les concept_families :
`world-geography-seismic` vs `world-geography-seismic-activity` pour Indonésie,
`baseball-rules-inning` vs `baseball-rules-outs` vs `baseball-rules-regulation` pour `3`.

Ces micro-fragments passent E1 (chaque path a count=1) mais sont cognitivement
redondants. Guard E5 (prefix-collision check sur 3 tokens) est prévu post-LOT6.
Instruction prompt ajoutée dans question-api.js (LOT5).

### Index DB ajouté

```sql
CREATE INDEX qg_entropy_e1_idx
    ON question_groups (sub_domain, concept_family, cognitive_type);
-- Migration : 2026_05_22_200000_add_entropy_guard_index_to_question_groups
```

---

*Document officiel StrategyBuzzer — Question Engine Cognitive Contract v1.1*
*Ne pas modifier sans validation humaine et test de dialyse.*
