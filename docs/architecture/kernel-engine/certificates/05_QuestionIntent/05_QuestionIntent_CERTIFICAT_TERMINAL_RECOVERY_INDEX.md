# 05_QuestionIntent — Index de récupération du certificat terminal

**Date de récupération :** 2026-08-19  
**Nature :** preuve documentaire récupérée ; **ce fichier n’est ni la spécification canonique ni une réécriture du certificat original**.  
**Statut architectural récupéré :** `05_QuestionIntent v1.0 — VERROUILLÉ`.

## Faits récupérés du certificat terminal / historique de validation

- Architecture : **100 %**
- Contrat : **100 %**
- Implémentation : **100 %**
- Validation : **100 %**
- Blockers restants : **AUCUN**
- Entrée canonique : `depth + domain + subdomain_active + subject_active + dominant_idea_active`
- Slot Blueprint écrit : **`kernel_code` uniquement**
- Format certifié dans l’historique terminal : `DD-DO-SUB-SUJ-IDE-VVVV`
- Suffixe : base36, 4 caractères, `0000 → ZZZZ`
- Source canonique d’identité runtime indiquée par le certificat : `kernel_blueprint_runs.kernel_code`
- `KLD` actif dans QuestionIntent : **NON**
- `KEY_STRUCTURE` actif dans QuestionIntent : **NON**
- `ks_hash` runtime QuestionIntent : **ABSENT**
- `kld_hash` runtime QuestionIntent : **ABSENT**
- `question_intents.kernel_code` : **ABSENT**
- Architecture parallèle : **AUCUNE**
- Régressions introduites au certificat : **0**

## Règle de récupération

Le vrai fichier canonique `05_QuestionIntent.md` doit être récupéré depuis l’historique/artefact original. Tant qu’il n’est pas retrouvé :

1. ne pas réinventer sa spécification ;
2. ne pas rétrograder le module à « À SPÉCIFIER » ;
3. utiliser les faits ci-dessus uniquement comme index de continuité et vérifier toute précision supplémentaire contre la source originale avant modification ;
4. archiver `05_QuestionIntent_BRIDES_ACTIVE.md` comme `SUPERSEDED` documentairement.
