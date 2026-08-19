# START HERE — StrategyBuzzer Kernel Engine

Ce dossier est la **mémoire architecturale persistante** du moteur intellectuel. Un changement de chat ne doit jamais modifier la source de vérité.

## Ordre obligatoire de lecture

1. `00_ConstitutionCognitive.md`
2. `00_ArchitectureRegister.md`
3. `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md`
4. `00_CURRENT_HANDOFF.md`
5. les spécifications verrouillées nécessaires dans `specifications/`
6. le dossier `working/` du **seul module actif**

## Hiérarchie

- `specifications/` = contrats canoniques verrouillés seulement.
- `working/` = reconstruction, références et brides non canoniques.
- `cross-module/` = brides transversales qui ne constituent pas un moteur 01–11.
- `audits/` = preuves d’audit ; ne remplacent pas les contrats.
- `certificates/` = preuves de verrouillage/validation ; ne remplacent pas les contrats.
- `archive/` = historique, documents supersédés, anciennes cartographies et reconstructions de chat.

## Interdictions

- ne jamais utiliser `archive/` comme vérité active ;
- ne jamais déduire l’architecture depuis le code ;
- ne jamais travailler deux spécifications en parallèle ;
- ne jamais modifier une spécification verrouillée sans révision complète/versionnement lorsque son architecture évolue ;
- ne jamais inventer un contrat manquant depuis un ancien chat ;
- ne jamais promouvoir un fichier `BRIDES` ou `RECONSTRUCTION` dans `specifications/` sans verrouillage/certification explicite.

## Branche de récupération documentaire

`replit/intellectual-engine-current-2026-08-16`

La présente réorganisation est documentaire seulement : **aucun code moteur n’est modifié**.
