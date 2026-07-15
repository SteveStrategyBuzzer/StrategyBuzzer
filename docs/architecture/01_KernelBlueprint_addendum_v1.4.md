```text
KernelRotationPlanner
↓
crée un KernelBlueprint canonique unique
↓
écrit :
Depth
Domaine
↓
Taxonomy remplit :
Sous-domaine actif
Sujet actif
Idée dominante active
↓
QuestionIntent
↓
écrit le kernel_code
↓
Phase 1
↓
crée l’ensemble des contenus cognitifs du noyau
↓
Validation Phase 1
↓
vérifie l’ensemble de la production de Phase 1
↓
attribue individuellement à chaque slot :
├── verdict PASS → état du slot : OK
└── verdict FAIL → état du slot : FAIL
↓
à la fin de la validation complète de la phase :
├── slots OK → autorisés à poursuivre vers Phase 2
└── présence d’un ou plusieurs slots FAIL
        ↓
   une copie travaillable du Blueprint est envoyée à Quarantine
   avec les erreurs associées à chaque slot concerné
↓
Phase 2
↓
travaille uniquement les slots identifiés OK
et produit leurs traductions
↓
Validation Phase 2
↓
vérifie l’ensemble de la production de Phase 2
↓
attribue individuellement à chaque slot :
├── verdict PASS → état du slot : OK
│                  slot ouvert au gameplay
└── verdict FAIL → état du slot : FAIL
                   slot fermé au gameplay
↓
à la fin de la validation complète de la phase :
└── présence d’un ou plusieurs slots FAIL
        ↓
   une copie travaillable du Blueprint est envoyée à Quarantine
   avec les erreurs associées à chaque slot concerné
↓
ReadyBank reçoit le Blueprint canonique
avec l’état individuel de tous ses slots :
OK ou FAIL
↓
ReadyBank informe KernelRotationPlanner :
CURRENT_KERNEL_RECEIVED
↓
KernelRotationPlanner comptabilise le noyau reçu
↓
KernelRotationPlanner combine :
├── la réception du noyau confirmée par ReadyBank
├── le compte restant de noyaux par Depth + Domaine
└── l’état des réservoirs communiqué par Taxonomy
↓
KernelRotationPlanner sélectionne le prochain couple :
Depth + Domaine
↓
KernelRotationPlanner crée le Blueprint canonique suivant
```

## Règle architecturale

```text
Validation Phase 1
≠
une validation indépendante déclenchée slot par slot
```

La règle correcte est :

```text
Validation Phase 1
=
une passe complète de validation de la production de Phase 1
+
un verdict individuel pour chaque slot
```

Même principe pour la Validation Phase 2 :

```text
Validation Phase 2
=
une passe complète de validation de la production de Phase 2
+
un verdict individuel pour chaque slot traduit
```

Les validations sont donc des **moteurs de phase**, tandis que `OK` et `FAIL` sont des **états individuels attribués aux slots**.

## Effet sur la rotation

La présence de slots `FAIL` ne bloque pas la rotation.

```text
ReadyBank reçoit le Blueprint canonique
↓
CURRENT_KERNEL_RECEIVED
↓
KernelRotationPlanner peut préparer le noyau suivant
```

La correction des slots `FAIL` se poursuit séparément dans Quarantine, puis retraverse le flow avant de s’imbriquer dans le noyau canonique déjà conservé dans ReadyBank.

```
```
