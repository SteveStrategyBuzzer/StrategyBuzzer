# STRATEGYBUZZER — MÉCANISME EXACT DU KERNELROTATIONPLANNER

## Correctif de `02_KernelRotationPlanner.md`

**Version :** 1.4
**Date :** 14 juillet 2026
**Statut :** UNDER_REVIEW
**Implantation autorisée :** NON

Ce texte décrit l’architecture métier attendue.

Il ne constitue pas encore une autorisation de modifier le code.

---

# 1. Principe central

Le `KernelRotationPlanner` décide seul du prochain couple :

```text
Depth
+
Domaine réel de création
```

Pour prendre cette décision, il combine trois informations indépendantes :

```text
ReadyBank
→ confirme que le Blueprint courant a été reçu
```

```text
Taxonomy
→ indique l’état réel des réservoirs du Depth courant
```

```text
DepthNeedMatrix
→ indique combien de noyaux restent à produire
   pour chaque couple Depth + Domaine
```

La règle centrale est donc :

```text
CURRENT_KERNEL_RECEIVED
+
état actuel des réservoirs Taxonomy
+
compte restant par Depth + Domaine
↓
KernelRotationPlanner calcule la prochaine position
```

ReadyBank ne choisit jamais le prochain domaine.

Taxonomy ne choisit jamais le prochain domaine.

La DepthNeedMatrix ne choisit jamais le prochain domaine.

Le choix final appartient exclusivement au KernelRotationPlanner.

---

# 2. Initialisation du compteur par Depth + Domaine

## 2.1 Source du besoin

Le nombre de noyaux à produire ne doit jamais être inventé par le KernelRotationPlanner.

Il provient de la `DepthNeedMatrix`.

Pour chaque couple :

```text
Depth + Domaine
```

la DepthNeedMatrix fournit :

```text
kernel_target
```

Exemple conceptuel :

```text
Depth 4 / Géographie → 40 noyaux demandés
Depth 4 / Histoire   → 40 noyaux demandés
Depth 4 / Faune      → 40 noyaux demandés
```

Les nombres présentés ici sont seulement des exemples.

Les valeurs officielles devront provenir de la configuration officielle de production.

## 2.2 État de production

Pour chaque couple `Depth + Domaine`, le Planner conserve :

```text
kernel_target
kernel_received
kernel_remaining
```

La formule officielle est :

```text
kernel_remaining
=
kernel_target
-
kernel_received
```

## 2.3 Première initialisation

Lors de la première activation d’un Depth :

1. le Planner charge les cibles de la DepthNeedMatrix ;
2. il vérifie combien de noyaux de chaque couple sont déjà présents dans ReadyBank ;
3. il initialise `kernel_received` à partir de cette réalité ;
4. il calcule `kernel_remaining` ;
5. il reçoit de Taxonomy l’état réel des réservoirs du Depth.

Il est interdit d’initialiser automatiquement :

```text
kernel_received = 0
```

si ReadyBank contient déjà des noyaux correspondant au couple.

La reprise doit toujours utiliser la vérité persistée.

## 2.4 Exemple

```text
Depth : 4
Domaine : Histoire

kernel_target    = 50
noyaux déjà reçus dans ReadyBank = 18
```

Le Planner initialise :

```text
kernel_received  = 18
kernel_remaining = 32
```

---

# 3. Mise à jour à chaque `CURRENT_KERNEL_RECEIVED`

ReadyBank transmet :

```text
CURRENT_KERNEL_RECEIVED
```

dès qu’il reçoit le Blueprint canonique courant.

Cette réception est valide même lorsque le Blueprint contient :

* des slots `OK` ;
* des slots `FAIL` ;
* des slots représentés par une copie en Quarantine ;
* des slots encore fermés au gameplay.

La jouabilité complète du Blueprint n’est pas requise.

## 3.1 Vérifications avant comptabilisation

Le KernelRotationPlanner vérifie :

