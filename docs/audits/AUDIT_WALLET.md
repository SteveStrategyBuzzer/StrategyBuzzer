# AUDIT_WALLET

## État actuel connu
- Le projet possède au moins deux types de monnaie interne :
  - pièces d’intelligence
  - pièces de compétence
- Les achats en compétence existent déjà côté boutique.
- Les achats Stripe de pièces d’intelligence passent par un ledger.
- Les crédits Stripe de pièces de compétence ne montrent pas encore un ledger équivalent dans l’audit actuel.

## Risques identifiés
- asymétrie de traçabilité entre monnaies internes
- difficulté de réconciliation
- difficulté d’audit support / anti-fraude
- risque de mutations directes de solde sans source comptable homogène

## Cible recommandée
Mettre en place un modèle ledger cohérent pour toute monnaie interne :
- achat
- récompense de partie
- bonus avatar
- remboursement
- pénalité
- ajustement administrateur

## Audit incomplet tant que non vus
- `app/Services/CoinLedgerService.php`
- modèle(s) ledger exact(s)
- modèle `Payment`
- logique complète des wallets

## Mise à jour après audit de `CoinLedgerService`
- `CoinLedgerService` sait déjà créditer et débiter les pièces d’intelligence et de compétence.
- Cela confirme qu’une base de service wallet existe déjà.
- Le problème actuel n’est donc pas l’absence de service ledger, mais son utilisation incomplète dans les flux Stripe.

## Risque confirmé
Le webhook Stripe traite encore les pièces de compétence sans passer par une écriture ledger homogène, alors que le service existe déjà.

## Correction prioritaire
Tous les crédits et débits de monnaie interne liés à Stripe doivent passer par le même service ledger avec une distinction explicite du type de monnaie.


## Mise à jour 2026-03-09 - Homogénéisation du ledger boutique
- `BoutiqueController` n'écrit plus directement dans `CoinLedger` pour les achats en pièces de compétence.
- Les débits de boutique passent désormais par `CoinLedgerService`.
- Cela réduit les écritures dispersées et améliore la cohérence du wallet.


## Mise à jour 2026-03-10 - Typage du ledger
- La table `coin_ledger` contient maintenant une colonne `coin_type`.
- `CoinLedgerService` écrit désormais explicitement `coin_type`.
- Un test manuel a confirmé l’écriture correcte de `coin_type = competence`.

