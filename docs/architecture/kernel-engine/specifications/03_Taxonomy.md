# 03_Taxonomy — Spécification officielle

**Version :** 1.0  
**Date :** 2026-08-16  
**Statut de la spécification :** **VERROUILLÉE**  
**Statut d’implémentation :** NON IMPLANTÉE / code existant à auditer après révision KRP v3.3  
**Position Blueprint :** SECTION 1 — CRÉATION INTELLECTUELLE

> Ce document contient uniquement l’architecture active de Taxonomy. Les formulations antérieures incompatibles ne sont pas des variantes valides ; elles restent uniquement dans l’Architecture Register avec leur statut historique.

---

# 1. Mission

Taxonomy transforme le territoire fixe déjà inscrit dans le KernelBlueprint :

```text
Blueprint.depth
+
Blueprint.domain
```

en un territoire intellectuel persistant, structuré et consommable pour **l’occurrence courante du bassin dans le tour de Depth courant** :

```text
1 Subdomain unique
↓
SubjectBank ≤ 50 Subjects PASS
↓
IdeaBanks ≤ 5 Dominant Ideas PASS par Subject
↓
consommation exacte
```

Taxonomy écrit uniquement :

```text
subdomain_active
subject_active
dominant_idea_active
```

Taxonomy possède la progression de ses réservoirs et est l’unique autorité intellectuelle pouvant produire :

```text
DOMAIN_EXHAUSTED(depth, domain)
DEPTH_EXHAUSTED(depth)
```

Taxonomy ne choisit jamais le prochain Domain ni le prochain Depth.

---

# 2. Responsabilités

Taxonomy doit :

1. recevoir un KernelBlueprint déjà créé et déjà rempli avec `depth + domain` par KRP ;
2. lire le `DepthContract` correspondant au `depth` courant ;
3. identifier l’**occurrence courante du bassin** `(Depth + Domain)` dans le tour de Depth courant ;
4. maintenir un seul Subdomain pour cette occurrence de bassin ;
5. faire créer par Gemini, dans un même travail intellectuel, le Subdomain et jusqu’à 50 Subjects conformes ;
6. persister uniquement les Subjects `PASS` dans la SubjectBank ;
7. conserver les Subjects `FAIL` seulement comme mémoire éphémère de l’appel courant ;
8. répartir les Subjects PASS en lots équilibrés pour la préparation des Dominant Ideas ;
9. faire utiliser à Gemini le mécanisme/règles `04_ValidationDominantIdeas` pendant cette préparation ;
10. persister les Dominant Ideas `PASS` dans les IdeaBanks ;
11. persister les Dominant Ideas `FAIL` dans la FAIL Bank Dominant Ideas ;
12. maintenir les mémoires anti-doublon applicables ;
13. sélectionner un IdeaSlot exact et permanent ;
14. écrire atomiquement le triplet exact `Subdomain + Subject + Dominant Idea` dans le Blueprint ;
15. marquer **ce même IdeaSlot** `CONSUMED` immédiatement après l’écriture Blueprint réussie ;
16. faire progresser silencieusement Idea → Subject → Subdomain ;
17. détecter la dernière Idea du dernier Subject de l’occurrence de bassin ;
18. vérifier que plus aucun contenu exploitable n’est oublié avant `DOMAIN_EXHAUSTED` ;
19. produire `DOMAIN_EXHAUSTED` pour le Domain du tour courant ;
20. produire `DEPTH_EXHAUSTED` lorsque les huit Domaines du tour de Depth courant sont terminés ;
21. persister ses Banks, historiques, curseurs, occurrences et états nécessaires à la reprise ;
22. gérer les erreurs techniques Gemini sans les transformer en verdict intellectuel ;
23. signaler les incidents opérationnels à Admin/Ops selon les codes définis ;
24. reprendre au plus petit point valide sans détruire le travail déjà accepté.

---

# 3. Interdictions

Taxonomy ne doit jamais :

