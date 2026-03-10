# SECURITY_MODEL

## Objectif
Définir le modèle de sécurité de StrategyBuzzer pour les paiements, la monnaie virtuelle, les comptes joueurs et les flux critiques.

## Axes de sécurité connus
- vérification de signature Stripe
- idempotence des événements Stripe
- devise calculée côté serveur
- protection contre le double crédit
- protection contre l’abus multi-comptes
- réduction de la fraude paiement
- future vérification téléphone par niveau de risque

## Principes retenus à ce stade
- ne jamais faire confiance au client pour la devise ou le montant
- ne jamais créditer depuis la page de succès Stripe
- le webhook Stripe est le point central du fulfillment
- toute monnaie interne doit être traçable
- l’anti-fraude doit reposer sur plusieurs signaux, pas un seul

## Signaux antifraude à intégrer
- `ip_country`
- `account_country`
- `card.country`
- vitesse d’achats
- multi-comptes par IP / appareil
- type de carte
- historique remboursements / litiges

## À compléter
- modèle de vérification téléphone
- politique de blocage par score de risque
- gestion des disputes et chargebacks

## Mise à jour 2026-03-09 - Protection anti-double-crédit Stripe
Le webhook Stripe est maintenant protégé à deux niveaux :
- niveau Stripe : déduplication par `event_id`
- niveau métier : blocage si `PurchaseIntent` est déjà `fulfilled`

Cette double protection réduit fortement le risque de double crédit en cas de replay, retry ou divergence future de traitement.

