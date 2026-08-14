---
name: Architecture Freeze Rules — KRP v3.2
description: Règles absolues sur l'architecture figée KRP v3.2. La spec pilote le code. Interdit de modifier l'archi pour faciliter un test ou débloquer une impasse.
---

## Principe fondamental

**LA SPEC FIGÉE PILOTE LE CODE. LE CODE NE PILOTE JAMAIS LA SPEC.**

02_KernelRotationPlanner v3.2 est VERROUILLÉ.
01_KernelBlueprint est VERROUILLÉ.

---

## Ce qui est autorisé

- Corriger le code pour le rendre conforme à la spec verrouillée
- Supprimer du legacy explicitement SUPERSEDED
- Créer les migrations nécessaires pour atteindre l'état persistant défini
- Adapter les tests à l'architecture verrouillée
- Ajouter des tests pour prouver les invariants verrouillés
- Choisir un détail purement technique quand plusieurs implémentations respectent le même contrat

---

## Ce qui est interdit (liste non exhaustive)

- Changer une responsabilité, la déplacer, en créer une nouvelle
- Créer un nouvel état, signal ou transition métier
- Changer le propriétaire d'une donnée ou d'un signal
- Changer le DomainCycle ou le DepthCycle
- Modifier la règle write-once du Blueprint
- Modifier CURRENT_KERNEL_RECEIVED
- Modifier la signification de DOMAIN_EXHAUSTED ou DEPTH_EXHAUSTED
- Faire produire un signal Taxonomy par KRP
- Réintroduire AVAILABLE, SHORTFALL, CYCLE_TARGET, cycle_completed, EMPTY
- Réattribuer un Blueprint / créer overwriteRotation
- Interpréter peekNext(null) comme une information métier
- Créer une reprise automatique depuis PRODUCTION_ON_HOLD
- Commencer 03_Taxonomy (LOT C INTERDIT)
- Inventer une nouvelle DEC pour faciliter l'implémentation
- **Retirer `final` d'une classe uniquement parce que PHPUnit ne sait pas la mocker**
- Modifier une classe/interface/frontière architecturale uniquement pour faciliter un test

---

## LOT C — INTERDIT

Aucune implémentation actuelle ne peut :
- Modifier Taxonomy pour produire les nouveaux signaux
- Décider comment Taxonomy détecte réellement l'épuisement
- Modifier confirmConsumed
- Définir le comportement final de peekNext(null)
- Consommer les SubjectSlots/IdeaSlots
- Commencer 03_Taxonomy

---

## Règle sur les tests

Un test ne peut jamais devenir une justification pour modifier l'architecture.

Si un test est incompatible avec la spec verrouillée :
1. Vérifier si le test représente une architecture SUPERSEDED → le supprimer ou réécrire
2. Sinon → NOMMER L'IMPASSE

**INTERDIT :** modifier le code de production uniquement pour rendre un mock possible.
**INTERDIT :** retirer `final` d'une classe parce que PHPUnit ne sait pas la mocker.

→ Solution sans violer l'archi : mocker les interfaces (autorisé), provoquer de vraies erreurs DB dans les tests (autorisé), supprimer le test s'il teste une archi SUPERSEDED.

---

## Règle du vide

| Situation | Action |
|-----------|--------|
| Information métier manquante | NE PAS INFÉRER → NOMMER L'IMPASSE |
| Règle ambiguë | NE PAS CHOISIR → NOMMER L'IMPASSE |
| Contradiction apparente | NE PAS CONTOURNER → NOMMER L'IMPASSE |
| Responsabilité non définie | NE PAS L'INVENTER → NOMMER L'IMPASSE |

---

## Format d'une impasse

```
IMPASSE-00N
Type :
Fichier / mécanisme concerné :
Contrat verrouillé concerné :
Ce que le code actuel permet :
Ce que le contrat exige :
Pourquoi les deux ne peuvent pas être conciliés :
Ce qui manque pour continuer :
Dépend de : KRP / Taxonomy / Blueprint / migration / orchestration / autre
Impact si on continue sans décision :
Code modifié sur cette impasse : NON
```

Après avoir nommé l'impasse → continuer uniquement les corrections qui n'en dépendent pas.

---

## Choix technique vs choix architectural

| Type | Exemple | Verdict |
|------|---------|---------|
| Technique (autorisé) | SELECT FOR UPDATE pour garantir l'atomicité | OK — même résultat métier |
| Technique (autorisé) | DTO RotationResolution pour transporter la sélection | OK — pas de nouvelle source de vérité |
| Architectural (interdit) | Ajouter état RECOVERY_PENDING pour gérer un crash | NON — modifie le contrat |
| Architectural (interdit) | RotationResolution devient source de vérité persistante | NON — sans décision explicite |

---

## Règle sur le legacy

| Situation | Action |
|-----------|--------|
| Compatible et requis par la spec | Conserver |
| SUPERSEDED et zéro caller nécessaire | Supprimer |
| Temporairement nécessaire jusqu'à LOT C | Isoler strictement, ne pas étendre, ne pas en faire dépendre le nouveau KRP |

---

**Why:** Ces règles ont été posées explicitement par l'utilisateur le 2026-08-14 pour préserver l'intégrité architecturale de KRP v3.2 VERROUILLÉ. Toute déviation, même mineure, doit passer par le protocole IMPASSE.

**How to apply:** Avant chaque modification de code dans le périmètre KRP/Blueprint/Taxonomy : vérifier que l'action est dans la liste "autorisé". Si le moindre doute → NOMMER L'IMPASSE avant d'écrire la moindre ligne.