- choisir ou modifier `depth` ;
- choisir ou modifier `domain` ;
- créer le KernelBlueprint ;
- écrire `blueprint_id` ;
- écrire `kernel_code` ;
- écrire les CognitiveSlots de Phase1 ;
- écrire les TranslationSlots de Phase2 ;
- stocker ses réservoirs dans le Blueprint ;
- créer plusieurs Subdomains dans la même occurrence de bassin ;
- considérer `(Depth + Domain)` seul comme identité durable d’un bassin lorsque ce couple revient dans un tour ultérieur ;
- forcer 50 Subjects ;
- inventer ou élargir des Subjects pour remplir des slots ;
- forcer 5 Dominant Ideas ;
- créer les Subjects un par un ;
- traiter `ValidationDominantIdeas` comme un moteur autonome relisant le Blueprint après coup ;
- persister un Subject `FAIL` dans une FAIL Bank permanente ;
- rendre une Dominant Idea `FAIL` exploitable ;
- consommer une autre Idea que celle écrite dans le Blueprint ;
- rechercher un nouveau `firstAvailableIdea()` après l’écriture pour confirmer la consommation ;
- attendre ReadyBank pour marquer l’Idea sélectionnée `CONSUMED` ;
- émettre un signal normal pendant la consommation d’Ideas intermédiaires ;
- émettre `AVAILABLE`, `DOMAIN_VALID` ou tout signal inverse destiné à rallumer un Domain ;
- émettre `DOMAIN_EXHAUSTED` si du contenu exploitable reste ;
- émettre `DEPTH_EXHAUSTED` avant la fin des huit Domaines du tour courant ;
- transformer timeout, erreur API, quota, réponse tronquée ou panne Gemini en `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED` ;
- comptabiliser les `cycle_target` ou choisir le prochain Depth à la place de KRP/DepthNeedMatrix.

---

# 4. Entrées

## 4.1 Entrée fonctionnelle canonique

```text
KernelBlueprint courant
+
DepthContract[Blueprint.depth]
```

Taxonomy lit dans le Blueprint uniquement :

```text
depth
domain
```

`blueprint_id` peut être utilisé comme référence technique d’incident ou de transaction, mais n’est pas une donnée intellectuelle de création.

`kernel_code` n’existe pas encore au début de Taxonomy et n’est jamais une entrée Taxonomy.

## 4.2 Contexte interne de tour

L’identité intellectuelle d’un bassin consommable n’est pas seulement `(Depth + Domain)` puisque le même couple revient au fil des besoins globaux.

Taxonomy maintient donc une identité interne d’occurrence :

```text
Depth
+
occurrence du tour de ce Depth
+
Domain
```

Le nom technique exact de la colonne ou de la classe d’implantation n’est pas contractuel. Le contrat métier est :

> deux passages du même `(Depth + Domain)` dans deux tours différents sont deux occurrences de bassin distinctes.

Cette identité reste **hors Blueprint**.

---

# 5. Sorties

Taxonomy possède trois catégories de sorties.

## 5.1 Écriture Blueprint

```text
subdomain_active
subject_active
dominant_idea_active
```

Les trois valeurs sont écrites ensemble comme le triplet exact sélectionné.

## 5.2 État persistant Taxonomy

Taxonomy met à jour :

- occurrence de bassin ;
- Subdomain actif et historique ;
- SubjectBank ;
- IdeaBanks ;
- mémoire PASS ;
- FAIL Bank Dominant Ideas ;
- états `CRÉÉ / PRÉPARÉ / DISPONIBLE / ACTIF / UTILISÉ` des Subjects ;
- états `DISPONIBLE / ACTIF / CONSUMED` des IdeaSlots ;
- curseurs de progression ;
- historique des Depths/Tours nécessaires à `LOOKBACK-2` ;
- incidents et points de reprise nécessaires à Taxonomy.

## 5.3 Signaux prospectifs vers KRP

```text
DOMAIN_EXHAUSTED(depth, domain)
DEPTH_EXHAUSTED(depth)
```

Ils ne modifient jamais le Blueprint qui vient d’être rempli.

---

# 6. Slots Blueprint

| Slot | Propriétaire | Taxonomy | Règle |
|---|---|---|---|
| `blueprint_id` | KernelBlueprintFactory | lecture technique seulement si nécessaire | jamais modifié |
| `depth` | KRP | **LIT** | immuable |
| `domain` | KRP | **LIT** | immuable |
| `subdomain_active` | Taxonomy | **ÉCRIT** | triplet Taxonomy write-once |
| `subject_active` | Taxonomy | **ÉCRIT** | même écriture atomique |
| `dominant_idea_active` | Taxonomy | **ÉCRIT** | même IdeaSlot que celui consommé |
| `kernel_code` | QuestionIntent / KernelCodeEngine | aucun accès fonctionnel | produit après Taxonomy |

Taxonomy ne transporte jamais sa SubjectBank, ses IdeaBanks, ses mémoires ou ses curseurs dans le Blueprint.

---

# 7. Données internes

## 7.1 Occurrence de bassin

