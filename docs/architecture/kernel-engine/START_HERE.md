# START HERE — StrategyBuzzer Kernel Engine

Ce dossier est la mémoire architecturale persistante du moteur intellectuel.
Un changement de chat ou l’état courant du code ne remplace jamais les contrats
officiels.

## Ordre obligatoire de lecture

1. `00_ConstitutionCognitive.md`
2. `00_ArchitectureRegister.md`
3. `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md`
4. `00_DOCUMENTATION_MAP.md`
5. `00_CURRENT_HANDOFF.md`
6. les spécifications canoniques nécessaires dans `specifications/`
7. un boundary bridge uniquement s’il est explicitement déclaré actif par les
   sources précédentes

## Hiérarchie

- `specifications/` : contrats canoniques verrouillés;
- `working/` : reconstruction ou bridges, autoritatifs seulement si le registre
  courant les active explicitement;
- `cross-module/` : brides transversales, jamais un moteur 01–11 autonome;
- `audits/` et `certificates/` : preuves, jamais contrats de remplacement;
- historique Git et documents `SUPERSEDED`, `REJECTED` ou `HISTORIQUE` :
  non autoritatifs.

## État courant

```text
01 KernelBlueprint       v3.2  — DEC-125
02 KernelRotationPlanner v4.0  — DEC-119 + langue DEC-126
03 Taxonomy              v1.1  — DEC-120 + langue DEC-126
05 QuestionIntent        v2.3  — DEC-125/126
06 Phase1                v1.2  — contrat documentaire DEC-125/126
07 ValidationPhase1      v1.2  — contrat documentaire DEC-125/126
08 Phase2                v1.0  — contrat terminal DEC-127
09 ValidationPhase2      v1.0  — contrat terminal DEC-127
10 Quarantine            v1.1  — frontières DEC-125/127
11 ReadyBank             v0.4  — frontière Phase2 DEC-127
```

La validation documentaire Phase2/ValidationPhase2 est PASS. L’implantation
DEC-127 n’est pas incluse dans ce verrouillage et appartient à une tâche
technique distincte.

## Pipeline canonique

```text
KBP
→ KRP
→ Taxonomy
→ QuestionIntent
→ Phase1
→ ValidationPhase1
→ Phase2
→ ValidationPhase2
→ ReadyBank
```

Un seul module métier est actif à la fois. Les phases reçoivent uniquement le
`blueprint_id` et rechargent le même KernelBlueprint persistant.

## Règles courantes décisives

- KRP v4.0 possède les moteurs `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED`;
- Taxonomy transmet uniquement son fait terminal de consommation et ne possède
  aucun moteur global d’épuisement;
- l’anglais est la langue intellectuelle canonique;
- Phase2 crée exactement `fr, es, de, it, pt, ru, zh, ar, el`, directement
  depuis l’anglais;
- `General` est uniquement un mode Shuffle gameplay;
- les sept slots Quarantaine sont modifiables, y compris les verts;
- ReadyBank publie atomiquement un CognitiveSlot uniquement lorsque la source
  et les neuf traductions courantes sont admissibles;
- `CURRENT_KERNEL_RECEIVED` suit sept issues terminales, pas nécessairement sept
  publications;
- une file Quarantaine READY est toujours prioritaire sur KBP;
- aucune conversion legacy n’est autorisée par DEC-126/127.

## Sources historiques interdites comme vérité active

Ne jamais reconstruire l’architecture depuis :

- `docs/architecture/02_KernelRotationPlanner.md`;
- les variantes KRP v3.x;
- DEC-115 à DEC-118;
- un fichier `REFERENCE`, `RECONSTRUCTION`, `BRIDES`, `SUPERSEDED`, `REJECTED`
  ou `HISTORIQUE`;
- un ancien chat;
- le comportement du code existant lorsqu’il diverge de la spécification.

Toute divergence entre code et contrat est un écart d’implantation à nommer et
à traiter séparément; elle ne réécrit jamais la spécification.