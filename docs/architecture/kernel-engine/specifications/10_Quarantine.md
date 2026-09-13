# STRATEGYBUZZER — 10_QUARANTINE

**Version :** 0.2
**Date :** 2026-09-13
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODULE À COMPLÉTER  
**Décision directrice :** DEC-125 — OFFICIAL (clauses compatibles de DEC-122)
**Implémentation :** À AUDITER  
**Validation terminale :** NON

> **Remplace :** v0.1 sur l’autorité de la copie, l’édition, la reprise et le
> cycle de circulation. Les clauses contraires sont `SUPERSEDED BY DEC-125`
> avant leur remplacement; DEC-122 reste historique et actif pour ses clauses
> compatibles.

## 0. Règles actives DEC-125

Quarantine persiste une copie de travail complète et non canonique avec les
sept slots, contenus et findings disponibles. Lorsqu’un slot canonique est
`SUSPICION` ou `EMPTY`, sa position est vide; le contenu rejeté et ses findings
restent dans la copie. Les sept slots sont visibles et éditables : rouge =
`SUSPICION`/`EMPTY`; vert = conforme et jamais encore modifié manuellement;
jaune = rempli/modifié manuellement et conservé jusqu’à ReadyBank,
marqueur de parcours et non état fonctionnel. Modifier un vert le rend jaune
et invalide immédiatement son ancien `PASS` aval.

Admin est une UI externe, pas une phase; suppression Admin = suppression de la
copie seulement. Une copie complète recommence toujours en Phase1. Elle
accompagne les sept slots comme contexte, mais chaque slot ne rejoue que les
contrôles/créations qui le concernent : Phase1 technique, ValidationPhase1
intellectuelle, Phase2 autorisée, ValidationPhase2 traduction, puis ReadyBank.
Les slots conformes continuent, les slots échoués restent vides. Une copie
peut cycler plusieurs fois avec les findings les plus récents; aucune histoire
permanente des corrections n’est conservée; plusieurs copies prêtes sont
permises.

Le renvoi ne démarre pas la copie : il l’enqueue selon l’ordre exact des clics,
FIFO, un seul traitement à la fois.

**OPEN IMPLEMENTATION REQUIREMENTS — solutions non approuvées :**

1. persistance de la copie complète courante;
2. file d’attente des clics Renvoie;
3. ordre exact des demandes;
4. détection des slots modifiés;
5. conservation du marqueur jaune jusqu’à ReadyBank;
6. invalidation immédiate d’un ancien PASS après modification;
7. protection contre les retours périmés;
8. idempotence de `CURRENT_KERNEL_RECEIVED`;
9. fusion atomique dans ReadyBank.

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

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125 :** le canonique ne
> poursuit plus une position suspecte; celle-ci est vidée et la copie porte le
> contenu rejeté et ses findings. Les slots conformes continuent séparément.

Le Blueprint canonique continue toutes les phases normales jusqu’à ReadyBank.

La création d’une copie Quarantine ne déplace pas le canonique et ne l’empêche pas d’atteindre ReadyBank.

Les slots suspects, vides ou non validés restent toutefois non exploitables par le gameplay.

# 5. Correction de la copie

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125 :** l’édition n’est plus
> limitée aux champs suspects et slots dépendants; les sept slots sont
> éditables, avec les règles de couleur de la section 0.

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

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125 :** toute copie complète
> corrigée reprend d’abord en Phase1, y compris après une erreur de traduction.

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

**Remplacement actif DEC-125 :** la copie recommence toutefois toujours par
Phase1; les slots accompagnent comme contexte et seuls les contrôles/créations
concernés sont exécutés à chaque phase. Les conformes continuent, les échoués
restent vides.

# 7. Sortie Quarantine

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125 :** le retour ne porte
> plus seulement un ciblage forensique; il porte la copie complète courante,
> avec les sept slots et les findings les plus récents.

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

> **CLAUSE v0.1 CI-DESSOUS — SUPERSEDED BY DEC-125, PORTÉE LIMITÉE :**
> l’interdiction historique d’écraser un slot valide hors cible ne vaut que
> pour un slot vert non modifié manuellement. Une modification manuelle le
> rend jaune, invalide son ancien PASS et suit le cycle complet.

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

La présente version verrouille la copie complète, le ciblage visuel, la reprise
Phase1 avec contrôles concernés par slot et la frontière ReadyBank.