Pour chaque tour d’un Depth, chaque Domain possède **une occurrence de bassin**.

Exemple :

```text
Depth 4 — tour 128 — Géographie
≠
Depth 4 — tour 129 — Géographie
```

Chaque occurrence possède son propre :

```text
1 Subdomain unique
1 SubjectBank
N IdeaBanks
progression
état de consommation
```

Lorsque le même `(Depth + Domain)` revient dans un tour ultérieur, Taxonomy ne recharge pas le bassin déjà `DOMAIN_EXHAUSTED` comme réservoir de consommation : elle ouvre une nouvelle occurrence.

Les occurrences antérieures restent persistées pour historique, anti-doublon et audit.

## 7.2 Subdomain

Une occurrence de bassin contient exactement **un Subdomain officiel**.

Le Subdomain possède au minimum :

- identité permanente ;
- Depth ;
- Domain ;
- occurrence de bassin ;
- libellé ;
- état actif/utilisé ;
- lien vers sa SubjectBank ;
- historique nécessaire à `SUBDOMAIN-LOOKBACK-2`.

## 7.3 SubjectBank

Plafond structurel :

```text
0 < Subjects PASS ≤ 50
```

`50` est un maximum et jamais une cible obligatoire.

Chaque SubjectSlot PASS possède au minimum :

- identité permanente ;
- parent Subdomain ;
- libellé ;
- état ;
- IdeaBank correspondante.

Les Subjects `FAIL` sont éphémères et ne sont pas persistés après l’appel de création du territoire.

## 7.4 IdeaBank

Chaque Subject PASS peut posséder :

```text
1 à 5 Dominant Ideas PASS
```

Chaque IdeaSlot PASS possède au minimum :

- identité permanente ;
- Subject parent ;
- Dominant Idea contextualisée ;
- état `DISPONIBLE / ACTIF / CONSUMED` ;
- mémoire PASS anti-doublon.

Une Dominant Idea `FAIL` ne devient jamais un IdeaSlot ; elle entre dans la FAIL Bank Dominant Ideas persistante.

## 7.5 Mémoire anti-doublon

Taxonomy possède :

- historique des Subdomains utilisés ;
- Subjects PASS historiques pour traçabilité ;
- mémoire PASS des Dominant Ideas ;
- FAIL Bank Dominant Ideas ;
- mémoire FAIL Subjects **éphémère seulement pendant l’appel courant** ;
- historique chronologique des tours de Depth permettant `LOOKBACK-2`, y compris à travers `Depth 10 → nouveau Depth 2`.

---

# 8. Mécanismes

## 8.1 Création du Subdomain unique + SubjectBank

Gemini reçoit dans le même appel :

```text
Depth
Domain
DepthContract[Depth]
règles Taxonomy applicables
exclusions Subdomain LOOKBACK-2
```

Gemini doit :

1. chercher un Subdomain conforme au Domain et au niveau de dégrainage du Depth ;
2. construire immédiatement ses Subjects dans le même travail ;
3. abandonner tout Subdomain candidat qui ne permet aucun Subject conforme ;
4. continuer avec un autre candidat dans le même appel ;
5. retourner uniquement un couple viable :

```text
1 Subdomain
+
1..50 Subjects PASS
```

Un Subdomain candidat n’est jamais persisté seul.

Il n’existe pas de sortie métier `0_MATIERE_VALIDE` de Taxonomy. Un candidat sans matière est simplement abandonné à l’intérieur du travail de création. L’appel n’est accepté que lorsqu’il respecte son contrat de sortie ; une sortie absente/inexploitable relève du mécanisme d’erreur/retry.

### Règles Subjects

- directement reliés au Subdomain ;
- conformes au `DepthContract` ;
- un mot si suffisant ; sinon minimum lexical nécessaire ;
- aucun remplissage artificiel ;
- pas de doublons/synonymes/équivalents trop proches dans la même SubjectBank ;
- Subjects similaires autorisés sous des Subdomains différents si le contexte le justifie.

## 8.2 Préparation équilibrée des lots

Soit :

```text
N = nombre réel de Subjects PASS
C = capacité technique maximale actuelle par appel = 10
```

Nombre minimal d’appels :

```text
ceil(N / C)
```

Les Subjects sont redistribués aussi également que possible :

```text
écart maximal entre deux lots = 1 Subject
```

Exemples :

```text
50 → 10 / 10 / 10 / 10 / 10
41 → 9 / 8 / 8 / 8 / 8
32 → 8 / 8 / 8 / 8
27 → 9 / 9 / 9
23 → 8 / 8 / 7
17 → 9 / 8
11 → 6 / 5
7  → 7
```

