# 03_Taxonomy — Spécification officielle

**Version :** 1.1  
**Date :** 2026-08-24  
**Statut de la spécification :** **VERROUILLÉE**  
**Architecture :** 100 %  
**Contrat :** 100 %  
**Statut d’implémentation :** NON IMPLANTÉE / code existant à auditer après fermeture documentaire  
**Position Blueprint :** SECTION 1 — CRÉATION INTELLECTUELLE  
**Décision de verrouillage :** DEC-120

> Cette v1.1 réécrit intégralement Taxonomy depuis la v1.0 en conservant sa mécanique intellectuelle interne valide : Subdomain unique, SubjectBank, IdeaBanks, ValidationDominantIdeas pendant la création, sélection exacte et consommation exacte.
>
> La correction architecturale porte sur la frontière KRP : Taxonomy **ne possède plus** `DOMAIN_EXHAUSTED` ni `DEPTH_EXHAUSTED`. Taxonomy constate seulement qu’il vient d’utiliser la dernière Dominant Idea du dernier Subject de l’occurrence de Domain qui lui a été attribuée et transmet ce **fait terminal**. Toute interprétation de rotation appartient ensuite à KRP v4.0.
>
> Taxonomy ne connaît pas le cadran global des Domaines, ne sait pas si le Domain qu’il termine est le dernier Domain actif du Depth et ne consulte jamais `DepthNeedMatrix`.

---

# 1. Mission

Taxonomy reçoit un territoire fixe déjà inscrit dans le KernelBlueprint :

```text
Blueprint.depth
+
Blueprint.domain
```

et gère le contenu intellectuel exploitable de l’occurrence active de ce couple :

```text
1 Subdomain unique
↓
SubjectBank ≤ 50 Subjects PASS
↓
IdeaBanks ≤ 5 Dominant Ideas PASS par Subject
↓
sélection exacte
↓
écriture Blueprint
↓
consommation exacte
```

Taxonomy écrit uniquement :

```text
subdomain_active
subject_active
dominant_idea_active
```

Lorsqu’il consomme la dernière Dominant Idea du dernier Subject de l’occurrence active, Taxonomy transmet uniquement un **fait terminal de consommation** à KRP.

Taxonomy ne décide jamais la rotation.

---

# 2. Position canonique

```text
KernelBlueprintFactory
↓
NOUVEAU Blueprint
↓
KRP
↓
écrit depth + domain
↓
FIN KRP
↓
Taxonomy
↓
reprend ou ouvre l’occurrence du depth + domain reçu
↓
sélectionne le triplet exact
↓
écrit subdomain_active + subject_active + dominant_idea_active
↓
consomme le même IdeaSlot
↓
si occurrence encore exploitable : silence
si dernière Idea du dernier Subject : fait terminal vers KRP
↓
FIN Taxonomy
↓
QuestionIntent
```

Taxonomy n’appelle pas KRP pour obtenir une rotation et KRP ne lit pas les Banks Taxonomy.

---

# 3. Responsabilités

Taxonomy doit :

