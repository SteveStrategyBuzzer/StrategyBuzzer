# 04_ValidationDominantIdeas — Brides actives avant spécification

**Statut :** À SPÉCIFIER OFFICIELLEMENT APRÈS TAXONOMY  
**Nature du fichier :** brides validées à intégrer plus tard ; ce fichier n’est pas une spécification verrouillée.

**Contrat entrant désormais verrouillé :** `03_Taxonomy v1.0` — Taxonomy prépare les lots, Gemini utilise les règles VDI pendant la création, puis Taxonomy persiste PASS/FAIL.

**Position Blueprint :** SECTION 1 — CRÉATION INTELLECTUELLE, comme mécanisme de règles utilisé par Gemini dans Taxonomy. `ValidationDominantIdeas` ne lit ni n’écrit directement dans le Blueprint.

## Brides déjà validées

ValidationDominantIdeas possède :

> les règles du mécanisme de création/contrôle des Dominant Ideas utilisé par Gemini.

Il ne constitue pas un moteur autonome qui relit le Blueprint et rend un verdict après coup.

```text
Taxonomy prépare un lot de Subjects
↓
Gemini utilise les règles ValidationDominantIdeas
pendant la création des Dominant Ideas
↓
PASS / FAIL classés par Subject
↓
Taxonomy persiste les résultats
```

## Bride — dominance contextuelle obligatoire

Les règles de `ValidationDominantIdeas` utilisées par Gemini doivent imposer qu'une Dominant Idea soit :

- dominante pour le `Subject` exact ;
- dominante dans le contexte du `Subdomain` parent ;
- conforme à la granularité et aux règles du `Depth` courant ;
- suffisamment spécifique au couple `Subdomain + Subject` pour constituer une direction intellectuelle exploitable ;
- non générique et non ajoutée artificiellement pour remplir les IdeaSlots.

L'évaluation ne doit pas traiter la Dominant Idea comme un mot isolé. Sa validité est contextuelle à `Subdomain + Subject + Depth`.

## Accès Blueprint connu

- lecture directe du Blueprint : NON dans l’architecture active actuelle ;
- écriture directe du Blueprint : NON ;
- possession des banques Taxonomy : NON ;
- consommation des IdeaSlots : NON.

## Frontière anti-doublon

- `ValidationDominantIdeas` ne possède pas les règles anti-doublon.
- Les règles anti-doublon appartiennent à `03_Taxonomy`.
- Taxonomy fournit à Gemini la mémoire PASS/FAIL et les exclusions applicables ; Gemini utilise ensuite les règles `ValidationDominantIdeas` pour la création/contrôle intellectuel des Dominant Ideas.

## Bride validée — minimum d’exploitabilité par Subject

Lorsqu’il est utilisé par Gemini pendant la préparation des Dominant Ideas, le mécanisme `ValidationDominantIdeas` participe à un contrat où tout Subject déjà accepté dans la SubjectBank doit aboutir à **au moins une Dominant Idea `PASS`** pour que sa préparation soit considérée réussie.

`0 PASS` pour un Subject accepté n’est pas un verdict métier normal d’épuisement ; c’est une anomalie de génération / non-respect du contrat de préparation à traiter par Taxonomy. Le maximum reste 5 PASS, sans obligation de remplir les 5 slots.