# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-09-16
**Branche officielle :** `replit/intellectual-engine-current-2026-08-16`
**Autorité documentaire courante :** DEC-119, DEC-120, DEC-125, DEC-126 et
DEC-127 selon leur module
**Contrat nouvellement verrouillé :** Phase2 v1.0 + ValidationPhase2 v1.0
**Implémentation DEC-127 :** NON — tâche technique distincte
**Validation documentaire :** PASS

Ce fichier est un pointeur opérationnel courant. En cas de contradiction,
l’ordre d’autorité est :

```text
00_ConstitutionCognitive.md
→ 00_ArchitectureRegister.md
→ 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
→ spécification canonique du module concerné
```

# 1. Sources actives

```text
01 KernelBlueprint       → specifications/01_KernelBlueprint.md v3.2
02 KRP                   → specifications/02_KernelRotationPlanner.md v4.0
03 Taxonomy              → specifications/03_Taxonomy.md v1.1
05 QuestionIntent        → specifications/05_QuestionIntent.md v2.3
06 Phase1                → specifications/06_Phase1.md v1.2
07 ValidationPhase1      → specifications/07_ValidationPhase1.md v1.2
08 Phase2                → specifications/08_Phase2.md v1.0
09 ValidationPhase2      → specifications/09_ValidationPhase2.md v1.0
10 Quarantine            → specifications/10_Quarantine.md v1.1
11 ReadyBank             → specifications/11_ReadyBank.md v0.4
```

ReadyBank demeure un module global à compléter. Sa frontière terminale Phase2,
sa publication par CognitiveSlot et son routage Quarantaine/KBP sont néanmoins
verrouillés par DEC-125/127.

# 2. Langue intellectuelle canonique — DEC-126

L’anglais est la langue de création intellectuelle :

```text
Domain
Subdomain
Subject
Dominant Idea
question source
choix source
bonne réponse source
SV source
```

Phase2 crée exactement neuf traductions directes et indépendantes depuis la
même source anglaise :

```text
fr, es, de, it, pt, ru, zh, ar, el
```

`General` appartient uniquement au mode Shuffle gameplay. Il n’est jamais un
Domaine créateur. La Bible, les règles, les titres et l’Admin restent français.
La langue choisie par le joueur reste externe à l’identité intellectuelle.

Aucune donnée française existante n’est convertie par DEC-126/127.

# 3. Contrat terminal Phase2 — DEC-127

L’identité logique d’une traduction est :

```text
blueprint_id
+ cognitive_type
+ language_code
+ source_revision
```

Chaque cible possède sa propre `translation_revision`. Phase2 possède les états
de création; ValidationPhase2 possède les états de validation. Un retour
portant une ancienne révision, un ancien cycle ou un ancien claim est un NO-OP.

Une cible admissible doit être `CREATED + PASS`, complète, courante et sans
finding `BLOCKING`, claim ou retry actif. Un CognitiveSlot admissible exige la
source anglaise PASS et les neuf cibles admissibles.

Les pannes techniques autorisent une tentative initiale et trois retries aux
planchers `1 / 5 / 15 minutes`; un `Retry-After` supérieur prévaut. Elles ne
créent ni rouge, ni finding intellectuel, ni Quarantaine automatique. Seule
ValidationPhase2 indépendante peut conclure `CONTENT_UNTRANSLATABLE`.

Les enveloppes internes, identités, révisions, claims et références de stockage
ne sont jamais transmis aux fournisseurs. Les références externes sont opaques.

# 4. Quarantaine et ReadyBank

Les sept slots d’une copie Quarantaine sont visibles et modifiables par
l’Admin, y compris les slots verts. Une modification réelle invalide l’ancien
PASS, crée une révision jaune et interdit tout écrasement par un ancien retour.

Un slot publié retouché est retiré de Gameplay dans toutes les langues dans la
même transaction. Les autres slots publiés et non modifiés restent disponibles.

ReadyBank publie atomiquement par `blueprint_id + cognitive_type` avec un
manifeste comprenant `source_revision` et les neuf `translation_revision`.
Chaque slot termine le passage comme :

```text
PUBLISHED
CONTENT_QUARANTINED
TECHNICALLY_BLOCKED
```

`CURRENT_KERNEL_RECEIVED` exige sept issues terminales et aucune opération
active; il n’exige pas sept slots publiés. Chaque événement et chaque direction
sont persistés une fois. La Quarantaine READY FIFO reste prioritaire sur KBP.

# 5. Prochain travail autorisable

Le contrat documentaire est terminé. Une tâche technique distincte peut
maintenant auditer puis implanter DEC-127.

Cette future tâche ne doit pas :

- rouvrir les décisions fonctionnelles;
- déduire l’architecture depuis le code;
- convertir les données françaises historiques;
- choisir silencieusement un fournisseur;
- modifier KRP, Taxonomy ou l’identité Blueprint hors adaptation indispensable
  et explicitement prouvée;
- regrouper implantation, conversion legacy et validation PostgreSQL finale en
  un seul changement non auditable.