Les lots artificiellement minuscules de fin sont interdits lorsque la redistribution équilibrée les évite.

## 8.3 Création des Dominant Ideas + ValidationDominantIdeas

Pour chaque lot de Subjects, Gemini effectue dans le **même travail intellectuel** :

```text
création des Dominant Ideas candidates
+
application des règles/mécanisme ValidationDominantIdeas
↓
PASS / FAIL classés par Subject exact
```

`04_ValidationDominantIdeas` possède les règles internes du mécanisme. Taxonomy ne les redéfinit pas ici.

Taxonomy exige seulement le contrat d’interface suivant :

- chaque résultat reste rattaché au Subject exact ;
- maximum 5 PASS par Subject ;
- au moins 1 PASS pour qu’un Subject accepté soit préparé avec succès ;
- `0 PASS` pour un Subject déjà accepté est une anomalie de préparation, jamais un état normal d’épuisement ;
- PASS et FAIL sont renvoyés séparément.

## 8.4 Alimentation des Banks

```text
Subject PASS
→ SubjectBank persistante

Subject FAIL
→ mémoire éphémère du même appel
→ non persisté
```

```text
Dominant Idea PASS
→ IdeaBank du Subject
→ mémoire PASS persistante

Dominant Idea FAIL
→ FAIL Bank Dominant Ideas persistante
→ jamais exploitable
```

Après chaque lot, les mémoires applicables sont mises à jour avant le lot suivant.

## 8.5 Sélection du triplet exact

Taxonomy choisit :

```text
Subdomain unique de l’occurrence
+
Subject ACTIF
+
IdeaSlot DISPONIBLE exact
```

L’identité permanente de l’IdeaSlot sélectionné est conservée jusqu’à la consommation.

## 8.6 Écriture Blueprint

Taxonomy appelle l’écriture Taxonomy du Blueprint uniquement lorsque les trois valeurs sont prêtes.

```text
subdomain_active
subject_active
dominant_idea_active
```

L’écriture est atomique du point de vue du contrat : aucun triplet partiel n’est valide.

## 8.7 Consommation exacte

Après écriture Blueprint réussie :

```text
IdeaSlot sélectionné
=
Dominant Idea écrite
=
IdeaSlot marqué CONSUMED
```

Aucune nouvelle sélection n’est effectuée entre l’écriture et `CONSUMED`.

La consommation n’attend pas ReadyBank.

## 8.8 Passage au Subject suivant

Tant qu’il reste une Idea PASS :

```text
Subject ACTIF
↓
Idea écrite
↓
CONSUMED
↓
aucun signal
↓
Subject reste ACTIF
```

À la dernière Idea PASS :

```text
Subject ACTIF
↓
dernière Idea PASS
↓
Blueprint écrit avec succès
↓
Idea CONSUMED
↓
Subject UTILISÉ
↓
prochain Blueprint : prochain Subject DISPONIBLE
```

## 8.9 DOMAIN_EXHAUSTED

À la dernière Idea du dernier Subject :

```text
dernier Subject
↓
dernière Idea
↓
Blueprint écrit avec succès
↓
Idea CONSUMED
↓
Subject UTILISÉ
↓
Subdomain UTILISÉ
↓
vérification terminale Banks
```

Condition obligatoire :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Si la condition est vraie :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Sinon :

```text
TAX-003 — DOMAIN_EXHAUSTION_BLOCKED_REMAINING_CONTENT
```

et Taxonomy reprend au curseur du contenu restant.

## 8.10 DEPTH_EXHAUSTED

Lorsque les huit Domaines de l’occurrence courante du tour de Depth ont été terminés :

```text
DEPTH_EXHAUSTED(depth)
```

Ce signal signifie :

> le **tour courant** de ce Depth est terminé.

Il ne signifie pas que le besoin global gameplay du Depth est définitivement satisfait.

KRP/DepthNeedMatrix comptabilise ensuite exactement un tour dans `cycle_completed[depth]` et choisit le prochain Depth nécessaire selon son contrat v3.3.

## 8.11 Retry Gemini

Erreurs retryables :

- timeout ;
- connexion interrompue ;
- rate limit/quota temporaire ;
- indisponibilité service ;
- erreur réseau/API ;
- réponse vide ;
- réponse tronquée ou techniquement inexploitable ;
- appel n’ayant pas exécuté jusqu’au bout son contrat de sortie.

Seuil :