* que la référence reçue correspond au Blueprint actif attendu ;
* que le `kernel_code` correspond au Blueprint reçu ;
* que le `depth` correspond au Depth actif ;
* que le `domain` correspond au domaine actif ;
* que ce Blueprint n’a pas déjà été comptabilisé.

## 3.2 Mise à jour du compte

Après validation de la réception :

```text
kernel_received = kernel_received + 1
```

```text
kernel_remaining = kernel_remaining - 1
```

Le compteur ne doit jamais devenir négatif.

Si :

```text
kernel_remaining = 0
```

le besoin de production du domaine est rempli pour ce Depth.

## 3.3 Comptabilisation unique

Un même Blueprint ne peut être comptabilisé qu’une seule fois.

Le Planner conserve l’identité des Blueprints déjà reçus.

Une confirmation ReadyBank répétée doit être ignorée.

```text
même blueprint_reference
ou
même kernel_code
↓
aucune seconde décrémentation
```

## 3.4 Ce que signifie le compteur

Le compteur représente :

> le nombre de Blueprints canoniques reçus par ReadyBank.

Il ne représente pas :

* le nombre de slots jouables ;
* le nombre de slots `OK` ;
* le nombre de corrections terminées ;
* le nombre de copies Quarantine ;
* le nombre de questions consommées par les joueurs.

---

# 4. Information des réservoirs transmise par Taxonomy

Taxonomy reste la seule autorité sur ses réservoirs.

Pour chaque domaine du Depth courant, Taxonomy transmet :

```text
AVAILABLE
```

ou :

```text
EMPTY
```

## 4.1 `AVAILABLE`

Le réservoir peut encore produire une nouvelle unité intellectuelle pour un Blueprint.

## 4.2 `EMPTY`

Le réservoir ne peut plus produire de nouveau noyau pour ce domaine et ce Depth.

Le KernelRotationPlanner ne doit jamais essayer de reconstruire cette information à partir :

* du nombre de sujets ;
* du nombre d’idées ;
* d’un compteur local ;
* du code existant ;
* d’une supposition.

---

# 5. Retrait d’un domaine vide du tour courant

Lorsque Taxonomy transmet :

```text
reservoir_status = EMPTY
```

le Planner retire immédiatement ce domaine de la rotation active du Depth.

Le domaine reçoit l’état :

```text
RESERVOIR_EMPTY
```

Il ne peut plus être sélectionné pour créer un nouveau Blueprint dans ce Depth.

## 5.1 Réservoir vide avec compteur à zéro

```text
kernel_remaining = 0
reservoir_status = EMPTY
```

Le domaine a terminé normalement sa participation au Depth.

État :

```text
COMPLETE
```

## 5.2 Réservoir vide avec compteur supérieur à zéro

```text
kernel_remaining > 0
reservoir_status = EMPTY
```

Le domaine ne peut plus fournir les noyaux encore demandés.

État :

```text
EMPTY_BEFORE_TARGET
```

Le Planner :

* retire le domaine de la rotation ;
* conserve le nombre de noyaux manquants ;
* inscrit l’écart ;
* ne crée pas de contenu artificiel ;
* poursuit la rotation avec les autres domaines.

Le domaine est fermé pour le Depth, mais la cible n’est pas considérée comme atteinte.

---

# 6. Différence entre domaine actif et domaine terminé

Ces états ne doivent jamais être confondus.

## 6.1 `AVAILABLE`

Le domaine peut être sélectionné.

Conditions :

```text
kernel_remaining > 0
AND
reservoir_status = AVAILABLE
AND
aucun Blueprint actif pour ce domaine
```

## 6.2 `ACTIVE`

Le domaine est temporairement actif lorsqu’un Blueprint est actuellement en construction pour ce couple.

```text
active_depth  = Depth du Blueprint
active_domain = Domaine du Blueprint
active_blueprint_reference existe
```

`ACTIVE` ne signifie pas que le domaine est terminé.

