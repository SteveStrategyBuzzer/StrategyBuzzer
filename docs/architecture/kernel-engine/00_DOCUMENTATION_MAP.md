# DOCUMENTATION MAP — StrategyBuzzer Kernel Engine

**Date :** 2026-08-19  
**But :** séparer la vérité canonique, le travail en cours, les preuves et l’historique afin qu’un changement de chat n’oblige plus à reconstruire l’état du projet depuis la conversation.

## Règle de classement

| Type | Dossier | Autorité |
|---|---|---|
| Contrat verrouillé | `specifications/` | OUI, pour le module concerné |
| Reconstruction / référence / brides | `working/` | NON, sauf brides déjà validées sur leur frontière exacte |
| Contrat transversal | `cross-module/` | selon son statut, jamais un moteur 01–11 implicite |
| Audit | `audits/` | preuve, pas contrat |
| Certificat | `certificates/` | preuve de statut/validation, pas remplacement du contrat |
| Historique / supersédé | `archive/` | NON |

## Migration réalisée

| Source de récupération | Destination | Statut après classement |
|---|---|---|
| `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md` | `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md` | maître actif harmonisé |
| `00_ArchitectureRegister_ACTIVE.md` | `00_ArchitectureRegister.md` | registre actif copié sans suppression ni nouvelle DEC |
| Constitution historique Document 1 | `00_ConstitutionCognitive.md` | v1.1.0 OFFICIAL, harmonisation des termes supersédés |
| `01_KernelBlueprint_RECONSTRUCTION_ACTIVE.md` | `working/01_KernelBlueprint/` | reconstruction, non promue |
| `02_KernelRotationPlanner_REFERENCE_ACTIVE.md` | `working/02_KernelRotationPlanner/` | référence v3.3, non promue |
| `03_Taxonomy_ACTIVE_SPEC.md` | `specifications/03_Taxonomy.md` | **canonique v1.0 VERROUILLÉE** |
| `03_Taxonomy_COHERENCE_AUDIT.md` | `audits/03_Taxonomy/` | preuve |
| `03_Taxonomy_CERTIFICAT_VERROUILLAGE.md` | `certificates/03_Taxonomy/` | preuve |
| `04_ValidationDominantIdeas_BRIDES_ACTIVE.md` | `working/04_ValidationDominantIdeas/` | brides seulement |
| `05_QuestionIntent_BRIDES_ACTIVE.md` | `archive/superseded/` | supersédé documentairement |
| certificat terminal QI récupéré dans l’historique | `certificates/05_QuestionIntent/*RECOVERY_INDEX.md` | index de récupération, pas spécification |
| `06..11 *_BRIDES_ACTIVE.md` | `working/<module>/` | brides seulement |
| `00_ADMIN_OPERATIONS_BRIDES_ACTIVE.md` | `cross-module/` | transverse hors roadmap 01–11 |
| `StrategyBuzzer_Registre_AntiDoublons.md` | `archive/superseded/` | supersédé ; ownership anti-doublon = Taxonomy |
| grand document historique du 2026-08-19 | `archive/chat-reconstructions/` | historique uniquement |

## Fichiers volontairement NON créés

Aucun faux contrat canonique n’est créé pour :

- `specifications/01_KernelBlueprint.md` — reconstruction à certifier ;
- `specifications/02_KernelRotationPlanner.md` — v3.3 à réécrire/verrouiller ;
- `specifications/04_ValidationDominantIdeas.md` — brides seulement ;
- `specifications/05_QuestionIntent.md` — module verrouillé mais fichier canonique original non récupéré ;
- `specifications/06..11` — pas encore verrouillés.

Cette absence est intentionnelle : **un nom de fichier canonique ne doit jamais donner une fausse impression de verrouillage.**
