# STRATEGYBUZZER — 10_QUARANTINE

**Version :** 0.1  
**Date :** 28 août 2026  
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODULE À COMPLÉTER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission verrouillée

Quarantine reçoit une copie complète et contextualisée du KernelBlueprint lorsqu’une création source ou une traduction comporte une suspicion d’erreur.

Quarantine ne reçoit jamais seulement le fragment fautif.

Quarantine ne devient jamais propriétaire du Blueprint canonique et ne crée jamais une nouvelle identité intellectuelle.

# 2. Contenu obligatoire de la copie

La copie Quarantine contient l’état complet disponible du Blueprint :

- `blueprint_id`;
- `kernel_code`;
- identité intellectuelle;
- sept CognitiveSlots;
- contenus source déjà produits;
- réponses;
- choix;
- SV;
- traductions déjà produites;
- slots vides;
- créations dépendantes non produites;
- validations disponibles;
- chemins soupçonnés;
- raisons de suspicion;
- étape d’origine.

# 3. Signalement visuel

Les éléments soupçonnés sont enregistrés sous forme de chemins structurés.

L’interface Quarantine les affiche en rouge.

Exemples :

```text
cognitive_slots.QCM_RECOGNITION.source.question

cognitive_slots.QCM_RECOGNITION.translations.el.answer
```

Règles :

- seuls les éléments soupçonnés sont rouges;
- les éléments valides restent normaux;
- les slots dépendants non créés sont affichés comme non créés ou bloqués;
- une absence causée par un blocage amont n’est pas présentée comme une traduction fautive;
- la couleur rouge n’est jamais la seule persistance de l’erreur.

# 4. Le canonique continue

Le Blueprint canonique continue toutes les phases normales jusqu’à ReadyBank.

La création d’une copie Quarantine ne déplace pas le canonique et ne l’empêche pas d’atteindre ReadyBank.

Les slots suspects, vides ou non validés restent toutefois non exploitables par le gameplay.

# 5. Correction de la copie

La correction travaille dans la copie complète avec tout le contexte intellectuel disponible.

Elle modifie uniquement :

- les champs explicitement soupçonnés;
- les slots dépendants restés vides;
- les métadonnées de correction et de validation nécessaires.

Elle ne modifie jamais :

- `blueprint_id`;
- `kernel_code`;
- les slots valides non ciblés;
- les autres langues valides;
- les autres CognitiveSlots valides.

# 6. Reprise du pipeline

La copie corrigée reprend au propriétaire du premier élément corrigé.

## Erreur source

```text
copie corrigée
→ Phase1 ciblée
→ ValidationPhase1 ciblée
→ Phase2 ciblée pour les traductions manquantes
→ ValidationPhase2
→ ReadyBank
```

## Erreur de traduction

```text
copie corrigée
→ Phase2 ciblée
→ ValidationPhase2 ciblée
→ ReadyBank
```

Aucune étape déjà valide n’est rejouée inutilement.

# 7. Sortie Quarantine

Quarantine transmet vers le pipeline une copie complète corrigée portant :

- la référence du canonique;
- les chemins initialement soupçonnés;
- les valeurs avant correction;
- les valeurs corrigées;
- les slots remplis après correction;
- les validations obtenues;
- la traçabilité du parcours ciblé.

La copie rejoint le canonique uniquement dans ReadyBank.

# 8. Interdictions

Quarantine ne doit jamais :

- remplacer globalement le canonique;
- créer un nouveau `kernel_code`;
- modifier l’identité intellectuelle;
- écraser un slot valide hors cible;
- contourner les validations propriétaires;
- rendre directement un contenu au gameplay;
- fusionner elle-même les données dans le canonique;
- renvoyer le Blueprint canonique vers KRP ou Taxonomy.

# 9. Statut restant

Restent à spécifier :

- modèle persistant exact de copie;
- états détaillés;
- acteurs autorisés à corriger;
- règles automatiques/manuelles;
- délais et retries;
- validation de sortie;
- archivage de la copie après fusion;
- interface administrative.

La présente version verrouille la copie complète, le ciblage visuel, la reprise ciblée et la frontière ReadyBank.
