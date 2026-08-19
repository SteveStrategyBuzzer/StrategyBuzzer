# 11_ReadyBank — Brides actives avant spécification

**Statut :** À SPÉCIFIER  
**Nature du fichier :** brides inter-modules déjà validées ; pas une spécification complète.

## Responsabilité connue

ReadyBank **stocke** le noyau canonique prêt.

ReadyBank ne lit pas le Blueprint pour prendre une décision intellectuelle et ne modifie pas son contenu.

## Signal terminal connu

Après réception / stockage réussi du noyau courant :

```text
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
Blueprint / mécanisme de création du prochain Blueprint
↓
création d’un nouveau KernelBlueprint
↓
ce nouveau Blueprint est ensuite remis à KRP
```

### Frontière obligatoire

```text
CURRENT_KERNEL_RECEIVED
≠ ReadyBank → KRP

CURRENT_KERNEL_RECEIVED
= ReadyBank → déclenchement du prochain Blueprint
```

KRP ne reçoit donc pas directement ce signal de ReadyBank. KRP intervient **après** la création du nouveau Blueprint.

## Interdictions connues

- ne choisit pas `depth` ni `domain` ;
- ne confirme pas la consommation Taxonomy ;
- ne choisit aucune IdeaSlot ;
- n’écrit aucun contenu intellectuel dans le Blueprint.