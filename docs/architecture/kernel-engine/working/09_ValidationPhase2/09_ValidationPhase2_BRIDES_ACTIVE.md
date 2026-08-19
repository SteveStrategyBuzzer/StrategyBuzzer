# 09_ValidationPhase2 — Brides actives avant spécification

**Statut :** À DÉTERMINER / À SPÉCIFIER  
**Nature du fichier :** point architectural ouvert ; ne pas traiter comme contrat verrouillé.

## Ce qui est déjà sûr

- ValidationPhase2 ne possède pas les traductions initiales ; ownership initial = Phase2.
- Son mode d’exécution doit être déterminé avant de figer les états TranslationSlots dans le Blueprint.

## Décision à prendre

Évaluer si :

```text
Gemini traduction
+
règles ValidationPhase2
↓
création + contrôle des traductions dans le même travail
↓
PASS / FAIL par traduction / slot
```

plutôt qu’une seconde passe autonome relisant ensuite le Blueprint.

Ce point reste OUVERT.