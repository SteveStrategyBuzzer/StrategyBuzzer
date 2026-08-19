# 08_Phase2 — Brides actives avant spécification

**Statut :** À SPÉCIFIER  
**Nature du fichier :** brides déjà connues ; pas une spécification complète.

**Position Blueprint :** SECTION 3 — TRADUCTION. Phase2 traduit tous les éléments de création gameplay portés par les 7 CognitiveSlots : questions, réponses et Saviez-vous (SV).

## Brides connues — relation Blueprint

Phase2 :

```text
lit les CognitiveSlots validés
↓
crée les traductions
↓
remplit les TranslationSlots du Blueprint
```

La traduction couvre, pour chacun des 7 CognitiveSlots :

```text
question
+
réponse(s)
+
Saviez-vous (SV)
```

Le contenu source reste intact ; les traductions l’enrichissent dans les TranslationSlots correspondants.

## Ownership connu

- propriétaire initial des TranslationSlots : Phase2.
- Phase2 ne remplace pas `depth`, `domain`, le triplet Taxonomy, `kernel_code` ni le contenu cognitif source.

## Point à trancher avec 09

Déterminer si `ValidationPhase2` reste une passe séparée ou devient l’ensemble de règles de création/contrôle utilisé directement pendant la création des traductions.