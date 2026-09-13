# STRATEGYBUZZER — 10_QUARANTINE

**Version :** 1.0  
**Date :** 13 septembre 2026  
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODÈLE PHYSIQUE À IMPLANTER  
**Décisions :** DEC-122 + DEC-125  
**Implémentation :** NON TERMINÉE  
**Validation terminale :** NON

---

# 1. Mission

Quarantaine conserve une copie complète, persistante et non canonique d’un KernelBlueprint nécessitant une correction. Elle ne crée jamais de Blueprint, de `blueprint_id`, de `kernel_code` ni d’identité intellectuelle.

L’interface Admin n’est pas une phase. Elle affiche et permet de modifier les copies persistées par Quarantaine.

# 2. Identité et contenu de la copie

Une copie conserve obligatoirement :

- le même `blueprint_id` que le canonique;
- le même `kernel_code`;
- Depth, Domaine, Sous-domaine, Sujet et Idée dominante;
- les sept `CognitiveSlots`;
- pour chaque slot : question, choix, bonne réponse, SV, traductions, états, findings et erreurs disponibles;
- l’étape d’origine;
- la version courante nécessaire contre les retours périmés.

La copie complète n’est jamais insérée dans les tables canoniques et n’est jamais envoyée à KBP ou Rotation.

# 3. Création de la copie et protection du canonique

Lorsqu’un slot est `SUSPICION` ou `EMPTY`, la copie complète est persistée avant toute mutation du canonique.

Ensuite, chaque position défaillante du Blueprint canonique devient `EMPTY`. Sa source, ses traductions, son ancien PASS et ses findings ne restent pas exploitables. Le contenu rejeté demeure disponible dans la copie Quarantaine.

Les slots canoniques conformes restent inchangés et peuvent continuer dans le flow régulier. Une position vide ne doit être traitée ni par Phase2, ni par ValidationPhase2, ni par Gameplay.

# 4. Couleurs fonctionnelles

La couleur est dérivée de l’état courant :

- **rouge** : `SUSPICION` ou `EMPTY`;
- **vert** : slot conforme et jamais modifié manuellement dans la copie courante;
- **jaune** : slot modifié manuellement.

Tous les slots sont modifiables dans l’interface Admin, y compris un slot vert. Dès qu’un slot est modifié, son ancien PASS est invalidé, ses validations requises redeviennent à faire et il devient jaune.

Un slot jaune reste jaune pendant tout son retour dans le pipeline jusqu’à la décision de ReadyBank. S’il échoue, il redevient rouge dans la copie retournée, sans effacer le fait qu’une correction manuelle a eu lieu.

# 5. Interface Admin

L’Admin doit permettre :

- de voir le nombre de copies;
- de trier par date d’arrivée, Depth, Domaine et état des slots;
- d’ouvrir une copie complète;
- de consulter toute sa structure intellectuelle;
- de modifier un ou plusieurs slots;
- de cliquer `Renvoie`;
- de supprimer une copie lorsqu’elle n’est pas en cours de traitement.

La suppression retire uniquement la copie de travail. Elle ne remplit pas les positions vides du canonique et ne crée aucun historique de versions.

# 6. Clic Renvoie et FIFO

Le clic `Renvoie` place la version courante de la copie dans une file persistante.

Plusieurs copies peuvent attendre simultanément. Elles sont prises en charge une à la fois, strictement dans l’ordre des clics `Renvoie` acceptés.

Un clic idempotent ne doit pas créer deux demandes. Une modification ultérieure produit une version supérieure. Une version réclamée ou terminée ne peut pas être remplacée silencieusement par une autre.

# 7. Direction de CURRENT_KERNEL_RECEIVED

Chaque `CURRENT_KERNEL_RECEIVED` produit une seule décision durable et idempotente :

```text
file Quarantaine READY non vide
→ GO vers la plus ancienne copie
→ KBP ne reçoit rien

file Quarantaine READY vide
→ GO vers KBP
```

Une copie déjà en cours empêche un événement concurrent de contourner la priorité Quarantaine pour démarrer KBP.

# 8. Reprise du flow

La copie complète corrigée reprend toujours à Phase1 avec son `blueprint_id`. Elle ne passe ni par KBP, ni par Rotation, ni par Taxonomy, ni par QuestionIntent.

À partir de Phase1 :

- chaque slot repasse uniquement les contrôles ou créations nécessaires à son état;
- les slots jaunes repassent les validations propriétaires;
- un slot vide peut être créé si nécessaire;
- Phase2 ignore tout slot source non admissible;
- les slots conformes continuent même si d’autres échouent;
- la copie corrigée peut revenir en Quarantaine autant de fois que nécessaire.

Lors du départ effectif, la copie disparaît de la liste éditable de l’Admin et devient en cours de traitement. Elle réapparaît si le pipeline la retourne.

# 9. ReadyBank

La copie rejoint le canonique uniquement dans ReadyBank.

ReadyBank :

- vérifie l’identité et la version réclamée;
- refuse tout retour périmé;
- fusionne atomiquement par `blueprint_id + cognitive_type`;
- remplace uniquement les slots admissibles;
- maintient vides les positions encore non conformes;
- conserve tous les slots canoniques hors fusion;
- termine la demande exactement une fois.

La copie ne devient jamais canonique. Aucune ancienne version de correction n’est conservée après la décision terminale.

# 10. Interdictions

Quarantaine ne doit jamais :

- créer ou recycler un Blueprint;
- déclencher ou modifier Rotation;
- compter une copie comme nouveau noyau;
- modifier `blueprint_id`, `kernel_code` ou l’identité;
- fusionner elle-même dans le canonique;
- rendre directement un contenu au Gameplay;
- contourner Phase1, ValidationPhase1, Phase2, ValidationPhase2 ou ReadyBank;
- conserver plusieurs versions historiques de la même copie;
- autoriser une réponse périmée à écraser une version plus récente.