Cela signifie uniquement :

> un noyau de ce domaine est présentement dans le pipeline.

## 6.3 Retour à `AVAILABLE`

Après `CURRENT_KERNEL_RECEIVED`, le domaine redevient `AVAILABLE` si :

```text
kernel_remaining > 0
AND
reservoir_status = AVAILABLE
```

Le DomainCycle ne le sélectionne pas nécessairement immédiatement.

Il poursuit d’abord vers les domaines suivants.

Le domaine sera repris lors d’un prochain tour circulaire.

## 6.4 `TARGET_COMPLETE`

Le domaine a produit tous les noyaux demandés :

```text
kernel_remaining = 0
```

Il est retiré de la rotation du Depth, même si Taxonomy possède encore du contenu.

## 6.5 `RESERVOIR_EMPTY`

Taxonomy a déclaré le réservoir vide.

Le domaine est retiré de la rotation, même si le compteur n’est pas encore à zéro.

## 6.6 État fermé pour le Depth

Un domaine est considéré fermé pour le Depth lorsqu’il est dans l’un des états suivants :

```text
TARGET_COMPLETE
RESERVOIR_EMPTY
EMPTY_BEFORE_TARGET
```

Un domaine `ACTIVE` ou `AVAILABLE` n’est jamais fermé.

---

# 7. Sélection du prochain domaine

Après la réception du Blueprint courant, le Planner avance d’une position dans le DomainCycle.

Il examine ensuite les domaines dans leur ordre officiel.

Pour chaque domaine, il vérifie :

```text
kernel_remaining > 0
```

et :

```text
reservoir_status = AVAILABLE
```

Si les deux conditions sont vraies :

```text
DOMAIN_SELECTABLE = TRUE
```

Le domaine est sélectionné.

Sinon, le Planner passe au domaine suivant.

## 7.1 Rotation circulaire

Exemple :

```text
Géographie
↓
Histoire
↓
Faune
↓
Art
↓
Sport
↓
Cinéma
↓
Cuisine
↓
Science
↓
retour à Géographie
```

Les domaines fermés sont ignorés.

## 7.2 Exemple

```text
Depth 4

Géographie
remaining = 0
status = TARGET_COMPLETE

Histoire
remaining = 5
reservoir = AVAILABLE

Faune
remaining = 3
reservoir = EMPTY

Art
remaining = 4
reservoir = AVAILABLE
```

Le Planner :

* ignore Géographie ;
* peut sélectionner Histoire ;
* ignore Faune ;
* peut sélectionner Art lors du prochain passage.

---

# 8. Comment le Planner sait que le Depth est terminé

Après avoir parcouru tout le DomainCycle sans trouver de domaine sélectionnable, le Planner évalue chaque domaine.

## 8.1 Condition générale

Le Depth est fermé lorsqu’aucun domaine n’est encore :

```text
AVAILABLE
```

ou :

```text
ACTIVE
```

Autrement dit, tous les domaines sont dans un état fermé :

```text
TARGET_COMPLETE
RESERVOIR_EMPTY
EMPTY_BEFORE_TARGET
```

## 8.2 Depth terminé normalement

Tous les domaines ont atteint leur cible :

```text
tous les kernel_remaining = 0
```

État :

```text
DEPTH_TARGET_COMPLETE
```

## 8.3 Depth terminé par épuisement des réservoirs

Tous les domaines sont retirés de la rotation, mais certains compteurs ne sont pas à zéro.

État :

```text
DEPTH_COMPLETE_WITH_SHORTFALL
```

Le Planner conserve :

* les domaines incomplets ;
* les noyaux manquants par domaine ;
* le total des noyaux manquants ;
* la raison de fermeture.

Le Planner ne doit jamais transformer ce résultat en réussite normale.

---

# 9. Bascule précise vers le prochain Depth

Lorsque le Depth courant est fermé :

