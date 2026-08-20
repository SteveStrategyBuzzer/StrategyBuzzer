# START HERE — StrategyBuzzer Kernel Engine

Ce dossier est la **mémoire architecturale persistante** du moteur intellectuel. Un changement de chat ne doit jamais modifier la source de vérité.

## Ordre obligatoire de lecture

1. `00_ConstitutionCognitive.md`
2. `00_ArchitectureRegister.md`
3. `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md`
4. `00_CURRENT_HANDOFF.md`
5. les spécifications verrouillées nécessaires dans `specifications/`
6. le dossier `working/` du **seul module actif**, uniquement s’il n’a pas déjà été promu

## Hiérarchie

- `specifications/` = contrats canoniques verrouillés seulement ;
- `working/` = reconstruction, références et brides non canoniques ;
- `cross-module/` = brides transversales qui ne constituent pas un moteur 01–11 ;
- `audits/` = preuves d’audit, ne remplacent jamais les contrats ;
- `certificates/` = preuves de verrouillage/validation, ne remplacent jamais les contrats ;
- `archive/` + documents marqués `SUPERSEDED/HISTORIQUE` = non autoritatifs.

## État de reprise actuel

```text
01 KernelBlueprint
→ contrat canonique disponible pour la frontière intellectuelle

02 KernelRotationPlanner
→ specifications/02_KernelRotationPlanner.md
→ v3.3 VERROUILLÉ — PARTIE INTELLECTUELLE
→ DEC-114
→ AUDIT-02-00 NEXT

03 Taxonomy
→ specifications/03_Taxonomy.md v1.0 VERROUILLÉ
```

## KRP — interdiction de sources historiques

Ne jamais reconstruire ou auditer KRP depuis :

```text
docs/architecture/02_KernelRotationPlanner.md
→ HISTORIQUE v3.2

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ PROMOTED / CLOSED
```

Source KRP unique :

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
```

## Interdictions générales

- ne jamais utiliser `archive/` comme vérité active ;
- ne jamais déduire l’architecture depuis le code ;
- ne jamais travailler deux spécifications en parallèle ;
- ne jamais modifier une spécification verrouillée sans révision complète/versionnement lorsque son architecture évolue ;
- ne jamais inventer un contrat manquant depuis un ancien chat ;
- ne jamais promouvoir un fichier `BRIDES`, `REFERENCE` ou `RECONSTRUCTION` dans `specifications/` sans verrouillage/certification explicite.

## Extension KRP Phases 1–2

KRP est complet pour la partie intellectuelle. Les éventuelles interfaces provenant plus tard de Phase1/Phase2 sont **réservées, non spécifiées et non bloquantes pour AUDIT-02-00**.

Toute extension future de KRP exige une nouvelle version complète + une nouvelle DEC.

## Branche officielle

```text
replit/intellectual-engine-current-2026-08-16
```

## Prochaine opération exacte

```text
AUDIT-02-00
```

Audit du code KRP réel contre la v3.3 canonique. Aucun patch avant la fermeture de l’audit.
