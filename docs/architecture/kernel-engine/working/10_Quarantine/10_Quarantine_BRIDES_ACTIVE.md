# 10_Quarantine — Brides actives avant spécification

**Statut :** À SPÉCIFIER  
**Nature du fichier :** brides inter-modules déjà validées ; pas une spécification complète.

**Position Blueprint :** **station de correction** ; Quarantine n’est pas une quatrième section du Blueprint. Elle reçoit les contenus en échec des sections autorisées, travaille sur une copie de correction, puis retourne le contenu corrigé de façon contrôlée sans devenir propriétaire initial du slot.

## Brides connues — relation Blueprint

Quarantine :

```text
reçoit une copie / représentation de travail du contenu en échec
↓
corrige le contenu ciblé
↓
retourne le contenu corrigé vers le Blueprint canonique
↓
remplissage ou remplacement contrôlé du contenu corrigé
↓
reprise du pipeline au point autorisé
```

## Ownership

- Quarantine n’est jamais propriétaire initial du slot.
- Quarantine possède une autorité de correction ciblée sur le contenu explicitement mis en échec.
- Elle ne peut pas modifier arbitrairement le Blueprint.

## À spécifier

- identité exacte du slot corrigé ;
- versionnement ;
- point de reprise ;
- règles de remplacement ;
- persistance ;
- relation avec les validations Phase1 / Phase2.

## Bride — interface d'administration Quarantine

L'interface d'administration StrategyBuzzer devra exposer les copies de travail Quarantine afin qu'un administrateur autorisé puisse comprendre et traiter les échecs.

Chaque entrée Quarantine affichable doit au minimum permettre d'identifier :
- le Blueprint canonique source ;
- le module/phase d'origine de l'échec ;
- le ou les slots/contenus ciblés ;
- le code défaut / motif ;
- le contenu de travail en Quarantine ;
- le point de reprise attendu ;
- l'état de correction et de retour.

L'administrateur peut corriger une copie Quarantine et demander son renvoi contrôlé. Ce renvoi passe toujours par le contrat Quarantine → Blueprint ; l'interface admin n'écrit jamais directement et arbitrairement dans le Blueprint canonique.