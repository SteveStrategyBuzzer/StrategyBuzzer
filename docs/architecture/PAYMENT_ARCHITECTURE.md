# PAYMENT_ARCHITECTURE

## Objectif
Assurer un flux achat → paiement → webhook → attribution fiable, auditable, idempotent et compatible avec une montée en charge mondiale.

## Flux actuel observé
1. L’utilisateur choisit une offre.
2. Le serveur résout la devise depuis la session.
3. Le prix de base CAD cents est converti.
4. Une Checkout Session Stripe est créée.
5. Un enregistrement `Payment` local est créé avec statut `pending`.
6. Stripe envoie `checkout.session.completed`.
7. Le webhook vérifie la signature et enregistre l’événement.
8. Le système retrouve le `Payment` via `stripe_session_id`.
9. Le système crédite ou débloque l’achat.
10. Le `Payment` passe à `completed`.

## Problème actuel identifié
Le flux actuel s’appuie encore partiellement sur les metadata Stripe pour le fulfillment métier.

## Risques confirmés
- dépendance des metadata Stripe pour déterminer le fulfillment
- absence visible de revalidation stricte `amount_total`, `currency` et `product_key` contre `Payment`
- pas de ledger homogène pour toutes les monnaies internes
- pas de gestion visible des refunds, disputes et chargebacks
- `Payment` joue encore plusieurs rôles à la fois

## Correction cible
1. Création d’un `purchase_intent`
2. Envoi d’un `purchase_intent_id` à Stripe
3. Webhook Stripe validé
4. Lecture de l’intention d’achat interne
5. Revalidation stricte du montant, de la devise, du produit et de l’utilisateur
6. Fulfillment transactionnel
7. Écriture ledger
8. Gestion refunds / disputes / chargebacks

## Règle d’architecture
Stripe confirme le paiement.
La source de vérité pour la livraison doit être interne au projet.

## État détaillé confirmé par audit
- `Payment` est actuellement la trace locale principale du paiement Stripe.
- `CoinLedgerService` existe déjà et supporte `intelligence` et `competence`.
- Le webhook ne l’utilise pas encore de manière homogène pour tous les types de monnaie.
- Le catalogue des packs est actuellement défini dans `config/coins.php`.
- Le système possède maintenant une première implémentation de `purchase_intent` pour le flux Stripe des packs de coins.
- Le webhook lit encore des metadata Stripe pour décider du fulfillment.

## Cible d’architecture affinée
Le modèle cible doit distinguer explicitement :
1. `purchase_intent`
2. `payment_attempt`
3. `stripe_webhook_event`
4. `fulfillment`
5. `wallet_ledger_entry`
6. `payment_risk_evaluation`

## Règle cible
Le `Payment` ne doit plus être l’unique pivot de vérité métier.
Il doit devenir une partie d’un flux paiement plus riche et mieux séparé.


## Mise à jour 2026-03-09 - Implémentation initiale du purchase intent
- La table `purchase_intents` a été créée.
- Le modèle `PurchaseIntent` a été ajouté.
- `CoinsController` crée maintenant un `PurchaseIntent` avant la création de la session Stripe.
- `StripeService` transmet désormais `purchase_intent_id` via `client_reference_id` et metadata.
- `StripeWebhookController` relit maintenant `PurchaseIntent` avant fulfillment.
- Le webhook valide désormais explicitement le montant, la devise, l’utilisateur et le produit contre `Payment`.
- Le crédit Stripe des pièces passe désormais par `CoinLedgerService` avec le `coin_type` issu du `PurchaseIntent`.

## État actuel
Le système a quitté le modèle purement basé sur les metadata Stripe pour entrer dans un modèle basé sur une intention d’achat interne.


## Mise à jour 2026-03-09 - Extension du purchase intent à la boutique
- `BoutiqueController` crée maintenant aussi des `PurchaseIntent` pour `master_mode`, `duo_mode` et `league_mode`.
- Les déblocages Stripe de modes utilisent désormais le même modèle de corrélation interne que les packs de coins.
- Le flux paiement devient plus homogène entre packs de coins et déblocages de modes.
