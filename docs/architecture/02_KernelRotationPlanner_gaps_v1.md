Il manque encore les éléments suivants pour que le KernelRotationPlanner soit fonctionnel à 100 % sur le plan architectural.

1. Initialisation complète

Le mécanisme doit définir sans ambiguïté :

Premier démarrage
↓
charger DepthCycle : 2 → 4 → 6 → 7 → 8 → 9
↓
charger les kernel_target du Depth 2
↓
obtenir l’état Taxonomy des huit domaines
↓
sélectionner le premier domaine exploitable
↓
créer le premier Blueprint

Il faut également définir la reprise lorsqu’une rotation existe déjà.

2. Contrat exact de la DepthNeedMatrix

Pour chaque couple :

Depth + Domaine

il faut verrouiller :

la provenance de kernel_target ;
sa valeur ;
son unité exacte ;
son versionnement ;
la règle lorsqu’une cible change ;
le calcul de kernel_received ;
le calcul de kernel_remaining.
kernel_remaining = kernel_target - kernel_received

Sans cette partie, le compte à rebours n’est pas entièrement défini.

3. Synchronisation ReadyBank–Taxonomy

Le Planner doit attendre deux informations séparées :

ReadyBank
→ CURRENT_KERNEL_RECEIVED
Taxonomy
→ état actualisé des réservoirs

Il faut encore verrouiller :

comment ces informations sont rattachées au même tour ;
comment leur fraîcheur est vérifiée ;
que faire si elles arrivent dans le désordre ;
que faire si l’une arrive deux fois ;
que faire si l’une n’arrive jamais ;
comment éviter d’utiliser un ancien état Taxonomy.
4. Cycle de vie complet d’un domaine

Les transitions doivent être totalement fermées :

PENDING
↓
AVAILABLE
↓
ACTIVE
↓
AVAILABLE

ou :

ACTIVE
↓
TARGET_COMPLETE

ou :

ACTIVE
↓
RESERVOIR_EMPTY

ou :

ACTIVE
↓
EMPTY_BEFORE_TARGET

Il faut définir précisément quel événement provoque chaque transition.

5. Règle exacte de fermeture d’un Depth

Le Planner doit distinguer :

DEPTH_TARGET_COMPLETE

et :

DEPTH_COMPLETE_WITH_SHORTFALL

Mais il faut encore décider officiellement ce que fait le Planner dans le second cas :

passe-t-il automatiquement au Depth suivant ;
arrête-t-il la production ;
produit-il une alerte architecturale ;
attend-il une nouvelle alimentation de Taxonomy ?

Cette décision est indispensable.

6. Création atomique du Blueprint

Le mécanisme doit garantir :

sélection Depth + Domaine
+
création Blueprint
+
écriture depth
+
écriture domain
+
enregistrement de la référence active

comme une seule transition.

Il faut spécifier la réaction exacte si l’opération échoue au milieu.

7. Comptabilisation atomique et idempotente

Lors de :

CURRENT_KERNEL_RECEIVED

le Planner doit exécuter une seule fois :

vérification de l’identité
↓
kernel_received + 1
↓
kernel_remaining - 1
↓
enregistrement du Blueprint comptabilisé

Il faut verrouiller l’identifiant utilisé pour empêcher le double comptage :

blueprint_reference ;
kernel_code ;
ou les deux.
8. Persistance du RotationState

Il faut définir le contrat persistant exact :

rotation_version
depth_position
active_depth
domain_position
active_domain
kernel_target
kernel_received
kernel_remaining
reservoir_status
domain_state
active_blueprint_reference
readybank_signal_received
taxonomy_signal_received
rotation_status

Il faut également définir :

quand chaque valeur est persistée ;
quelles écritures sont atomiques ;
comment une reprise est réconciliée avec ReadyBank et Taxonomy ;
quelle source gagne en cas de divergence.
9. Gestion des anomalies

Le module doit avoir une règle explicite pour chaque cas :

ReadyBank confirme un Blueprint inconnu ;
ReadyBank confirme deux fois le même Blueprint ;
Taxonomy déclare vide un domaine actif ;
kernel_remaining devient négatif ;
aucun domaine n’est sélectionnable ;
un état Taxonomy est périmé ;
un Blueprint existe sans référence active ;
une référence active existe sans Blueprint ;
le depth ou le domain reçu ne correspond pas au tour courant ;
le système redémarre entre deux étapes atomiques.
10. Tests contractuels complets

Les tests doivent couvrir au minimum :

premier démarrage ;
rotation normale entre domaines ;
retour circulaire au premier domaine ;
domaine à cible atteinte ;
domaine vide avant la cible ;
fermeture normale du Depth ;
fermeture avec écart ;
passage 2 → 4 → 6 → 7 → 8 → 9 ;
fin après Depth 9 ;
réception ReadyBank avant Taxonomy ;
Taxonomy avant ReadyBank ;
signal dupliqué ;
redémarrage à chaque transition ;
absence de double Blueprint ;
absence de double comptabilisation ;
Quarantine non bloquante.
Définition du 100 %

Le KernelRotationPlanner sera architecturalement fonctionnel à 100 % lorsque cette boucle sera entièrement déterministe :

charger l’état
↓
recevoir les informations nécessaires
↓
calculer la prochaine position
↓
sélectionner un couple valide
↓
créer exactement un Blueprint
↓
attendre sa réception dans ReadyBank
↓
le comptabiliser exactement une fois
↓
actualiser l’état des réservoirs
↓
continuer le DomainCycle
ou fermer le Depth
↓
passer au prochain Depth
↓
terminer après Depth 9