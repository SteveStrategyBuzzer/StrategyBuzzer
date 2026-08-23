# 02_KernelRotationPlanner — Spécification canonique

**Version :** 3.5  
**Date :** 2026-08-23  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Architecture intellectuelle :** 100 %  
**Contrat intellectuel :** 100 %  
**Implémentation :** À RÉAUDITER CONTRE v3.5  
**Validation terminale code :** NON  
**Décision de verrouillage :** DEC-116

> Cette v3.5 remplace intégralement la v3.4. Elle corrige la frontière Taxonomy → KRP : Taxonomy **parle à KRP** par un signal factuel d’épuisement du Domain courant. KRP ne lit pas les Banks Taxonomy et ne poll pas un état Taxonomy partagé.
>
> Taxonomy n’ordonne jamais le prochain Domain ni le prochain Depth. Il n’envoie pas `DEPTH_EXHAUSTED`. KRP reste l’autorité unique du contrat de rotation.
>
> Les besoins éventuels des futures Phases 1 et 2 restent **RÉSERVÉS / NON SPÉCIFIÉS** et ne peuvent modifier silencieusement ce contrat.

---

# 1. Mission

`KernelRotationPlanner` (KRP) est l’autorité unique de rotation du moteur intellectuel.

Pour chaque **nouveau KernelBlueprint déjà créé**, KRP choisit et écrit exactement :

```text
depth + domain
```

KRP prend sa décision à partir de :

1. `RotationState` — son propre état persistant ;
2. `DepthNeedMatrix` — le besoin quantitatif global par Depth ;
3. les faits d’épuisement Domain déjà transmis par Taxonomy et persistés dans `RotationState`.

Taxonomy fournit la réalité intellectuelle de ses Banks. `DepthNeedMatrix` fournit la réalité quantitative globale. KRP transforme ces réalités en décision de rotation.

---

# 2. Position canonique

## 2.1 Pendant le travail Taxonomy

```text
KRP a attribué depth + domain
↓
Taxonomy travaille ce territoire
↓
si du contenu exploitable reste
→ aucun signal de rotation

si les Banks du Domain sont réellement vides
→ Taxonomy envoie DOMAIN_EXHAUSTED(depth, domain)
→ KRP reçoit le FAIT
→ KRP persiste VISIBLE → ESTOMPÉ dans RotationState
→ aucune nouvelle rotation n’est lancée à cet instant
```

## 2.2 Déclenchement du noyau suivant

```text
noyau courant termine son pipeline
↓
ReadyBank reçoit le noyau
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle/orchestration externe
↓
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint + blueprint_id
↓
KernelRotationPlanner
↓
lecture RotationState
+
lecture DepthNeedMatrix
↓
KRP applique SON contrat de rotation
↓
Blueprint.fillRotation(depth, domain)
↓
persistance KRP
↓
FIN KRP
↓
porte vers Taxonomy
```

Règles absolues :

- ReadyBank ne remet jamais l’ancien Blueprint à KRP ;
- `CURRENT_KERNEL_RECEIVED` déclenche le lifecycle mais ne décide aucune rotation ;
- Taxonomy peut informer KRP d’un Domain vide, mais ne déclenche pas le prochain Blueprint ;
- la réception de `DOMAIN_EXHAUSTED` ne choisit pas immédiatement un nouveau Domain ;
- le Blueprint reçu par KRP est toujours une nouvelle enveloppe créée par Factory.

---

# 3. Responsabilités

KRP doit :

1. recevoir un nouveau Blueprint avec `blueprint_id` rempli et `depth/domain` vides ;
2. charger son `RotationState` ;
3. consulter `DepthNeedMatrix` ;
4. recevoir le signal factuel `DOMAIN_EXHAUSTED(depth, domain)` de Taxonomy ;
5. valider que ce signal concerne le territoire KRP actif ;
6. persister `VISIBLE → ESTOMPÉ` pour le Domain concerné ;
7. traiter un replay déjà appliqué comme `NO-OP` ;
8. ne déclencher aucune rotation simplement parce que le signal vient d’arriver ;
9. connaître le `DepthCycle` officiel ;
10. connaître le `DomainCycle` officiel ;
11. conserver le même `depth + domain` au prochain Blueprint tant que le Domain courant reste `VISIBLE` ;
12. sélectionner lui-même le prochain Domain `VISIBLE` lorsque le Domain courant est `ESTOMPÉ` ;
13. constater lui-même qu’un tour est terminé lorsque les huit Domaines sont `ESTOMPÉ` ;
14. fermer lui-même ce tour ;
15. incrémenter `cycle_completed[depth]` exactement une fois par tour fermé ;
16. demander à `DepthNeedMatrix` le prochain Depth encore nécessaire ;
17. revenir vers Depth 2 après Depth 10 si un besoin global subsiste ;
18. produire `PRODUCTION_ON_HOLD` seulement lorsque toutes les cibles globales sont satisfaites ;
19. écrire uniquement `depth + domain` dans le Blueprint ;
20. persister toute transition KRP avant de la considérer commise ;
21. laisser le même Blueprint prêt pour Taxonomy.

