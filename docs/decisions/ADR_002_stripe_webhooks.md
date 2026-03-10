# ADR_002 - Stripe webhooks

## Contexte
StrategyBuzzer utilise Stripe Checkout pour vendre des pièces et des déblocages.

## Décision
- Le webhook Stripe est le point central de confirmation paiement.
- La page de succès Stripe ne crédite jamais directement le wallet.
- Les événements Stripe doivent être persistés.
- L’idempotence doit être assurée.

## Mise à jour
Les metadata Stripe peuvent contenir des informations de corrélation (`purchase_intent_id`, `user_id`, `product_key`), mais elles ne doivent jamais être la source de vérité métier pour la quantité à livrer.

## Règle
Le webhook Stripe ne crédite jamais à partir de `metadata.coins` comme vérité principale.
Il doit relire l’intention d’achat interne et valider montant, devise, produit et utilisateur avant fulfillment.

## Conséquences
- le modèle futur doit introduire une intention d’achat interne
- le fulfillment doit être strictement revalidé

## Mise à jour 2026-03-09 - Verrou métier complémentaire
En plus de la déduplication des événements Stripe, le webhook doit bloquer tout fulfillment si `PurchaseIntent` est déjà marqué `fulfilled`.

## Conséquence
La déduplication technique Stripe n'est pas considérée comme suffisante à elle seule.
Le verrou métier sur l'intention d'achat fait partie de l'architecture obligatoire.