1. enregistrer son état terminal ;
2. enregistrer les écarts éventuels ;
3. avancer `depth_position` d’une position ;
4. charger le prochain Depth du `DepthCycle` ;
5. remettre `domain_position` au début du DomainCycle ;
6. charger les cibles de la DepthNeedMatrix pour ce nouveau Depth ;
7. restaurer les nombres déjà reçus dans ReadyBank ;
8. calculer les comptes restants ;
9. demander à Taxonomy l’état des réservoirs de ce nouveau Depth ;
10. rechercher le premier domaine sélectionnable ;
11. créer le nouveau KernelBlueprint.

Exemple :

```text
Depth 4 fermé
↓
depth_position : 0 → 1
↓
active_depth : 4 → 6
↓
domain_position remis au début
↓
chargement des cibles du Depth 6
↓
chargement des réservoirs Taxonomy du Depth 6
↓
sélection du premier domaine disponible
```

## 9.1 Aucun domaine disponible dans le nouveau Depth

Si tous les domaines du nouveau Depth sont déjà fermés :

* le Planner enregistre l’état de ce Depth ;
* il passe au Depth suivant ;
* il ne crée aucun Blueprint inutile.

## 9.2 Dernier Depth

Après la fermeture du Depth 9 :

```text
ROTATION_COMPLETE
```

Aucun retour automatique au Depth 4 n’est autorisé.

---

# 10. Création du Blueprint suivant

Après avoir sélectionné le prochain couple :

```text
active_depth
+
active_domain
```

le Planner :

1. crée un nouveau KernelBlueprint canonique ;
2. écrit `depth` ;
3. écrit `domain` ;
4. conserve la référence du Blueprint actif ;
5. place le couple dans l’état `ACTIVE` ;
6. rend le Blueprint disponible à Taxonomy.

```text
KernelRotationPlanner
↓
crée KernelBlueprint
↓
écrit depth
↓
écrit domain
↓
Taxonomy reçoit le contexte actif
↓
Taxonomy écrit :
subdomain_active
subject_active
dominant_idea_active
```

Chaque noyau possède son propre Blueprint.

---

# 11. Déclenchement exact de la rotation suivante

Après le premier Blueprint, le Planner ne crée pas spontanément le suivant.

Il attend les deux informations indépendantes :

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
```

et :

```text
Taxonomy
→ état actualisé des réservoirs
```

Ces informations peuvent arriver dans n’importe quel ordre.

Le Planner les conserve jusqu’à ce que les deux soient disponibles pour le tour courant.

## 11.1 ReadyBank arrive en premier

```text
CURRENT_KERNEL_RECEIVED reçu
↓
compteur mis à jour
↓
attente de l’état Taxonomy
```

## 11.2 Taxonomy arrive en premier

```text
état des réservoirs reçu
↓
état conservé
↓
attente de CURRENT_KERNEL_RECEIVED
```

## 11.3 Les deux informations sont disponibles

```text
CURRENT_KERNEL_RECEIVED
+
état Taxonomy actuel
↓
CALCULATE_NEXT_POSITION
```

Le Planner peut alors :

* fermer le tour courant ;
* avancer le DomainCycle ;
* déterminer si le Depth reste actif ;
* sélectionner le prochain couple ;
* créer le Blueprint suivant.

---

# 12. Persistance de l’état de rotation

Le KernelRotationPlanner doit posséder un état persistant conceptuellement nommé :

```text
RotationState
```

Il contient au minimum :

```text
rotation_version

depth_position
active_depth

domain_position
active_domain

kernel_target_by_depth_and_domain
kernel_received_by_depth_and_domain
kernel_remaining_by_depth_and_domain

reservoir_status_by_depth_and_domain
domain_state_by_depth_and_domain

active_blueprint_reference
active_kernel_code

readybank_received_for_current_turn
taxonomy_state_received_for_current_turn

last_counted_blueprint_reference
last_counted_kernel_code