---

# 4. Interdictions

KRP ne doit jamais :

- créer le KernelBlueprint ;
- générer ou modifier `blueprint_id` ;
- recycler l’ancien Blueprint reçu par ReadyBank ;
- traiter `CURRENT_KERNEL_RECEIVED` comme une commande de rotation ;
- lire directement les SubjectBanks, IdeaBanks ou curseurs internes de Taxonomy ;
- poller Taxonomy pour découvrir si un Domain est vide ;
- recevoir de Taxonomy une commande « va au prochain Domain » ;
- recevoir de Taxonomy une commande « va au prochain Depth » ;
- recevoir de Taxonomy `DEPTH_EXHAUSTED` comme décision de fin de tour ;
- changer de Domain au moment même de la réception du signal factuel ;
- écrire `subdomain_active`, `subject_active`, `dominant_idea_active` ou `kernel_code` ;
- créer ou valider du contenu intellectuel ;
- utiliser `Général` comme domaine de création ;
- déclarer HOLD parce que Depth 10 vient d’être terminé ;
- inventer une logique Phase 1 ou Phase 2.

---

# 5. Entrées

## 5.1 Nouveau KernelBlueprint

```text
blueprint_id = REMPLI
depth = NULL
domain = NULL
subdomain_active = NULL
subject_active = NULL
dominant_idea_active = NULL
kernel_code = NULL
```

## 5.2 RotationState

KRP possède et persiste au minimum la réalité nécessaire pour garantir :

- Depth courant ;
- Domain courant ;
- position dans le DomainCycle ;
- Domaines `VISIBLE / ESTOMPÉ` du tour courant ;
- tour ouvert/fermé ;
- fermeture d’un même tour exactement une fois ;
- état opérationnel normal/bloqué/hold.

Le nom exact des colonnes/classes n’est pas contractuel.

## 5.3 DepthNeedMatrix

Autorité quantitative globale :

```text
cycle_target[2]  = 250
cycle_target[4]  = 300
cycle_target[6]  = 350
cycle_target[7]  = 350
cycle_target[8]  = 350
cycle_target[9]  = 250
cycle_target[10] = 100
```

```text
cycle_remaining[depth]
= max(0, cycle_target[depth] - cycle_completed[depth])
```

`DepthNeedMatrix` indique quels Depths ont encore besoin de tours. Il ne décide pas qu’un Domain Taxonomy est vide.

## 5.4 Signal factuel Taxonomy

Interface sémantique active :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification unique :

```text
Pour ce territoire actif,
Taxonomy a vérifié qu’il ne reste plus
aucun contenu intellectuel exploitable
pour ce Domain.
```

Garde Taxonomy avant émission :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Taxonomy n’envoie aucun signal si du contenu exploitable reste.

Le signal :

- est un **fait**, pas une commande ;
- ne contient aucun prochain Domain ;
- ne contient aucun prochain Depth ;
- ne ferme pas lui-même le tour ;
- ne déclenche pas la création d’un nouveau Blueprint ;
- doit concerner le territoire KRP actif au moment de son émission.

Le transport technique et l’identité technique utilisée pour dédupliquer les replays peuvent varier ; ils ne changent pas cette sémantique métier.

---

# 6. Sorties

## 6.1 Sortie normale vers Taxonomy

Le même Blueprint ressort de KRP avec uniquement :

```text
blueprint_id           = REMPLI
depth                  = REMPLI
domain                 = REMPLI
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

KRP s’arrête ici.

## 6.2 Sortie opérationnelle

`PRODUCTION_ON_HOLD` est permis uniquement si :

```text
pour tous les Depths :
cycle_completed[depth] >= cycle_target[depth]
```

et qu’aucune transition KRP n’attend de persistance.

---

# 7. Slots Blueprint

KRP possède exactement :

```text
depth
domain
```

Écriture unique :

```text
Blueprint.fillRotation(depth, domain)
```

Aucun autre slot Blueprint n’appartient à KRP.

---

# 8. Cycles officiels

## 8.1 DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → prochain Depth encore nécessaire
```

Après 10, la recherche reprend cycliquement à 2.

## 8.2 DomainCycle

```text
Géographie
→ Histoire
→ Faune
→ Art
→ Sport
→ Cinéma
→ Cuisine
→ Science
```

`Général` est exclu de la création.

---

# 9. Mécanisme de rotation

## 9.1 Premier démarrage absolu

Sans territoire précédent :