1. recevoir un Blueprint avec `blueprint_id + depth + domain` déjà remplis ;
2. lire le `DepthContract` correspondant au `depth` ;
3. identifier l’occurrence Taxonomy active du `(Depth + Domain)` reçu ;
4. ouvrir une nouvelle occurrence seulement lorsqu’aucune occurrence exploitable correspondante n’existe ;
5. maintenir un seul Subdomain officiel par occurrence ;
6. faire créer par Gemini le Subdomain et jusqu’à 50 Subjects conformes dans le même travail ;
7. persister seulement les Subjects `PASS` dans la SubjectBank ;
8. garder les Subjects `FAIL` éphémères pendant l’appel courant ;
9. préparer les Subjects en lots équilibrés ;
10. faire utiliser à Gemini `04_ValidationDominantIdeas` pendant la création des Dominant Ideas ;
11. persister les Dominant Ideas `PASS` dans les IdeaBanks ;
12. persister les Dominant Ideas `FAIL` dans la FAIL Bank Dominant Ideas ;
13. maintenir les mémoires anti-doublon ;
14. sélectionner un IdeaSlot exact et permanent ;
15. écrire atomiquement `Subdomain + Subject + Dominant Idea` dans le Blueprint ;
16. marquer **le même IdeaSlot** `CONSUMED` immédiatement après l’écriture réussie ;
17. faire progresser silencieusement Idea → Subject → occurrence ;
18. reprendre le curseur de l’occurrence lorsque KRP réattribue ultérieurement le même `depth + domain` ;
19. détecter la dernière Dominant Idea du dernier Subject de l’occurrence ;
20. vérifier qu’aucun contenu exploitable de cette occurrence n’est oublié ;
21. transmettre une seule fois le fait terminal correspondant à KRP ;
22. persister ses Banks, occurrences, curseurs, historiques et état d’émission du fait terminal ;
23. gérer les erreurs techniques Gemini sans les transformer en fin de contenu ;
24. reprendre au plus petit point valide après incident.

---

# 4. Interdictions

Taxonomy ne doit jamais :

- choisir ou modifier `depth` ;
- choisir ou modifier `domain` ;
- créer le KernelBlueprint ;
- écrire `blueprint_id` ;
- écrire `kernel_code` ;
- écrire les CognitiveSlots ou TranslationSlots ;
- stocker ses Banks dans le Blueprint ;
- lire ou modifier le `RotationState` KRP ;
- suivre le DomainCycle KRP ;
- connaître les états `VISIBLE / ESTOMPÉ` des autres Domaines ;
- déterminer si le Domain courant est le dernier Domain actif d’un Depth ;
- produire ou posséder le moteur `DOMAIN_EXHAUSTED` ;
- produire ou posséder le moteur `DEPTH_EXHAUSTED` ;
- consulter ou modifier `DepthNeedMatrix` ;
- maintenir `cycle_target`, `cycle_completed` ou `cycle_remaining` ;
- choisir le prochain Domain ;
- choisir le prochain Depth ;
- produire `PRODUCTION_ON_HOLD` ;
- émettre un statut à chaque Idea intermédiaire ;
- émettre un statut à chaque retour du même Domain si rien ne se termine ;
- émettre `AVAILABLE` ou tout signal inverse de rotation ;
- considérer une erreur technique comme une fin de contenu ;
- consommer une autre Idea que celle écrite dans le Blueprint ;
- rechercher un nouveau `firstAvailableIdea()` après l’écriture ;
- attendre ReadyBank pour consommer l’Idea sélectionnée ;
- forcer 50 Subjects ;
- forcer 5 Dominant Ideas ;
- créer plusieurs Subdomains dans la même occurrence ;
- réutiliser comme occurrence active une occurrence déjà entièrement consommée lorsque KRP réattribue ce même `depth + domain` dans un tour futur.

---

# 5. Entrées

## 5.1 KernelBlueprint

Taxonomy lit fonctionnellement :

```text
depth
domain
```

`blueprint_id` peut servir de référence technique mais n’est pas une donnée de création Taxonomy.

Précondition :

```text
blueprint_id = REMPLI
depth = REMPLI
domain = REMPLI
subdomain_active = NULL
subject_active = NULL
dominant_idea_active = NULL
```

## 5.2 DepthContract

Taxonomy lit :

```text
DepthContract[Blueprint.depth]
```

selon le contrat externe propriétaire du niveau de profondeur.

## 5.3 Contexte interne d’occurrence

Une occurrence Taxonomy est liée à :

```text
Depth
+
Domain
+
occurrence historique propre à ce bassin
```

Le numéro exact de tour KRP n’a pas à être transporté dans le Blueprint.

Règle de résolution :

```text
KRP réattribue depth + domain
↓
Taxonomy cherche la plus récente occurrence exploitable correspondante
├── existe → reprend cette occurrence et son curseur
└── aucune occurrence exploitable → ouvre une nouvelle occurrence
```

