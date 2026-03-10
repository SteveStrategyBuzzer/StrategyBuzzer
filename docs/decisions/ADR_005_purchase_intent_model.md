# ADR_005 - Purchase intent model

## Contexte
Le système Stripe actuel de StrategyBuzzer repose encore trop sur `Payment` et sur les metadata Stripe pour déterminer le fulfillment métier.

## Problème
Cette architecture n’est pas assez robuste pour une économie virtuelle mondiale, car elle mélange :
- paiement
- produit acheté
- quantité à livrer
- fulfillment
- auditabilité

## Décision
StrategyBuzzer doit introduire un modèle `purchase_intent` interne.

## Rôle du `purchase_intent`
Le `purchase_intent` doit figer avant la création de la session Stripe :
- l’utilisateur
- le produit
- le type de produit
- le type de monnaie interne
- la quantité à livrer
- le montant
- la devise
- la version du catalogue
- l’état de l’intention

## Conséquences
- Stripe ne sera plus la source de vérité métier pour la livraison.
- Les metadata Stripe ne serviront plus qu’à la corrélation technique.
- Le webhook devra relire l’intention interne avant fulfillment.
- `Payment` deviendra une trace de paiement et non plus l’unique pivot métier.


## Mise à jour 2026-03-09
Une première implémentation du modèle `purchase_intent` a été mise en place pour les packs de coins Stripe.

## Éléments déjà appliqués
- création de la table `purchase_intents`
- création du modèle `PurchaseIntent`
- création du `PurchaseIntent` avant Stripe checkout
- liaison de la session Stripe au `PurchaseIntent`
- lecture du `PurchaseIntent` dans le webhook avant fulfillment

## Conséquence
Le flux Stripe coins est maintenant partiellement réaligné vers une source de vérité interne.


## Mise à jour 2026-03-09 - Extension aux modes
Le modèle `purchase_intent` n'est plus limité aux packs de coins.
Il est maintenant aussi utilisé pour les déblocages de modes payants (`master_mode`, `duo_mode`, `league_mode`).


## Mise à jour 2026-03-09 - Extension aux modes
Le modèle `purchase_intent` n'est plus limité aux packs de coins.
Il est maintenant aussi utilisé pour les déblocages de modes payants (`master_mode`, `duo_mode`, `league_mode`).

