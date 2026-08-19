# 07_ValidationPhase1 — Brides actives avant spécification

**Statut :** À DÉTERMINER / À SPÉCIFIER  
**Nature du fichier :** point architectural ouvert ; ne pas traiter comme contrat verrouillé.

## Ce qui est déjà sûr

- ValidationPhase1 ne possède pas le contenu initial des 7 CognitiveSlots ; ownership initial = Phase1.
- Il faut déterminer son mode d’exécution avant de fixer les états Blueprint.

## Décision à prendre

### Option à évaluer prioritairement

```text
Gemini Phase1
+
règles ValidationPhase1
↓
création + contrôle des 7 cognitifs dans le même travail intellectuel
↓
PASS / FAIL par slot
```

Dans ce modèle, ValidationPhase1 deviendrait analogue à ValidationDominantIdeas : règles du mécanisme de création/contrôle utilisé par Gemini, sans lecture directe autonome du Blueprint.

### Ancien modèle à ne pas réaffirmer sans décision

```text
Phase1 écrit
↓
ValidationPhase1 relit ensuite le Blueprint
↓
PASS / FAIL
```

Ce point reste OUVERT.