Pourquoi cette règle est sûre : un Domain dont l’occurrence précédente a été entièrement consommée est retiré par KRP de son tour courant. S’il réapparaît plus tard dans un Blueprint, il s’agit donc d’une nouvelle occurrence de production.

---

# 6. Sorties

## 6.1 Écriture Blueprint

Taxonomy écrit exactement :

```text
subdomain_active
subject_active
dominant_idea_active
```

Les trois valeurs sont écrites comme un triplet atomique.

## 6.2 État Taxonomy persistant

Taxonomy persiste notamment :

- occurrences ;
- Subdomain officiel ;
- SubjectBank ;
- IdeaBanks ;
- FAIL Bank Dominant Ideas ;
- mémoires PASS ;
- états Subject/Idea ;
- curseur de consommation ;
- historique anti-doublon ;
- marqueur indiquant qu’un fait terminal a déjà été émis pour l’occurrence ;
- incidents et points de reprise.

## 6.3 Fait terminal vers KRP

Taxonomy peut produire une seule catégorie d’information de rotation :

```text
FAIT TERMINAL DE DOMAIN
```

Sémantique exacte :

```text
la dernière Dominant Idea
du dernier Subject encore exploitable
de l’occurrence du Domain attribué
vient d’être utilisée avec succès
```

Le contrat logique transporte au minimum l’identité nécessaire pour rattacher ce fait au territoire :

```text
depth
domain
```

et peut transporter comme références techniques :

```text
subject_slot_id
idea_slot_id
blueprint_id
occurrence_id
```

Le nom technique final de l’événement peut varier à l’implantation. Il ne doit pas s’appeler comme un moteur interne KRP si cela crée une confusion d’ownership.

Ce fait alimente ensuite le moteur interne KRP :

```text
DOMAIN_EXHAUSTED
```

Taxonomy n’émet jamais `DEPTH_EXHAUSTED`.

---

# 7. Slots Blueprint

| Slot | Propriétaire | Taxonomy | Règle |
|---|---|---|---|
| `blueprint_id` | KernelBlueprintFactory | lecture technique si nécessaire | jamais modifié |
| `depth` | KRP | **LIT** | immuable |
| `domain` | KRP | **LIT** | immuable |
| `subdomain_active` | Taxonomy | **ÉCRIT** | triplet write-once |
| `subject_active` | Taxonomy | **ÉCRIT** | même écriture atomique |
| `dominant_idea_active` | Taxonomy | **ÉCRIT** | même IdeaSlot que celui consommé |
| `kernel_code` | QuestionIntent | aucun accès fonctionnel | produit après Taxonomy |

---

# 8. Données internes

## 8.1 Subdomain

Une occurrence contient exactement un Subdomain officiel.

Lifecycle :

```text
CRÉÉ
↓
ACTIF
↓
UTILISÉ
```

## 8.2 SubjectBank

Plafond :

```text
0 < Subjects PASS ≤ 50
```

Chaque Subject PASS possède son IdeaBank.

Lifecycle :

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

## 8.3 IdeaBank

Chaque Subject préparé possède :

```text
1..5 Dominant Ideas PASS
```

Lifecycle IdeaSlot :

```text
DISPONIBLE
↓
ACTIF
↓
CONSUMED
```

`CONSUMED` est irréversible.

## 8.4 FAIL Bank Dominant Ideas

Une Dominant Idea `FAIL` :

- est persistée dans la FAIL Bank ;
- n’entre jamais dans l’IdeaBank exploitable ;
- ne peut jamais être sélectionnée comme `dominant_idea_active`.

## 8.5 Mémoire anti-doublon

Taxonomy conserve :

- historique des Subdomains utilisés ;
- Subjects PASS historiques ;
- mémoire PASS Dominant Ideas ;
- FAIL Bank Dominant Ideas ;
- historique chronologique nécessaire à `LOOKBACK-2` ;
- mémoire FAIL Subject uniquement pendant l’appel courant.

---

# 9. Mécanismes de création

## 9.1 Création Subdomain + SubjectBank