1. KRP consulte `DepthNeedMatrix` ;
2. sélectionne le premier Depth encore nécessaire ;
3. ouvre son tour avec les huit Domaines `VISIBLE` ;
4. sélectionne Géographie ;
5. écrit `depth + domain` dans le nouveau Blueprint.

## 9.2 Domain encore exploitable

Tant que Taxonomy n’a pas envoyé de `DOMAIN_EXHAUSTED` pour le territoire actif :

```text
Domain courant reste VISIBLE
↓
prochain ReadyBank/CURRENT_KERNEL_RECEIVED
↓
Factory crée nouveau Blueprint
↓
KRP conserve le même Depth + Domain
```

La création d’un nouveau Blueprint ne provoque donc jamais à elle seule une rotation de Domain.

## 9.3 Réception d’un Domain vide

Lorsque Taxonomy a réellement épuisé le Domain actif :

```text
Taxonomy
→ DOMAIN_EXHAUSTED(depth, domain)
↓
KRP valide le territoire actif
↓
KRP persiste VISIBLE → ESTOMPÉ
↓
FIN de la réception du signal
```

Aucun nouveau `depth + domain` n’est choisi à cette étape.

## 9.4 Blueprint suivant après Domain épuisé

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ Factory crée nouveau Blueprint
→ KRP lit SON RotationState
```

Si le Domain courant est `ESTOMPÉ` :

```text
KRP choisit le prochain Domain VISIBLE
selon SON DomainCycle
```

Taxonomy n’a pas choisi ce Domain.

## 9.5 Fin du tour de Depth

Si KRP constate dans `RotationState` que les huit Domaines du tour sont `ESTOMPÉ` au moment où un nouveau Blueprint lui est remis :

```text
KRP ferme SON tour
↓
persiste la fermeture
↓
cycle_completed[depth] += 1 exactement une fois
↓
DepthNeedMatrix
↓
prochain Depth encore nécessaire
↓
ouverture d’un nouveau tour de ce Depth
↓
Géographie VISIBLE
```

Taxonomy ne produit pas `DEPTH_EXHAUSTED`.

## 9.6 Après Depth 10

Si le tour Depth 10 est fermé et que des besoins globaux subsistent :

```text
DepthNeedMatrix
↓
recherche cyclique depuis 2
↓
prochain Depth encore nécessaire
```

Si Depth 2 a encore un besoin, KRP repart sur Depth 2 avec un nouveau tour.

---

# 10. Ownership canonique — DEC-116

```text
Taxonomy
= propriétaire de ses réservoirs
= vérifie la réalité intellectuelle du Domain actif
= pousse DOMAIN_EXHAUSTED(depth, domain) lorsque le Domain est réellement vide
= aucune autorité de rotation

DepthNeedMatrix
= autorité quantitative globale des tours encore nécessaires
= aucune autorité sur les Banks Taxonomy

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclencheur lifecycle du noyau suivant
= aucune autorité de rotation

KernelBlueprintFactory
= crée le nouveau Blueprint

KernelRotationPlanner
= autorité UNIQUE de rotation
= reçoit/persiste le fait Domain vide
= décide seul du prochain Domain
= décide seul de la fermeture du tour
= décide seul du prochain Depth
= décide seul de HOLD
```

La v3.4 qui disait que KRP devait « lire la réalité Taxonomy » est superseded.

L’ancienne commande :

```text
Taxonomy → DEPTH_EXHAUSTED(depth)
```

reste superseded et n’appartient pas au contrat actif.

---

# 11. États KRP

## 11.1 Domain

```text
VISIBLE
↓ DOMAIN_EXHAUSTED factuel reçu + persistance KRP
ESTOMPÉ
```

Dans un même tour :

```text
ESTOMPÉ → VISIBLE
```

est interdit.

## 11.2 Tour

```text
OPEN
↓ 8 Domaines ESTOMPÉ constatés par KRP lors du prochain cycle
CLOSED
```

Un retour futur au même Depth crée un nouveau tour avec huit Domaines `VISIBLE`.

## 11.3 Opérationnel

```text
NORMAL
BLOCKED
PRODUCTION_ON_HOLD
```

---

# 12. Persistance et idempotence

## 12.1 Signal Domain

Première réception valide :

```text
DOMAIN_EXHAUSTED(depth, domain)
↓
VISIBLE → ESTOMPÉ
↓
COMMIT RotationState
```

Replay déjà appliqué pour le même état actif :

```text
NO-OP
```

Un signal contradictoire ou ne correspondant pas au territoire KRP actif ne modifie pas la rotation.

## 12.2 Fermeture de tour

La fermeture appartient à KRP et ne survient qu’au cycle de sélection déclenché par le nouveau Blueprint.

Un même tour `CLOSED` ne peut incrémenter `cycle_completed` qu’une seule fois, y compris après reprise/retry.

L’identité technique garantissant cet `exactly once` appartient à l’implantation de `RotationState` et n’est pas une responsabilité Taxonomy.

## 12.3 Échec technique de persistance

Politique :

```text
1 tentative initiale
+ 3 retries techniques maximum
```

Codes :

```text
KRP-002 — DOMAIN_EXHAUSTED_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
```

Après épuisement : `BLOCKED` et aucune nouvelle progression basée sur un état incertain.

Une anomalie sémantique de signal n’est pas une panne DB et ne produit pas `KRP-002`.

---

# 13. Communication inter-modules

## 13.1 Taxonomy → KRP

```text
Taxonomy
→ DOMAIN_EXHAUSTED(depth, domain)
→ KRP persiste le fait dans RotationState
```

Cette communication ne crée aucun Blueprint et ne choisit aucune rotation.

## 13.2 ReadyBank → lifecycle

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ lifecycle
→ Factory
→ nouveau Blueprint
→ KRP
```