rotation_status
last_completed_depth
```

---

# 13. Moments obligatoires de persistance

L’état doit être persisté après :

* le chargement d’un nouveau Depth ;
* la réception d’un état Taxonomy ;
* la création d’un Blueprint ;
* l’écriture de `depth` et `domain` ;
* la réception de `CURRENT_KERNEL_RECEIVED` ;
* la mise à jour du compteur ;
* le changement d’état d’un domaine ;
* l’avancement du DomainCycle ;
* la fermeture d’un Depth ;
* le passage au Depth suivant ;
* la fin complète de la rotation.

---

# 14. Transitions atomiques

Certaines opérations doivent être indivisibles.

## 14.1 Comptabilisation ReadyBank

```text
vérifier que le Blueprint n’a pas déjà été compté
+
kernel_received + 1
+
kernel_remaining - 1
+
enregistrer la référence comptabilisée
```

Une interruption ne doit jamais produire une double comptabilisation.

## 14.2 Création du Blueprint

```text
sélectionner Depth + Domaine
+
créer le Blueprint
+
écrire depth
+
écrire domain
+
enregistrer active_blueprint_reference
+
placer le domaine ACTIVE
```

Une interruption ne doit jamais produire :

* un Blueprint sans référence active ;
* une référence active sans Blueprint ;
* deux Blueprints pour le même tour.

## 14.3 Fermeture du Depth

```text
enregistrer l’état terminal du Depth
+
enregistrer les écarts
+
avancer depth_position
+
réinitialiser domain_position
+
charger le nouveau Depth
```

---

# 15. Reprise après interruption

Au redémarrage, le Planner recharge `RotationState`.

## 15.1 Blueprint actif existant

La présence d’un `active_blueprint_reference` ne déclenche pas une nouvelle rotation.

Elle sert uniquement à empêcher une duplication après interruption.

Le véritable déclencheur normal reste :

```text
CURRENT_KERNEL_RECEIVED
```

Si ReadyBank possède déjà le Blueprint actif mais que le signal n’a pas été enregistré, le Planner réconcilie l’état avec ReadyBank et comptabilise le Blueprint une seule fois.

## 15.2 Réception déjà comptabilisée

Si le Blueprint est déjà dans la liste des noyaux comptabilisés :

* ne pas redécrémenter ;
* reprendre au calcul de la prochaine position.

## 15.3 Création interrompue

Si l’état indique qu’un Blueprint devait être créé :

* vérifier s’il existe déjà ;
* le réutiliser s’il existe ;
* le créer seulement s’il n’existe pas.

---

# 16. États internes du Planner

```text
INITIALIZING_DEPTH
↓
WAITING_TAXONOMY_STATE
↓
SELECTING_DOMAIN
↓
CREATING_BLUEPRINT
↓
WAITING_CURRENT_KERNEL_RECEIVED
↓
WAITING_CURRENT_TAXONOMY_STATE
↓
CALCULATING_NEXT_POSITION
↓
SELECTING_DOMAIN
```

Transition de fin de Depth :

```text
CALCULATING_NEXT_POSITION
↓
NO_SELECTABLE_DOMAIN
↓
CLOSING_DEPTH
↓
INITIALIZING_NEXT_DEPTH
```

Fin générale :

```text
CLOSING_DEPTH_9
↓
ROTATION_COMPLETE
```

---

# 17. Pseudo-mécanisme complet

```text
1. Charger RotationState.

2. Si aucun Depth n’est actif :
      charger le premier Depth autorisé.

3. Charger les cibles DepthNeedMatrix.

4. Restaurer les noyaux déjà reçus dans ReadyBank.

5. Calculer kernel_remaining pour chaque domaine.

6. Recevoir de Taxonomy l’état des réservoirs.

7. Identifier les domaines sélectionnables :
      remaining > 0
      ET
      reservoir = AVAILABLE.

8. Sélectionner le prochain domaine selon le DomainCycle.

9. Créer un KernelBlueprint.

10. Écrire depth et domain.