```text
1 tentative initiale
+
3 retries
=
4 tentatives maximum par opération
```

Après épuisement :

```text
GEMINI_TECHNICAL_ERROR_UNRESOLVED
```

Compteur transversal :

```text
MAX_CONSECUTIVE_UNRESOLVED_CALLS = 3
```

Au troisième appel intellectuel distinct consécutif ayant épuisé ses 4 tentatives :

```text
INTELLECTUAL_CREATION_UNAVAILABLE
↓
production opérationnelle BLOCKED
↓
Admin/Ops + notification
```

Toute réussite remet le compteur consécutif à `0`.

Aucune erreur technique ne modifie les Banks métier, ne consomme une Idea et ne produit un signal d’épuisement.

---

# 9. Communication

## 9.1 KernelBlueprint → Taxonomy

```text
Blueprint.depth
Blueprint.domain
```

Taxonomy n’a besoin d’aucun autre slot intellectuel pour créer son territoire.

## 9.2 Taxonomy ↔ Gemini

Taxonomy fournit :

- Depth ;
- Domain ;
- DepthContract ;
- exclusions anti-doublon applicables ;
- mémoires PASS/FAIL applicables selon le niveau ;
- Subjects du lot lors de la préparation des Dominant Ideas ;
- règles/mécanisme `ValidationDominantIdeas` lors des appels d’Ideas.

Gemini fournit :

- `Subdomain + SubjectBank PASS` lors de la création du territoire ;
- `Dominant Ideas PASS / FAIL` classées par Subject lors de la préparation des IdeaBanks.

## 9.3 Taxonomy → KernelBlueprint

```text
fillTaxonomy(
  subdomain_active,
  subject_active,
  dominant_idea_active
)
```

Le nom exact de l’API d’implantation peut varier ; la sémantique du triplet write-once est contractuelle.

## 9.4 Taxonomy → KRP

Uniquement :

```text
DOMAIN_EXHAUSTED(depth, domain)
DEPTH_EXHAUSTED(depth)
```

Taxonomy n’envoie jamais `AVAILABLE`.

## 9.5 Taxonomy → Admin/Ops

Taxonomy transmet les incidents techniques et de cohérence avec code défaut, contexte, Blueprint si disponible, `Depth + Domain`, occurrence de bassin, nombre de tentatives et point de reprise.

## 9.6 Absence de communication ReadyBank

ReadyBank ne choisit aucune Idea Taxonomy, ne confirme pas sa consommation et ne fait pas avancer les curseurs Taxonomy.

---

# 10. Contrats

## TAX-C01 — Bassin fixe

Le `depth + domain` du Blueprint est immuable pendant Taxonomy.

## TAX-C02 — Occurrence de bassin

Chaque nouveau tour d’un même Depth ouvre une nouvelle occurrence pour chaque `(Depth + Domain)` ; aucune occurrence consommée n’est réutilisée comme réservoir actif.

## TAX-C03 — Subdomain unique

Une occurrence de bassin possède exactement un Subdomain officiel.

## TAX-C04 — Création atomique du territoire

Le Subdomain et sa SubjectBank sont créés dans le même travail Gemini ; un Subdomain sans Subject conforme n’est jamais persisté seul.

## TAX-C05 — Plafonds sans remplissage forcé

```text
SubjectBank ≤ 50
IdeaBank ≤ 5 PASS par Subject
```

Les plafonds ne sont jamais des obligations de remplissage.

## TAX-C06 — Sujet exploitable

Tout Subject accepté doit aboutir à au moins une Dominant Idea PASS lors d’une préparation réussie.

## TAX-C07 — ValidationDominantIdeas

Gemini utilise le mécanisme `04_ValidationDominantIdeas` pendant la création des Dominant Ideas ; VDI ne lit ni n’écrit directement le Blueprint.

## TAX-C08 — Mémoire PASS/FAIL

- Subject PASS : persistant ;
- Subject FAIL : éphémère pendant l’appel ;
- Dominant Idea PASS : persistante ;
- Dominant Idea FAIL : persistante dans FAIL Bank.

## TAX-C09 — Exactitude de consommation

```text
Idea sélectionnée = Idea écrite = Idea CONSUMED
```

## TAX-C10 — Consommation immédiate

`CONSUMED` suit l’écriture Blueprint réussie ; il n’attend pas ReadyBank.

## TAX-C11 — Progression silencieuse

Aucun signal n’est émis entre les Ideas intermédiaires ou lors du simple passage normal d’un Subject au suivant.

## TAX-C12 — Épuisement prospectif

`DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` influencent uniquement la rotation future ; le Blueprint courant reste valide.

## TAX-C13 — Garde-fou contenu restant

`DOMAIN_EXHAUSTED` est interdit si une Bank exploitable n’est pas vide ; `TAX-003` prend priorité.

## TAX-C14 — Idempotence de frontière KRP

Le contrat entrant attendu de KRP est :

- `DOMAIN_EXHAUSTED` reçu deux fois pour le même Domain/tour → second = `NO-OP` ;
- `DEPTH_EXHAUSTED` reçu deux fois pour le même Depth/tour → second = `NO-OP` ;
- persistance KRP avant progression future.

## TAX-C15 — LOOKBACK-2 cyclique

Les fenêtres anti-doublon traversent `Depth 10 → nouveau Depth 2` et suivent toujours les deux Depths réellement précédents du même Domain.

## TAX-C16 — Séparation besoin global

Taxonomy déclare la fin intellectuelle d’un tour ; KRP/DepthNeedMatrix décide combien de tours globaux restent à produire.

---

# 11. États

## 11.1 Subdomain

```text
CRÉÉ
↓
ACTIF
↓
UTILISÉ
```

- `CRÉÉ` : Subdomain officiel accepté avec SubjectBank viable ;
- `ACTIF` : occurrence en cours de consommation ;
- `UTILISÉ` : tous les Subjects de l’occurrence sont `UTILISÉ`.

## 11.2 SubjectSlot

```text
CRÉÉ
↓
PRÉPARÉ
↓
DISPONIBLE
↓
ACTIF
↓
UTILISÉ
```

- `CRÉÉ` : membre PASS de la SubjectBank ;
- `PRÉPARÉ` : Dominant Ideas créées/contrôlées avec au moins 1 PASS ;
- `DISPONIBLE` : au moins une Idea PASS exploitable ;
- `ACTIF` : Taxonomy consomme actuellement ses IdeaSlots ;
- `UTILISÉ` : toutes ses Ideas PASS sont `CONSUMED`.

## 11.3 IdeaSlot

```text
DISPONIBLE
↓
ACTIF
↓
CONSUMED
```

`CONSUMED` est irréversible.

Une Dominant Idea `FAIL` n’est jamais un IdeaSlot et ne possède donc pas ce lifecycle.

## 11.4 État opérationnel externe

```text
RUNNING
BLOCKED
```

`BLOCKED` est un état Admin/Ops de la production intellectuelle ; ce n’est pas un état métier de Subdomain/Subject/Idea et ce n’est ni `DOMAIN_EXHAUSTED`, ni `DEPTH_EXHAUSTED`, ni `PRODUCTION_ON_HOLD`.

---

# 12. Transitions

## 12.1 Ouverture d’une occurrence

```text
KRP écrit Depth + Domain dans un nouveau Blueprint
↓
Taxonomy recherche une occurrence active non épuisée pour ce tour
├── existe → reprend son curseur
└── n’existe pas → ouvre la nouvelle occurrence du bassin
```

Un ancien bassin `DOMAIN_EXHAUSTED` d’un tour précédent n’est jamais réouvert.

## 12.2 Subject

```text
CRÉÉ
→ PRÉPARÉ
→ DISPONIBLE
→ ACTIF
→ UTILISÉ
```

Le passage `ACTIF → UTILISÉ` se produit uniquement après consommation réussie de la dernière Idea PASS.

## 12.3 Idea

```text
DISPONIBLE
→ ACTIF
→ écriture Blueprint réussie
→ CONSUMED
```

## 12.4 Domain

```text
dernière Idea du dernier Subject CONSUMED
↓
remaining_subjects = 0
remaining_ideas = 0
↓
Subdomain UTILISÉ
↓
DOMAIN_EXHAUSTED
```

## 12.5 Depth

```text
8 Domaines du tour courant terminés
↓
DEPTH_EXHAUSTED(depth)
```

KRP/DepthNeedMatrix effectue ensuite :

```text
cycle_completed[depth] += 1
```

exactement une fois, selon le contrat KRP v3.3.

---

# 13. Cas limites

## 13.1 Moins de 50 Subjects

Accepté. Aucun remplissage artificiel.

## 13.2 Un seul Subject PASS

Accepté si le territoire est conforme et que ce Subject obtient au moins une Dominant Idea PASS.

## 13.3 Moins de 5 Dominant Ideas PASS

Accepté à partir de `1 PASS`. Aucun remplissage artificiel.

## 13.4 `0 PASS` pour un Subject accepté

