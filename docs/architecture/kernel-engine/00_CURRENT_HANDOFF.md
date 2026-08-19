# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-19  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Baseline avant récupération documentaire :** `db260475`  
**Bloc actif :** SECTION 1 — CRÉATION INTELLECTUELLE

> Ce fichier n’a aucune autorité architecturale propre. Il indique uniquement le point exact de reprise entre deux chats. En cas de contradiction, `Architecture Register + spécification canonique verrouillée + document maître actif` priment.

## Ordre du bloc

```text
01 KernelBlueprint
↓
02 KernelRotationPlanner
↓
03 Taxonomy
↓
04 ValidationDominantIdeas
↓
05 QuestionIntent
↓
CERTIFICAT DE FERMETURE — SECTION 1
↓
06 Phase1 — SECTION 2 CRÉATION GAMEPLAY
```

## État exact

### 01 — KernelBlueprint
- module : **VERROUILLÉ historiquement** ;
- reconstruction actuelle : `working/01_KernelBlueprint/01_KernelBlueprint_RECONSTRUCTION_ACTIVE.md` ;
- frontière active : `KernelBlueprintFactory` crée l’enveloppe canonique et `blueprint_id`; KRP ne crée pas le Blueprint ;
- Section 1 : ownership structurel connu ;
- action requise avant promotion canonique : **terminer/certifier la reconstruction canonique** ;
- ne pas fabriquer `specifications/01_KernelBlueprint.md` tant que cette certification n’est pas faite.

### 02 — KernelRotationPlanner
- v3.2 : **VERROUILLÉE historiquement** ;
- état actif : **RÉVISION v3.3 OBLIGATOIRE** ;
- doit intégrer `cycle_target/cycle_completed`, Tours de Depth, horloge des 8 Domaines, nouveau sens de `DEPTH_EXHAUSTED`, persistance/idempotence ;
- source de travail : `working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md` ;
- ne pas promouvoir v3.2 comme contrat actif lorsque ses règles sont supersédées par DEC-094/108/111.

### 03 — Taxonomy
- v1.0 : **SPÉCIFICATION VERROUILLÉE** ;
- Architecture : **100 %** ;
- Contrat : **100 %** ;
- Implémentation : **0 % à auditer/adapter** ;
- Validation code : **0 %** ;
- canon : `specifications/03_Taxonomy.md`.

### 04 — ValidationDominantIdeas
- état : **À SPÉCIFIER complètement** dans son tour officiel ;
- brides actives : `working/04_ValidationDominantIdeas/` ;
- frontière déjà active : mécanisme/règles utilisé par Gemini pendant la création des Dominant Ideas sous orchestration Taxonomy ;
- aucun accès direct Blueprint ; aucune ownership des Banks/anti-doublons Taxonomy.

### 05 — QuestionIntent
- v1.0 : **VERROUILLÉ selon certificat terminal récupéré** ;
- Architecture : **100 %** ; Contrat : **100 %** ; Implémentation : **100 %** ; Validation : **100 %** ;
- blockers : **AUCUN** ;
- entrée canonique connue : `depth + domain + subdomain_active + subject_active + dominant_idea_active` ;
- seule écriture canonique : `kernel_code` ;
- le vrai fichier `05_QuestionIntent.md` n’a pas encore été récupéré dans les sources présentes ; **ne pas le réécrire de mémoire** ;
- l’ancien fichier `05_QuestionIntent_BRIDES_ACTIVE.md` est archivé comme supersédé.

## Prochain travail EXACT

**01_KernelBlueprint — terminer et certifier la reconstruction canonique de la Section 1 avant de commencer la réécriture complète de KRP v3.3.**

Après 01 certifié :
1. reconstruire intégralement `02_KernelRotationPlanner` v3.3 ;
2. mettre à jour l’Architecture Register si une nouvelle décision est réellement créée ;
3. verrouiller v3.3 ;
4. conserver `03_Taxonomy` comme contrat verrouillé ;
5. spécifier `04_ValidationDominantIdeas` ;
6. auditer la compatibilité de `05_QuestionIntent` v1.0 sans le redessiner si aucune contradiction ;
7. produire le certificat de fermeture de la Section 1 ;
8. seulement ensuite ouvrir `06_Phase1`.

## Corrections documentaires déjà actées dans cette récupération

- ancien « KRP crée le Blueprint » → **supersédé dans les documents actifs par `KernelBlueprintFactory`** ;
- ancienne boucle de réutilisation/écrasement du même Blueprint sur `EMPTY` → **historique uniquement, incompatible avec write-once** ;
- Taxonomy n’est plus `EN CONCEPTION` → **v1.0 verrouillée** ;
- QuestionIntent n’est plus `À SPÉCIFIER` → **v1.0 verrouillé selon certificat récupéré** ;
- VDI n’est pas un deuxième moteur autonome de validation après Gemini ;
- ancien `0..5 PASS` comme état normal d’un Subject → supersédée : **1..5 PASS requis pour une préparation réussie ; 0 PASS = anomalie**.

## Ne pas commencer

- implantation de KRP v3.3 avant verrouillage ;
- `04_ValidationDominantIdeas` avant fermeture documentaire 01/02 ;
- `06_Phase1` avant certificat de fermeture de la Section 1 ;
- migration de documents historiques vers `specifications/` sans certification.
