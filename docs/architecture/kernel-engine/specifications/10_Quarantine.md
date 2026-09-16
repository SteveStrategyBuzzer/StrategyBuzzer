# STRATEGYBUZZER — 10_QUARANTINE

**Version :** 1.1
**Date :** 16 septembre 2026
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODÈLE PHYSIQUE À IMPLANTER  
**Décisions :** DEC-122 + DEC-125 + DEC-126 + DEC-127
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

Les valeurs intellectuelles canoniques de la copie conservent leur langue
d’origine anglaise, et chaque traduction conserve son code parmi
`fr, es, de, it, pt, ru, zh, ar, el`. Quarantaine ne traduit, ne francise et ne
change jamais l’identité d’une valeur. Ses libellés d’interface restent
français; la langue choisie par le joueur reste externe.

Les anciennes données intellectuelles françaises restent inventoriables dans
la copie mais ne sont pas converties par le contrat documentaire DEC-126.

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


# 11. Indice de reprise persistant des slots jaunes

## Indice de reprise persistant des slots jaunes

Le jaune n’est pas une simple couleur d’interface. Chaque slot modifié manuellement doit conserver un indice de reprise persistant lié à la version exacte de la copie.

Cet indice permet de connaître :

- la révision manuelle du slot;
- le numéro de reprise;
- la version de copie concernée;
- l’étape actuellement atteinte dans le retour;
- les créations ou validations qui restent obligatoires;
- la décision terminale attendue de ReadyBank.

Le sens officiel du jaune est :

> slot modifié manuellement, engagé dans une reprise déterminée et pas encore accepté terminalement par ReadyBank.

La copie complète reprend à Phase1. Chaque slot jaune traverse ensuite seulement les opérations requises par son état, parmi :

```text
Phase1
→ ValidationPhase1
→ Phase2
→ ValidationPhase2
→ ReadyBank
```

Une étape non requise ne recrée ni ne réécrit le contenu; elle constate la précondition persistée et laisse le slot continuer.

L’indice de reprise est mis à jour atomiquement lors de chaque progression. Il reste rattaché à la même révision manuelle et à la même version réclamée. Après interruption, la reprise repart de la dernière frontière persistée sans recommencer une opération déjà terminée.

ReadyBank retire le jaune uniquement après avoir décidé terminalement de cette révision exacte :

- fusion réussie : la correction devient canonique et l’indice est terminé;
- échec : la position canonique reste vide, la copie retourne en Quarantaine et le slot redevient rouge;
- retour périmé : aucune écriture canonique et aucun changement de l’indice courant.

Une ancienne reprise, une ancienne version de copie ou une ancienne révision manuelle ne peut jamais terminer, remplacer ou écraser une correction plus récente.

Pour une traduction cible, toute création ou modification manuelle réelle sous
la même `source_revision` augmente sa `translation_revision`, invalide
atomiquement son ancien résultat ValidationPhase2 et la remet à
`CREATED + NOT_VALIDATED`. Cette nouvelle révision jaune peut réparer un
`PERMANENT_FAILURE` du cycle automatisé.

Tout claim, résultat fournisseur ou résultat de validation portant une ancienne
`source_revision`, une ancienne `translation_revision` ou un ancien jeton est
refusé comme périmé. Phase2 ne peut jamais écraser la révision jaune courante
avec un résultat fournisseur antérieur.

Seuls les findings ValidationPhase2 des `source_revision` et
`translation_revision` courantes sont opérationnels et peuvent produire des
chemins rouges. Les anciens findings peuvent subsister pour audit technique et
idempotence, mais ne constituent pas un historique éditable : ils ne sont
jamais affichés comme courants, recopiés dans une nouvelle copie, reportés sur
une nouvelle révision ni utilisés pour colorer un slot.

## Copie linguistique complète et reprise sélective

La copie Quarantaine comprend toujours les sept CognitiveSlots. Pour chacun,
elle conserve la source anglaise et sa révision, les neuf traductions et leurs
révisions, les validations, les findings courants et les indices de reprise.

Cette complétude ne provoque aucune recréation générale. Chaque cible reprend
exactement à la frontière persistée :

| État courant | Contenu conservé | Reprise |
|---|---|---|
| jaune après modification manuelle complète | oui | ValidationPhase2 |
| rouge ou manquante avec création requise | non ou incomplet | Phase2 |
| périmée après modification de la source anglaise | ancien contenu non réutilisé | nouvelle traduction Phase2 |
| PASS, courante et non modifiée | oui | aucune création ni revalidation |

Une cible jaune complète reste `CREATED`, passe à `NOT_VALIDATED` et ne rejoue
pas sa création Phase2. Une étape déjà satisfaite ne recrée, ne réécrit et ne
revalide rien.

Modifier la source anglaise augmente `source_revision` et périme les neuf
traductions de ce CognitiveSlot. Modifier une seule cible augmente uniquement
sa `translation_revision`, sans périmer les huit autres ni les autres slots.

Une panne réseau, un quota, un timeout, une authentification invalide, une
configuration fournisseur ou l’épuisement d’un cycle technique ne colore aucun
contenu et ne crée pas seul une copie Quarantaine.

## Retrait d’un CognitiveSlot déjà publié

Si l’Admin modifie réellement la source anglaise ou une traduction d’un slot
déjà publié, une seule transaction verrouille `blueprint_id + cognitive_type`,
crée la nouvelle révision jaune, invalide le manifeste publié, rend
immédiatement le slot non exploitable dans toutes les langues et persiste son
indice de reprise.

Le contenu antérieur peut être conservé pour audit, mais Gameplay ne peut plus
l’utiliser. Modifier la source anglaise périme les neuf traductions. Modifier
une seule cible ne périme pas les huit autres, mais retire temporairement le
CognitiveSlot complet parce que la publication demeure atomique par slot.

Cette opération ne retire, ne révise et ne relance aucun autre CognitiveSlot
publié et non modifié du même Blueprint.

# 12. Annexe officielle de conformité

La projection vérifiable des frontières Quarantaine de DEC-127 est :

[`DEC-127_PHASE2_VALIDATION_COMPLIANCE_ANNEX.md`](../DEC-127_PHASE2_VALIDATION_COMPLIANCE_ANNEX.md)

La présente spécification reste propriétaire et autoritative. En cas d’écart,
l’annexe doit être corrigée.