Gemini reçoit :

```text
Depth
Domain
DepthContract[Depth]
règles Taxonomy
exclusions anti-doublon applicables
```

et doit retourner dans le même travail :

```text
1 Subdomain viable
+
1..50 Subjects PASS
```

Un Subdomain candidat sans Subject conforme est abandonné avant persistance.

## 9.2 Lots équilibrés

Pour `N` Subjects PASS et une capacité technique `C = 10` :

```text
nombre minimal d’appels = ceil(N / C)
```

Les lots sont répartis aussi également que possible, avec un écart maximal de 1 Subject.

Exemples :

```text
50 → 10/10/10/10/10
41 → 9/8/8/8/8
32 → 8/8/8/8
23 → 8/8/7
17 → 9/8
11 → 6/5
```

## 9.3 Dominant Ideas + ValidationDominantIdeas

Gemini effectue dans le même travail :

```text
création candidates
+
application règles ValidationDominantIdeas
↓
PASS / FAIL par Subject exact
```

Contrat :

- maximum 5 PASS par Subject ;
- minimum 1 PASS pour un Subject préparé avec succès ;
- `0 PASS` = anomalie de préparation, pas épuisement ;
- PASS et FAIL restent rattachés au Subject exact.

## 9.4 Alimentation des Banks

```text
Subject PASS → SubjectBank persistante
Subject FAIL → mémoire éphémère de l’appel
Dominant Idea PASS → IdeaBank + mémoire PASS
Dominant Idea FAIL → FAIL Bank persistante
```

---

# 10. Mécanisme de consommation

## 10.1 Sélection exacte

Taxonomy choisit :

```text
Subdomain de l’occurrence
+
Subject ACTIF/DISPONIBLE
+
IdeaSlot DISPONIBLE exact
```

L’identité de l’IdeaSlot est conservée jusqu’à la consommation.

## 10.2 Écriture Blueprint

Taxonomy écrit atomiquement :

```text
subdomain_active
subject_active
dominant_idea_active
```

Aucun triplet partiel n’est valide.

## 10.3 Consommation exacte

Après écriture réussie :

```text
IdeaSlot sélectionné
=
Dominant Idea écrite
=
IdeaSlot marqué CONSUMED
```

Aucune nouvelle sélection n’intervient entre écriture et consommation.

## 10.4 Progression dans un Subject

Tant qu’une Idea PASS reste disponible :

```text
Idea écrite
↓
CONSUMED
↓
aucun fait terminal
↓
curseur conservé
```

Quand la dernière Idea PASS du Subject est consommée :

```text
Subject → UTILISÉ
```

Si d’autres Subjects restent :

```text
aucun fait terminal de Domain
```

Lors de la **prochaine réattribution du même `depth + domain` par KRP**, Taxonomy reprend cette occurrence et sélectionne le prochain Subject disponible.

Il ne suppose jamais que le Blueprint immédiatement suivant appartient au même Domain.

---

# 11. Fin d’une occurrence de Domain

## 11.1 Condition terminale

Après écriture réussie et consommation du même IdeaSlot :

```text
dernier Subject
↓
dernière Dominant Idea
↓
IdeaSlot CONSUMED
↓
Subject UTILISÉ
↓
aucun autre Subject exploitable
↓
aucune autre Idea exploitable
```

Condition de sécurité :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

## 11.2 Si du contenu reste

Taxonomy n’émet aucun fait terminal.

Il reprend son curseur au contenu restant.

Code contractuel :

```text
TAX-003 — TERMINAL_DOMAIN_FACT_BLOCKED_REMAINING_CONTENT
```

## 11.3 Si l’occurrence est réellement terminée

Taxonomy :

1. marque le Subdomain `UTILISÉ` ;
2. marque l’occurrence comme entièrement consommée ;
3. persiste l’état terminal ;
4. émet **une seule fois** le fait terminal vers KRP ;
5. termine.

Taxonomy ne fait aucune vérification sur les sept autres Domaines.

Taxonomy ne déclenche aucun changement de Depth.