Anomalie de préparation. Le Subject n’atteint pas `PRÉPARÉ/DISPONIBLE`. Ce cas ne produit aucun épuisement.

## 13.5 Subdomain candidat sans Subject conforme

Le candidat est abandonné **dans le même travail de création** et Gemini cherche un autre Subdomain. Aucun signal métier n’est produit.

## 13.6 Erreur Gemini

Retry de la même opération, sans progression métier.

## 13.7 Redémarrage pendant une Bank partiellement consommée

Taxonomy recharge l’occurrence, les états et le curseur persistant ; aucun `CONSUMED` ne redevient disponible.

## 13.8 Même `(Depth + Domain)` dans un tour ultérieur

Nouvelle occurrence de bassin, nouveau Subdomain unique, nouvelle SubjectBank. L’ancienne occurrence reste historique.

## 13.9 LOOKBACK à `Depth 10 → nouveau Depth 2`

La fenêtre ne se réinitialise pas : nouveau Depth 2 regarde Depth 10 + Depth 9 précédents du même Domain.

## 13.10 Signal `DOMAIN_EXHAUSTED` alors que du contenu reste

Interdit. `TAX-003` bloque l’envoi et Taxonomy reprend le contenu restant.

## 13.11 Signal KRP dupliqué

Le contrat KRP exige `NO-OP` après la première persistance réussie.

## 13.12 Échec de persistance KRP après signal valide

Taxonomy ne réémet pas un signal inverse. KRP effectue jusqu’à 3 retries de persistance, puis bloque la production avec `KRP-002` ou `KRP-003` selon le signal.

## 13.13 Régression `ESTOMPÉ → VISIBLE`

Interdite dans le même tour de Depth.

---

# 14. Persistance

Doivent survivre aux redémarrages :

- occurrences de bassin ;
- Subdomain de chaque occurrence ;
- SubjectBanks PASS ;
- IdeaBanks PASS ;
- FAIL Bank Dominant Ideas ;
- mémoire PASS Dominant Ideas ;
- états des SubjectSlots ;
- états des IdeaSlots ;
- curseurs de progression ;
- historiques Subdomain nécessaires à `LOOKBACK-2` ;
- historiques Dominant Ideas nécessaires à `LOOKBACK-2` ;
- historique chronologique des tours de Depth ;
- marqueurs `DOMAIN_EXHAUSTED` / `DEPTH_EXHAUSTED` nécessaires à l’idempotence Taxonomy ;
- incidents Taxonomy et points de reprise.

Ne doit **pas** être persisté comme mémoire durable Taxonomy :

```text
Subject FAIL de l’appel de création courant
```

Cette mémoire est détruite à la fin de l’appel.

Aucune donnée de réservoir n’est persistée dans le KernelBlueprint.

---

# 15. Validation

La spécification Taxonomy est valide uniquement si les invariants suivants sont tous vrais :

| Invariant | Résultat attendu |
|---|---|
| Taxonomy ne choisit jamais Depth/Domain | PASS |
| `depth/domain` restent immuables | PASS |
| occurrence de bassin distingue les tours répétés | PASS |
| 1 seul Subdomain par occurrence | PASS |
| Subdomain + SubjectBank créés dans le même travail | PASS |
| SubjectBank ≤ 50 sans remplissage forcé | PASS |
| Subject FAIL éphémère | PASS |
| lots équilibrés | PASS |
| VDI utilisé par Gemini pendant la création | PASS |
| IdeaBank 1..5 PASS par Subject préparé | PASS |
| Dominant Idea FAIL persistante hors IdeaBank | PASS |
| PASS anti-doublon contextualisé | PASS |
| LOOKBACK-2 cyclique | PASS |
| sélection = écriture = consommation | PASS |
| consommation immédiate après écriture Blueprint | PASS |
| dernière Idea → Subject UTILISÉ | PASS |
| dernière Idea du dernier Subject → Domain terminable | PASS |
| TAX-003 bloque tout épuisement prématuré | PASS |
| DEPTH_EXHAUSTED = fin d’un tour, pas fin globale | PASS |
| Taxonomy ne touche pas `cycle_target/cycle_completed` | PASS |
| erreur Gemini ≠ épuisement intellectuel | PASS |
| `BLOCKED` ≠ `PRODUCTION_ON_HOLD` | PASS |
| ReadyBank ne confirme pas la consommation Taxonomy | PASS |

### Audit croisé requis

La validation documentaire finale doit être effectuée contre :

