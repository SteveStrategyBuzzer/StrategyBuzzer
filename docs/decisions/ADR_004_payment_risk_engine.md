# ADR_004 - Payment risk engine

## Contexte
StrategyBuzzer vend de la monnaie virtuelle et des déblocages via Stripe. Le système doit limiter les multi-comptes abusifs, les paiements suspects, les usages VPN incohérents et les comportements à risque.

## Décision
Un moteur de risque paiement sera introduit.
Il utilisera plusieurs signaux, dont `card.country`, `ip_country`, `account_country`, le type de carte, la vélocité d’achat et les signaux multi-compte.

## Règles
- `card.country` est un signal antifraude fort.
- `card.country` ne sert pas à choisir la devise Stripe.
- La devise reste une décision commerciale et produit.
- Le score de risque peut déclencher une vérification téléphone sur certains scénarios.

## Conséquences
- les paiements ne seront plus évalués uniquement en mode binaire succès/échec
- un scoring de risque permettra d’ajouter des contrôles ciblés avec une friction limitée