---

# 12. Communication

## 12.1 KRP → Taxonomy

```text
KRP
→ Blueprint avec depth + domain
→ FIN KRP
→ Taxonomy
```

## 12.2 Taxonomy → KRP

```text
Taxonomy
→ fait terminal de consommation du Domain
→ FIN Taxonomy
```

Ce fait ne contient aucune instruction :

- pas de prochain Domain ;
- pas de prochain Depth ;
- pas de fin de Depth ;
- pas de HOLD.

KRP décide ensuite selon son propre contrat v4.0.

## 12.3 Taxonomy ↔ Gemini

Taxonomy fournit à Gemini les contrats de création et les mémoires nécessaires.

Gemini retourne les structures candidates PASS/FAIL définies plus haut.

## 12.4 Taxonomy → Admin/Ops

Taxonomy transmet les incidents techniques avec contexte, nombre de tentatives et point de reprise.

## 12.5 ReadyBank

ReadyBank ne confirme jamais une consommation Taxonomy et ne fait pas avancer les curseurs Taxonomy.

---

# 13. Contrats

## TAX-C01 — Territoire fixe

`depth + domain` sont immuables pendant une exécution Taxonomy.

## TAX-C02 — Occurrence active

Taxonomy reprend la plus récente occurrence exploitable du `depth + domain` reçu.

Si aucune occurrence exploitable n’existe, il en ouvre une nouvelle.

## TAX-C03 — Subdomain unique

Une occurrence possède exactement un Subdomain officiel.

## TAX-C04 — Création atomique

Subdomain + SubjectBank sont créés dans le même travail Gemini.

## TAX-C05 — Plafonds

```text
SubjectBank ≤ 50
IdeaBank ≤ 5 PASS / Subject
```

Aucun remplissage forcé.

## TAX-C06 — Subject exploitable

Un Subject préparé avec succès possède au moins 1 Dominant Idea PASS.

## TAX-C07 — ValidationDominantIdeas

VDI est utilisé par Gemini pendant la création des Ideas et ne constitue pas un moteur autonome relisant le Blueprint.

## TAX-C08 — PASS/FAIL

```text
Subject PASS = persistant
Subject FAIL = éphémère appel courant
Idea PASS = persistante exploitable
Idea FAIL = persistante hors IdeaBank exploitable
```

## TAX-C09 — Exactitude

```text
Idea sélectionnée = Idea écrite = Idea CONSUMED
```

## TAX-C10 — Consommation immédiate

`CONSUMED` suit l’écriture réussie et n’attend pas ReadyBank.

## TAX-C11 — Progression silencieuse

Aucun fait terminal pendant les Ideas intermédiaires ou le simple passage Subject→Subject.

## TAX-C12 — Fait terminal unique

Une occurrence entièrement consommée émet au maximum un fait terminal normal vers KRP.

## TAX-C13 — Garde contenu restant

Aucun fait terminal si `remaining_subjects > 0` ou `remaining_ideas > 0`.

## TAX-C14 — Aucun DEPTH_EXHAUSTED Taxonomy

Taxonomy ne produit jamais `DEPTH_EXHAUSTED`.

## TAX-C15 — Aucun état global Domain KRP

Taxonomy ne maintient ni `VISIBLE/ESTOMPÉ`, ni DomainCycle, ni nombre de Domaines actifs.

## TAX-C16 — Aucun besoin global

Taxonomy ne connaît ni `cycle_target`, ni `cycle_completed`, ni `cycle_remaining`.

## TAX-C17 — LOOKBACK-2 cyclique

Les historiques anti-doublon traversent `Depth 10 → Depth 2` selon les occurrences chronologiques réellement produites.

---

# 14. États et transitions

## 14.1 Subdomain

```text
CRÉÉ → ACTIF → UTILISÉ
```

## 14.2 Subject

```text
CRÉÉ → PRÉPARÉ → DISPONIBLE → ACTIF → UTILISÉ
```

## 14.3 IdeaSlot