```text
01_KernelBlueprint
02_KernelRotationPlanner — architecture active v3.3 à reconstruire
04_ValidationDominantIdeas — brides d’interface seulement
05_QuestionIntent — frontière aval
```

Le détail interne des règles VDI reste volontairement dans `04_ValidationDominantIdeas`.

---

# 16. Tests minimaux

Ces tests sont **le contrat minimal d’implantation** à exécuter après audit du code réel. Ils ne constituent pas une validation terminale de tout le moteur, qui reste différée à l’assemblage global.

1. Taxonomy lit seulement `depth + domain` comme entrée intellectuelle Blueprint.
2. Taxonomy n’écrit que les trois slots Taxonomy.
3. Deux appels du même `(Depth + Domain)` dans le même tour reprennent la même occurrence active.
4. Un retour au même `(Depth + Domain)` dans un nouveau tour ouvre une nouvelle occurrence.
5. Une occurrence ne peut avoir qu’un seul Subdomain officiel.
6. Un Subdomain candidat avec 0 Subject conforme n’est jamais persisté seul.
7. La SubjectBank ne dépasse jamais 50 Subjects.
8. Aucun Subject artificiel n’est ajouté pour atteindre 50.
9. Les doublons/synonymes proches sont refusés dans la même SubjectBank.
10. Un Subject PASS historique peut réapparaître sous un autre Subdomain si le contexte est légitime.
11. Un Subject FAIL n’existe plus dans la mémoire durable après l’appel.
12. `LOOKBACK-2` Subdomain fonctionne en premier cycle.
13. `LOOKBACK-2` traverse correctement `10 → 2`.
14. Répartition équilibrée : 41 → 9/8/8/8/8 ; 23 → 8/8/7 ; 11 → 6/5.
15. Aucun lot ne dépasse la capacité technique configurée.
16. Chaque Subject préparé obtient entre 1 et 5 PASS.
17. `0 PASS` empêche le Subject d’atteindre `DISPONIBLE`.
18. Dominant Idea PASS entre dans IdeaBank + mémoire PASS.
19. Dominant Idea FAIL entre dans FAIL Bank et jamais dans IdeaBank.
20. Les lots suivants voient les PASS/FAIL applicables des lots précédents.
21. Anti-doublon PASS compare la direction contextualisée, pas le mot isolé.
22. Même mot autorisé sous contexte intellectuel réellement différent.
23. `LOOKBACK-2` Dominant Ideas traverse `10 → 2`.
24. L’IdeaSlot sélectionné est exactement celui écrit dans le Blueprint.
25. Le même IdeaSlot est marqué `CONSUMED` immédiatement après l’écriture réussie.
26. Aucun `firstAvailableIdea()` post-écriture ne peut substituer une autre Idea.
27. Une Idea `CONSUMED` ne redevient jamais disponible après restart.
28. Avant la dernière Idea du Subject : aucun signal, Subject reste ACTIF.
29. Dernière Idea du Subject : Subject devient UTILISÉ ; le Blueprint courant ne change pas.
30. Dernière Idea du dernier Subject + Banks vides : `DOMAIN_EXHAUSTED` autorisé.
31. Contenu restant : `TAX-003`, aucun `DOMAIN_EXHAUSTED` transmis.
32. Huit Domaines terminés : `DEPTH_EXHAUSTED` produit une seule fois pour le tour.
33. Taxonomy ne modifie jamais `cycle_completed`.
34. Un second signal identique est compatible avec l’idempotence KRP (`NO-OP`).
35. 1 tentative + 3 retries maximum pour une erreur Gemini.
36. Trois opérations complètes consécutives non résolues → `BLOCKED` opérationnel.
37. Une réussite remet le compteur consécutif à zéro.
38. Une erreur Gemini ne produit aucun `CONSUMED`, `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED`.
39. Restart recharge occurrence + Banks + curseurs sans duplication.
40. Taxonomy n’envoie aucun `AVAILABLE` ni signal de régression KRP.

---

# 17. Statut

```text
Architecture :      100 %
Contrat :           100 %
Implémentation :      0 % à auditer/adapter
Validation code :     0 % dans cette phase
Spécification :      VERROUILLÉE
```

**03_Taxonomy v1.0 est le contrat d’entrée du futur `04_ValidationDominantIdeas` pour sa frontière d’utilisation par Gemini.**

La prochaine activité technique sur Taxonomy est interdite tant que l’Architecture Register actif n’est pas consolidé et que la révision KRP v3.3 nécessaire aux besoins globaux n’est pas reconstruite selon l’ordre officiel décidé.