C’est cette séquence qui donne à KRP l’occasion d’appliquer la prochaine rotation.

## 13.3 Sortie KRP

```text
KRP
→ même nouveau Blueprint avec blueprint_id + depth + domain
→ Taxonomy
```

---

# 14. Cas limites

1. Aucun signal Taxonomy → Domain courant reste `VISIBLE` et est conservé au prochain Blueprint.
2. `DOMAIN_EXHAUSTED` valide → KRP estompe le Domain, sans choisir immédiatement le suivant.
3. Replay du signal déjà appliqué → `NO-OP`.
4. Signal pour un territoire non actif → aucune mutation de rotation.
5. Au prochain Blueprint, Domain courant ESTOMPÉ → KRP choisit le prochain Domain VISIBLE.
6. Huit Domaines ESTOMPÉS → au prochain Blueprint, KRP ferme le tour une seule fois.
7. Depth suivant déjà satisfait → KRP l’ignore via Matrix.
8. Depth 10 terminé avec besoin restant → retour cyclique vers le prochain Depth nécessaire, potentiellement 2.
9. Tous les Depths satisfaits → HOLD.
10. Échec de persistance KRP → retries techniques puis BLOCKED.
11. Nouveau Blueprint créé → ne signifie jamais automatiquement prochain Domain.
12. Taxonomy ne transmet jamais le prochain Domain ni le prochain Depth.

---

# 15. Validation contractuelle

KRP est conforme seulement si les tests prouvent :

1. Factory crée un nouveau Blueprint avant KRP ;
2. KRP consulte RotationState + DepthNeedMatrix, pas les Banks Taxonomy ;
3. sans signal Domain vide → même `depth + domain` au Blueprint suivant ;
4. Taxonomy peut envoyer `DOMAIN_EXHAUSTED(depth, domain)` factuel ;
5. réception du signal → `VISIBLE → ESTOMPÉ`, sans rotation immédiate ;
6. replay → `NO-OP` ;
7. prochain Blueprint après Domain ESTOMPÉ → KRP choisit lui-même le suivant ;
8. DomainCycle officiel respecté ;
9. Général absent ;
10. Taxonomy n’envoie pas `DEPTH_EXHAUSTED` dans le contrat actif ;
11. huit Domaines ESTOMPÉS → KRP ferme lui-même le tour au prochain cycle ;
12. `cycle_completed` incrémenté exactement une fois ;
13. prochain Depth choisi par besoin global Matrix ;
14. après Depth 10, retour vers 2 ou un autre Depth encore nécessaire ;
15. HOLD seulement lorsque toutes les cibles sont satisfaites ;
16. KRP écrit seulement `depth + domain` ;
17. sortie Blueprint intacte pour Taxonomy ;
18. `CURRENT_KERNEL_RECEIVED` reste un déclencheur lifecycle, pas une décision métier KRP ;
19. persistance Domain et fermeture de tour respectent la politique 1+3 retries.

---

# 16. Architecture = 100 % — partie intellectuelle

| Axe | Statut |
|---|---:|
| Mission | 100 % |
| Responsabilités | 100 % |
| Interdictions | 100 % |
| Entrées | 100 % |
| Sorties | 100 % |
| Slots Blueprint | 100 % |
| Données internes | 100 % |
| Mécanismes | 100 % |
| Communication | 100 % |
| Contrats | 100 % |
| États | 100 % |
| Transitions | 100 % |
| Cas limites | 100 % |
| Persistance | 100 % |
| Validation | 100 % |
| Tests contractuels | 100 % |
| Architecture intellectuelle | **100 %** |

---

# 17. Extension future Phases 1–2

KRP reste complet pour la responsabilité intellectuelle définie ici.

Toute nouvelle interface nécessaire à Phase1/Phase2 :

```text
spécification propriétaire de la Phase
↓
nouvelle version KRP
↓
nouvelle DEC
```

Aucune logique future n’est inventée dans v3.5.