```text
DISPONIBLE → ACTIF → CONSUMED
```

## 14.4 Occurrence

```text
OUVERTE
↓
EN_CONSOMMATION
↓ dernière Idea du dernier Subject consommée
ÉPUISÉE
```

`ÉPUISÉE` ne signifie pas `DOMAIN_EXHAUSTED` au sens moteur KRP. Il signifie seulement que l’occurrence Taxonomy n’a plus de contenu exploitable.

## 14.5 Fait terminal

```text
ÉPUISÉE
↓ fait non encore émis
FAIT_TERMINAL_ÉMIS
```

Replay d’émission déjà confirmé :

```text
NO-OP
```

## 14.6 Opérationnel

```text
RUNNING
BLOCKED
```

---

# 15. Persistance

Doivent survivre :

- occurrences ;
- Subdomain de chaque occurrence ;
- SubjectBanks PASS ;
- IdeaBanks PASS ;
- FAIL Bank Dominant Ideas ;
- mémoires PASS ;
- états Subject ;
- états IdeaSlot ;
- curseurs ;
- historiques LOOKBACK ;
- état `ÉPUISÉE` d’une occurrence ;
- marqueur d’émission du fait terminal ;
- incidents et points de reprise.

Ne doit pas être persisté durablement :

```text
Subject FAIL de l’appel de création courant
```

Aucune donnée de Bank n’entre dans le KernelBlueprint.

---

# 16. Erreurs techniques Gemini

Erreurs retryables :

- timeout ;
- connexion interrompue ;
- rate limit/quota temporaire ;
- indisponibilité service ;
- erreur réseau/API ;
- réponse vide ;
- réponse tronquée/inexploitable ;
- contrat de sortie non exécuté jusqu’au bout.

Politique :

```text
1 tentative initiale
+ 3 retries
```

Après épuisement d’une opération :

```text
GEMINI_TECHNICAL_ERROR_UNRESOLVED
```

Après trois opérations intellectuelles distinctes consécutives non résolues :

```text
INTELLECTUAL_CREATION_UNAVAILABLE
↓
BLOCKED
```

Toute réussite remet le compteur consécutif à zéro.

Aucune erreur technique ne :

- consomme une Idea ;
- épuise une occurrence ;
- produit un fait terminal.

---

# 17. Cas limites

1. **Moins de 50 Subjects** → accepté.
2. **1 seul Subject PASS** → accepté s’il possède au moins 1 Idea PASS.
3. **Moins de 5 Ideas PASS** → accepté à partir de 1.
4. **0 PASS pour Subject accepté** → anomalie de préparation, pas fin de contenu.
5. **Subdomain candidat sans Subject** → candidat abandonné avant persistance.
6. **Restart** → recharge occurrence + Banks + curseur sans rendre `CONSUMED` disponible.
7. **KRP réattribue le même Domain avant épuisement** → reprise de la même occurrence exploitable.
8. **KRP réattribue le même Domain après ancienne occurrence épuisée** → nouvelle occurrence.
9. **Dernière Idea d’un Subject mais autres Subjects restent** → Subject UTILISÉ, aucun fait terminal.
10. **Dernière Idea du dernier Subject mais contenu résiduel détecté** → TAX-003, aucun fait terminal.
11. **Occurrence réellement épuisée** → un fait terminal maximum.
12. **Replay technique du fait terminal** → NO-OP après émission confirmée.
13. **Taxonomy ne sait pas si d’autres Domaines KRP restent** → comportement normal ; aucune tentative de le déterminer.
14. **Depth 10 → 2** → historique anti-doublon continue sans reset.
15. **Erreur Gemini** → aucun effet métier de consommation ou de fin.

---

# 18. Validation contractuelle

La spécification est conforme seulement si :

