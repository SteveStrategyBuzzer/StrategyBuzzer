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
| Historique / supersédé | `archive/` ou historique Git | NON |

## État canonique actuel

| Module / document | Destination | Statut |
|---|---|---|
| Constitution | `00_ConstitutionCognitive.md` | OFFICIAL |
| Architecture Register | `00_ArchitectureRegister.md` | registre actif |
| Master | `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md` | vérité globale active |
| Handoff | `00_CURRENT_HANDOFF.md` | reprise opérationnelle uniquement |
| 01 KernelBlueprint | `specifications/01_KernelBlueprint.md` | **v2.0 VERROUILLÉE** |
| Certificat 01 | `certificates/01_KernelBlueprint/` | preuve de verrouillage |
| Reconstruction 01 | `working/01_KernelBlueprint/01_KernelBlueprint_RECONSTRUCTION_ACTIVE.md` | **CLOSED/PROMOTED pointer** |
| Reconstruction antérieure 01 | historique Git du fichier `working/01_KernelBlueprint/01_KernelBlueprint_RECONSTRUCTION_ACTIVE.md` | historique non actif |
| 02 KernelRotationPlanner | `working/02_KernelRotationPlanner/` | référence v3.3, non promue |
| 03 Taxonomy | `specifications/03_Taxonomy.md` | **v1.0 VERROUILLÉE** |
| Audit/Certificat 03 | `audits/03_Taxonomy/`, `certificates/03_Taxonomy/` | preuves |
| 04 ValidationDominantIdeas | `working/04_ValidationDominantIdeas/` | brides seulement |
| 05 QuestionIntent | `certificates/05_QuestionIntent/` + source historique récupérée | statut verrouillé selon certificat ; canon kernel-engine à consolider sans réécriture de mémoire |
| 06..11 | `working/<module>/` | brides seulement |
| Admin/Ops | `cross-module/` | transverse hors roadmap 01–11 |

## Règle de promotion

Un fichier n’entre dans `specifications/` qu’après :

```text
Mission
↓
Responsabilités
↓
Interdictions
↓
Entrées
↓
Sorties
↓
Slots Blueprint
↓
Données internes
↓
Mécanismes
↓
Communication
↓
Contrats
↓
États
↓
Transitions
↓
Cas limites
↓
Persistance
↓
Validation
↓
Tests
↓
Architecture = 100 %
↓
certificat de verrouillage
```

## Fichiers canoniques volontairement NON créés à ce jour

Aucun faux contrat canonique n’est créé pour :

- `specifications/02_KernelRotationPlanner.md` — v3.3 non encore reconstruite/verrouillée ;
- `specifications/04_ValidationDominantIdeas.md` — brides seulement ;
- `specifications/05_QuestionIntent.md` dans `kernel-engine/` — le module est verrouillé selon certificat mais sa consolidation documentaire doit respecter les sources récupérées, jamais être réécrite de mémoire ;
- `specifications/06..11` — pas encore verrouillés.

## Bloc opérationnel actuel

```text
SPEC-01-CLOSE = CLOSED
↓
AUDIT-01-00 = NEXT
```

Aucun code 01 ne doit être patché avant l’audit.

`02_KernelRotationPlanner` reste fermé au travail jusqu’à fermeture implantation + validation de 01.