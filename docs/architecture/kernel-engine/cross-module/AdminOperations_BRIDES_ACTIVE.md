# Administration opérationnelle StrategyBuzzer — Brides actives

Statut : BRIDES VALIDÉES — spécification détaillée à venir.
Nature : couche opérationnelle transverse, extérieure au pipeline intellectuel 01–11.

## Mission
Rendre visibles les arrêts de production, persister les incidents, notifier l'administrateur, guider le diagnostic et fournir les actions de reprise autorisées, notamment pour Quarantine.

## Administrateur
- compte admin distinct requis ;
- notification opérationnelle : `lefunnygr@gmail.com`.

## État production visible
L'interface doit au minimum distinguer :
- `RUNNING` : production intellectuelle fonctionnelle ;
- `DEGRADED` : incidents techniques en cours mais seuil de blocage non atteint ;
- `BLOCKED` : production intellectuelle arrêtée pour cause technique ;
- `PRODUCTION_ON_HOLD` : fin normale de cycle de production, distincte d'un incident.

## Registre persistant des incidents
Chaque incident doit conserver au minimum :
- `incident_id` ;
- `fault_code` stable ;
- date/heure début et dernière occurrence ;
- module/source ;
- opération ;
- `blueprint_id` si applicable ;
- `depth` et `domain` si applicables ;
- cause technique ;
- compteur de tentatives ;
- compteur d'échecs complets consécutifs ;
- état de production au moment de l'incident ;
- état de résolution/reprise ;
- historique des actions administratives.

## Familles initiales de codes défauts
Les identifiants exacts seront verrouillés lors de la spécification opérationnelle. Familles nécessaires :
- `GEMINI_*` : timeout, rate limit/quota, service indisponible, réponse absente/tronquée, erreur réseau ;
- `TAXONOMY_*` : impossibilité d'achever une opération Taxonomy pour cause technique ;
- `BLUEPRINT_*` : impossibilité de poursuivre/reprendre un Blueprint ;
- `QUARANTINE_*` : erreur de copie, correction, réintégration ou point de reprise ;
- `READYBANK_*` : erreur de stockage/réception du noyau prêt ;
- `PIPELINE_*` : erreur d'orchestration transverse.

## Routage de réparation
- incident Gemini transitoire : retry automatique Taxonomy ;
- retries épuisés : incident persistant + état dégradé/bloqué selon seuil ;
- cause externe (quota/service/clé/API) : intervention admin/ops ;
- contenu intellectuel FAIL : Quarantine, pas réparation directe par l'admin dans le Blueprint ;
- copie Quarantine à corriger : interface admin → correction de la copie → retour contrôlé Quarantine → Blueprint ;
- bug de code/orchestration : incident identifié par code défaut et transmis au propriétaire technique du module concerné ; aucune modification automatique de l'architecture métier.

## Quarantine dans l'interface admin
L'interface doit permettre :
1. lister les copies Quarantine ;
2. filtrer par module, code défaut, Blueprint, Depth, Domain et statut ;
3. ouvrir la copie avec son contexte et ses erreurs ;
4. corriger uniquement les contenus autorisés par le contrat Quarantine ;
5. soumettre le retour contrôlé ;
6. suivre la réintégration et le point de reprise ;
7. conserver l'audit de la correction.

## Interdictions
- l'admin ne devient pas propriétaire des slots ;
- l'interface ne modifie jamais directement un Blueprint canonique hors contrat Quarantine ;
- un incident technique ne produit jamais `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED` ;
- un incident technique ne doit jamais être enregistré comme `0 matière valide` ;
- `BLOCKED` et `PRODUCTION_ON_HOLD` sont deux états de nature différente et ne doivent jamais être confondus.

## Seuils techniques validés
- `MAX_TECHNICAL_RETRIES = 3` après la tentative initiale, soit 4 tentatives techniques maximum par opération Gemini.
- `MAX_CONSECUTIVE_UNRESOLVED_CALLS = 3` opérations intellectuelles distinctes ayant chacune épuisé leurs 4 tentatives.
- au 3e échec complet consécutif : état opérationnel `BLOCKED` + notification administrateur.
- toute réussite confirmée remet le compteur d'échecs complets consécutifs à 0.

## Codes défauts — nomenclature de travail
La nomenclature suivante est active comme base de conception ; elle pourra être versionnée lors de la spécification détaillée sans changer la logique métier.

### Gemini
- `GEM-001` — `TIMEOUT`
- `GEM-002` — `RATE_LIMIT`
- `GEM-003` — `QUOTA_EXHAUSTED`
- `GEM-004` — `SERVICE_UNAVAILABLE`
- `GEM-005` — `NETWORK_ERROR`
- `GEM-006` — `EMPTY_RESPONSE`
- `GEM-007` — `TRUNCATED_OR_UNUSABLE_RESPONSE`

### Taxonomy
- `TAX-001` — `GEMINI_RETRIES_EXHAUSTED`
- `TAX-002` — `INTELLECTUAL_OPERATION_BLOCKED_TECHNICAL`

