# AUDIT_STRIPE

## État actuel observé
- La devise Stripe est résolue côté serveur via la session.
- La conversion monétaire est centralisée dans `App\Support\Currency`.
- Les Checkout Sessions Stripe créent un enregistrement `Payment` local avec statut `pending`.
- La page `success` ne crédite rien directement.
- Le webhook Stripe vérifie la signature.
- Les événements Stripe sont stockés dans `stripe_webhook_events`.
- Le traitement principal repose sur `checkout.session.completed`.
- Le crédit des pièces d’intelligence passe par un ledger.
- Le crédit des pièces de compétence modifie directement le solde sans ledger équivalent.
- `StripeService` crée les Checkout Sessions côté serveur.
- Les metadata Stripe contiennent actuellement `user_id`, `product_key`, `coins`, `coin_type`.
- Le webhook lit ces metadata pour décider quoi livrer.

## Fichiers réellement audités
- `app/Http/Controllers/BoutiqueController.php`
- `app/Http/Controllers/CoinsController.php`
- `app/Http/Controllers/StripeWebhookController.php`
- `app/Services/StripeService.php`
- `app/Support/Currency.php`
- `app/Services/CurrencyResolver.php`
- `app/Http/Middleware/DetectCurrency.php`
- `config/currency.php`
- `database/migrations/2026_03_01_010740_create_stripe_webhook_events_table.php`

## Points solides
- Devise non fournie par le client.
- Signature webhook vérifiée.
- Événements Stripe persistés.
- Idempotence initiale basée sur `event_id`.
- Crédit non déclenché par la page de succès.
- Montants Stripe construits côté serveur.

## Risques identifiés
- Le webhook se base sur les metadata Stripe pour déterminer le fulfillment.
- Aucune revalidation stricte visible de `amount_total`, `currency` et `product_key` contre `Payment`.
- Pas de ledger homogène pour tous les types de monnaie interne.
- Pas de gestion visible des refunds, disputes, chargebacks.
- `Payment` sert encore de pivot mixte paiement / ordre / fulfillment.
- Orphan webhooks ackés sans vraie réconciliation formelle.

## Risque confirmé
Le système actuel est vulnérable à une classe de dérive fréquente dans les jeux : dépendre des metadata Stripe pour déterminer le montant de monnaie virtuelle à livrer.

## Corrections prioritaires
1. Le webhook doit livrer à partir des données internes figées, pas des metadata Stripe comme source de vérité.
2. Ajouter la revalidation stricte montant/devise/produit avant tout crédit.
3. Uniformiser le ledger pour intelligence et compétence.
4. Introduire un modèle `purchase_intent`.
5. Gérer refunds, disputes et reversals.
6. Ajouter l’observabilité paiement.
7. Introduire un moteur de risque paiement.

## Audit incomplet tant que non vus
- `app/Models/Payment.php`
- `app/Services/CoinLedgerService.php`
- `config/coins.php`
- `routes/web.php`

## Mise à jour après audit de `Payment`, `CoinLedgerService`, `config/coins.php` et `routes/web.php`

### `app/Models/Payment.php`
- Le modèle `Payment` contient `user_id`, `stripe_session_id`, `stripe_payment_intent_id`, `product_key`, `amount_cents`, `currency`, `coins_awarded`, `status`, `metadata`, `processed_at`.
- `Payment` agit actuellement comme trace locale du paiement, mais reste trop limité pour représenter à lui seul une intention d’achat, un état de fulfillment et un état de risque.

### `app/Services/CoinLedgerService.php`
- `CoinLedgerService` sait créditer et débiter les pièces d’intelligence et de compétence.
- Le service existe déjà, mais le webhook Stripe ne l’utilise pas encore de manière homogène pour les pièces de compétence.
- Le ledger doit distinguer explicitement les types de monnaie interne si ce n’est pas déjà le cas au niveau table.

### `config/coins.php`
- Les packs Stripe sont centralisés dans `config/coins.php`.
- Les prix sont traités dans l’architecture actuelle comme des montants de base à convertir.
- Le champ `currency` présent dans les packs est ambigu dans le modèle actuel, car la devise finale est remplacée dynamiquement côté serveur.
- Les commentaires actuels peuvent prêter à confusion sur la devise de référence réelle.
- Le catalogue doit évoluer vers un modèle plus strict où le prix de base, le type de produit et la livraison attendue sont figés explicitement.

### `routes/web.php`
- Les routes Stripe checkout sont protégées par `auth`.
- La route webhook Stripe est dédiée et distincte.
- Le flux applicatif actuel est clair, mais il manque encore une couche de réconciliation, d’observabilité et de gestion des cas négatifs.

## Risques confirmés supplémentaires
- Le modèle `Payment` est encore un pivot mixte trop pauvre pour un système mondial.
- Le ledger n’est pas encore utilisé de façon homogène dans tous les flux Stripe.
- Le catalogue `config/coins.php` n’est pas encore un vrai catalogue versionné.
- La gestion des refunds, disputes et chargebacks n’est pas visible dans l’état audité.

## Conclusion d’audit Stripe
L’intégration actuelle est sérieuse pour une base de travail, mais elle doit évoluer vers :
1. une intention d’achat interne figée
2. une validation stricte du webhook
3. un ledger homogène
4. une gestion complète des événements négatifs Stripe
5. un moteur de risque paiement


## Mise à jour 2026-03-09 - Verrou anti-double-crédit métier
- Le webhook Stripe protège maintenant aussi le fulfillment métier via `PurchaseIntent`.
- Si `purchase_intent.status === fulfilled`, aucun nouveau crédit n'est exécuté.
- La protection anti-double-crédit repose désormais sur deux niveaux :
  1. déduplication Stripe par `event_id`
  2. verrou métier par statut `fulfilled` de `PurchaseIntent`