11. Enregistrer active_blueprint_reference.

12. Attendre CURRENT_KERNEL_RECEIVED.

13. Lorsque ReadyBank confirme :
      vérifier l’identité ;
      empêcher le double comptage ;
      kernel_received + 1 ;
      kernel_remaining - 1.

14. Recevoir ou restaurer l’état actualisé de Taxonomy.

15. Mettre à jour l’état de chaque domaine.

16. Avancer domain_position.

17. Chercher le prochain domaine sélectionnable.

18. Si un domaine est trouvé :
      conserver le même Depth ;
      créer le Blueprint suivant.

19. Si aucun domaine n’est trouvé :
      fermer le Depth ;
      enregistrer TARGET_COMPLETE
      ou COMPLETE_WITH_SHORTFALL ;
      passer au prochain Depth.

20. Après le Depth 9 :
      ROTATION_COMPLETE.
```

---

# 18. Règles absolues

## KRP-R01

La DepthNeedMatrix initialise le compte demandé.

## KRP-R02

ReadyBank confirme les Blueprints réellement reçus.

## KRP-R03

Taxonomy confirme l’état réel des réservoirs.

## KRP-R04

KernelRotationPlanner combine ces informations et décide seul de la rotation.

## KRP-R05

Un Blueprint reçu est comptabilisé même s’il contient des slots `FAIL`.

## KRP-R06

Quarantine ne bloque jamais la rotation.

## KRP-R07

Un domaine `ACTIVE` possède seulement un Blueprint actuellement dans le pipeline.

## KRP-R08

Un domaine `TARGET_COMPLETE` a un compteur restant égal à zéro.

## KRP-R09

Un domaine `RESERVOIR_EMPTY` a été déclaré vide par Taxonomy.

## KRP-R10

Le Depth change lorsqu’aucun domaine ne reste sélectionnable.

## KRP-R11

La réception ReadyBank et l’état Taxonomy sont deux signaux indépendants.

## KRP-R12

Le Planner attend les deux informations avant de calculer la prochaine position.

## KRP-R13

L’état de rotation est persistant.

## KRP-R14

Toute opération de comptabilisation doit être idempotente.

## KRP-R15

`Général` ne peut jamais être présent dans le DomainCycle.

---

# Architecture Register

## DEC-051 — Initialisation par DepthNeedMatrix

**Statut :** UNDER_REVIEW

Le compte de noyaux demandé pour chaque couple `Depth + Domaine` provient exclusivement de la DepthNeedMatrix.

---

## DEC-052 — Réception ReadyBank indépendante de la jouabilité

**Statut :** OFFICIAL

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si certains slots sont `FAIL` ou en correction.

---

## DEC-053 — Deux signaux indépendants

**Statut :** OFFICIAL

ReadyBank et Taxonomy transmettent séparément leurs informations au KernelRotationPlanner.

Le calcul suivant exige :

```text
CURRENT_KERNEL_RECEIVED
+
état actuel des réservoirs
```

---

## DEC-054 — États distincts des domaines

**Statut :** UNDER_REVIEW

Le Planner distingue :

```text
AVAILABLE
ACTIVE
TARGET_COMPLETE
RESERVOIR_EMPTY
EMPTY_BEFORE_TARGET
```

---

## DEC-055 — Complétion sans domaine sélectionnable

**Statut :** UNDER_REVIEW

Le Depth est fermé lorsqu’aucun de ses domaines ne reste sélectionnable.

La raison de fermeture est conservée :

```text
DEPTH_TARGET_COMPLETE
```

ou :

```text
DEPTH_COMPLETE_WITH_SHORTFALL
```

---

## DEC-056 — Persistance obligatoire de RotationState

**Statut :** UNDER_REVIEW

L’état complet de la rotation est persisté afin d’empêcher :

* les doubles comptabilisations ;
* les doubles Blueprints ;
* les sauts de domaine ;
* les pertes de position ;
* les reprises incohérentes.