### Blueprint / orchestration
- `BLP-001` — `BLUEPRINT_BLOCKED_PENDING_TECHNICAL_RECOVERY`
- `PIP-001` — `NEXT_BLUEPRINT_TRIGGER_FAILED`
- `PIP-002` — `PIPELINE_RESUME_FAILED`

### KRP / rotation
- `KRP-001` — `DOMAIN_STATE_REGRESSION_ATTEMPT`
- `KRP-002` — `DOMAIN_EXHAUSTED_PERSIST_FAILED`
- `KRP-003` — `DEPTH_EXHAUSTED_PERSIST_FAILED`

### Quarantine
- `QUA-001` — `QUARANTINE_COPY_CREATION_FAILED`
- `QUA-002` — `QUARANTINE_CORRECTION_RETURN_FAILED`
- `QUA-003` — `BLUEPRINT_REINTEGRATION_FAILED`
- `QUA-004` — `RESUME_POINT_FAILED`

### ReadyBank
- `RDY-001` — `READYBANK_STORE_FAILED`
- `RDY-002` — `CURRENT_KERNEL_RECEIVED_TRIGGER_FAILED`

## Routage de réparation par type d'arrêt
1. `GEM-*` transitoire avant épuisement du seuil
   - responsable d'action : retry automatique Taxonomy ;
   - admin : information seulement si on souhaite exposer l'incident en mode DEGRADED.
2. `TAX-001/TAX-002` ou `BLOCKED`
   - responsable d'action immédiate : couche Admin/Ops ;
   - notification : `lefunnygr@gmail.com` ;
   - actions autorisées : voir la cause, vérifier Gemini/quota/clé/service, relancer l'opération bloquée, reprendre la production après succès.
3. `QUA-*` / contenu intellectuel à corriger
   - responsable fonctionnel : Quarantine ;
   - interface admin : ouvre la copie, affiche erreurs/contexte, permet la correction autorisée et le renvoi ;
   - le Blueprint canonique n'est jamais modifié directement par l'interface.
4. `BLP-*` / `PIP-*`
   - responsable technique : propriétaire technique du module/orchestrateur concerné ;
   - admin : voit le code défaut, le Blueprint et le point de blocage ;
   - reprise uniquement après résolution de la cause.
5. `RDY-*`
   - responsable technique : ReadyBank / persistance / déclenchement du prochain Blueprint ;
   - ne jamais simuler `CURRENT_KERNEL_RECEIVED` tant que le stockage du noyau n'est pas confirmé.

## Actions minimales de l'interface admin
- `Voir incident`
- `Accuser réception`
- `Relancer l'opération technique`
- `Reprendre la production` après succès confirmé
- `Ouvrir la copie Quarantine`
- `Corriger la copie Quarantine`
- `Renvoyer vers Blueprint` via le contrat Quarantine
- `Voir historique / audit`

Aucune action admin ne peut forcer `DOMAIN_EXHAUSTED`, `DEPTH_EXHAUSTED`, `CONSUMED` ou modifier directement les slots du Blueprint.

## Anomalie de régression KRP
Si un message contradictoire tente de rendre `VISIBLE` un Domain déjà `ESTOMPÉ` dans le même tour de Depth, KRP conserve l’état `ESTOMPÉ`, n’effectue aucun recul, et journalise `KRP-001 DOMAIN_STATE_REGRESSION_ATTEMPT`.

## Cohérence Taxonomy — Domain déclaré terminé avec contenu restant

Code défaut :

```text
TAX-003 — DOMAIN_EXHAUSTION_BLOCKED_REMAINING_CONTENT
```

Déclenchement : Taxonomy s’apprête à émettre `DOMAIN_EXHAUSTED` mais détecte encore au moins un Subject non `UTILISÉ` ou une Dominant Idea `PASS` non `CONSUMED` dans le bassin courant.

L’interface admin doit afficher :
- `Depth + Domain` ;
- Subdomain ;
- nombre de Subjects restants ;
- nombre d’Ideas PASS restantes ;
- liste/identifiants des Banks restantes ;
- dernier contenu consommé ;
- point exact de reprise ;
- cause technique/journal disponible.

Action admin requise :

```text
Reprendre les réservoirs Taxonomy
```

Cette action :
- reprend au curseur persistant ;
- ne régénère pas les Banks existantes ;
- ne duplique pas les contenus déjà `CONSUMED` ;
- ne force jamais `DOMAIN_EXHAUSTED` ;
- ne modifie jamais directement le cadran KRP ;
- laisse le Domain `VISIBLE` tant que les réservoirs ne sont pas réellement vidés.


## Échec de persistance de rotation KRP

Pour `KRP-002` / `KRP-003` :
- 1 tentative initiale + 3 retries de persistance ;
- aucune création de Blueprint suivant tant que le `RotationState` n’est pas commit avec succès ;
- après épuisement : état opérationnel `BLOCKED`, incident persistant et notification admin ;
- action de reprise : réappliquer la transition en attente de façon idempotente puis confirmer le `COMMIT` ;
- le Blueprint courant déjà produit n’est ni supprimé ni dupliqué.