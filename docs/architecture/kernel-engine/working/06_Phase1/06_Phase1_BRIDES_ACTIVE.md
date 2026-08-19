# 06_Phase1 — Brides actives avant spécification

**Statut :** À SPÉCIFIER  
**Nature du fichier :** brides déjà connues ; pas une spécification complète.

**Position Blueprint :** SECTION 2 — CRÉATION GAMEPLAY.

## Brides connues — relation Blueprint

Phase1 :

```text
lit le territoire / identité nécessaires du Blueprint
↓
crée les 7 questions cognitives
↓
remplit les 7 CognitiveSlots du Blueprint
```

Les 7 cognitifs connus sont :

```text
QCM Recognition
QCM Reasoning
QCM Deceptive Trap
TF Recognition True
TF Recognition False
TF Reasoning True
TF Reasoning False
```

Chaque CognitiveSlot porte fonctionnellement :

```text
question
+
réponse(s)
+
Saviez-vous (SV)
```

Le schéma technique exact sera verrouillé pendant la spécification officielle de Phase1.

## Ownership connu

- propriétaire initial du contenu des 7 CognitiveSlots : Phase1.
- ValidationPhase1 ne devient pas propriétaire initial de ces contenus.

## Point à trancher avec 07

Déterminer si `ValidationPhase1` reste une passe séparée ou devient l’ensemble de règles de création/contrôle utilisé directement par Gemini pendant Phase1.