| Invariant | Attendu |
|---|---|
| Taxonomy lit seulement depth/domain fonctionnellement | PASS |
| Taxonomy écrit seulement ses 3 slots | PASS |
| 1 Subdomain par occurrence | PASS |
| SubjectBank ≤ 50 sans remplissage | PASS |
| IdeaBank 1..5 PASS par Subject préparé | PASS |
| VDI utilisé pendant création | PASS |
| sélection = écriture = consommation | PASS |
| consommation immédiate | PASS |
| progression intermédiaire silencieuse | PASS |
| reprise du même bassin lors du prochain retour KRP | PASS |
| aucun `DOMAIN_EXHAUSTED` moteur dans Taxonomy | PASS |
| aucun `DEPTH_EXHAUSTED` dans Taxonomy | PASS |
| aucun DomainCycle / VISIBLE / ESTOMPÉ dans Taxonomy | PASS |
| aucune DepthNeedMatrix dans Taxonomy | PASS |
| fait terminal seulement à la dernière Idea du dernier Subject | PASS |
| fait terminal unique par occurrence | PASS |
| TAX-003 bloque tout fait prématuré | PASS |
| erreur Gemini ≠ fin de contenu | PASS |

Audit croisé :

```text
01_KernelBlueprint v2.0
02_KernelRotationPlanner v4.0 / DEC-119
04_ValidationDominantIdeas — interface
05_QuestionIntent — frontière aval
```

---

# 19. Tests minimaux

1. lit `depth + domain` du Blueprint ;
2. n’écrit que `subdomain_active + subject_active + dominant_idea_active` ;
3. même `depth + domain` avec occurrence encore exploitable → reprise même occurrence ;
4. même `depth + domain` avec ancienne occurrence épuisée → nouvelle occurrence ;
5. une occurrence = un Subdomain officiel ;
6. SubjectBank ≤ 50 ;
7. aucun remplissage forcé ;
8. Subject FAIL éphémère ;
9. lots équilibrés ;
10. chaque Subject préparé = 1..5 PASS ;
11. Idea FAIL jamais exploitable ;
12. LOOKBACK-2 fonctionne, y compris 10→2 ;
13. Idea sélectionnée = Idea écrite ;
14. même Idea immédiatement `CONSUMED` après écriture ;
15. aucun `firstAvailableIdea()` post-écriture ne substitue une autre Idea ;
16. Idea CONSUMED irréversible après restart ;
17. Ideas intermédiaires → aucun fait terminal ;
18. dernière Idea d’un Subject avec autres Subjects → aucun fait terminal ;
19. après retour KRP sur le même Domain → prochain Subject/Idea selon curseur ;
20. dernière Idea du dernier Subject + Banks vides → occurrence ÉPUISÉE ;
21. occurrence ÉPUISÉE → fait terminal transmis une seule fois ;
22. contenu restant → TAX-003 et aucun fait ;
23. Taxonomy ne produit jamais `DOMAIN_EXHAUSTED` comme moteur ;
24. Taxonomy ne produit jamais `DEPTH_EXHAUSTED` ;
25. Taxonomy ne lit jamais `DepthNeedMatrix` ;
26. Taxonomy ne compte jamais les Domaines VISIBLE/ESTOMPÉ ;
27. Taxonomy ne choisit jamais prochain Domain/Depth ;
28. erreur Gemini → aucun CONSUMED / occurrence ÉPUISÉE / fait terminal ;
29. 1+3 retries Gemini ;
30. trois opérations consécutives non résolues → BLOCKED ;
31. réussite remet le compteur d’échecs consécutifs à zéro ;
32. ReadyBank ne confirme jamais la consommation Taxonomy.

---

# 20. Statut

```text
Architecture :      100 %
Contrat :           100 %
Implémentation :      0 % à auditer/adapter
Validation code :     0 %
Spécification :      VERROUILLÉE v1.1
```

**03_Taxonomy v1.1 est désormais alignée sur `02_KernelRotationPlanner v4.0`.**

Prochaine opération autorisée :

```text
AUDIT-03-v1.1
↓
audit du code Taxonomy réel
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
↓
implantation Taxonomy dans son propre bloc
↓
validation terminale Taxonomy
```

Aucune implantation KRP et Taxonomy ne doit être conduite comme un même bloc.