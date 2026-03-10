# ADR_003 - Wallet ledger

## Contexte
StrategyBuzzer possède une économie interne avec plusieurs types de monnaies et des achats Stripe.

## Décision
Toute monnaie interne importante doit être traçable dans un ledger cohérent.

## Règle
Un simple `coins += X` sans trace comptable n’est pas une architecture suffisante pour un jeu mondial avec achats.

## Conséquences
- les achats Stripe
- les récompenses de jeu
- les bonus avatar
- les remboursements
- les pénalités
doivent être représentables dans une structure comptable claire.

## Mise à jour
`CoinLedgerService` existe déjà et supporte les crédits/débits pour `intelligence` et `competence`.

## Décision complémentaire
Tout flux Stripe touchant la monnaie interne doit utiliser le service ledger de manière homogène.

## Règle
Aucune monnaie interne liée à Stripe ne doit être créditée directement hors du service ledger.


## Mise à jour 2026-03-09 - Boutique
Les achats boutique en pièces de compétence doivent passer par `CoinLedgerService` et non par des écritures directes dans `CoinLedger`.


## Mise à jour 2026-03-10 - Typage explicite
Le ledger doit distinguer explicitement les monnaies internes via `coin_type`.
Les écritures `intelligence` et `competence` ne doivent plus être mélangées sans typage.

