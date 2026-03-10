# TECHNICAL_AUDIT

## Objectif
Ce document est la mémoire technique vivante de StrategyBuzzer.
Il sert de source de vérité pour éviter les audits répétés, conserver les décisions techniques et donner un point d’entrée unique à tout nouvel audit.

## Règle de travail
Avant toute nouvelle analyse importante, lire en priorité :
- `docs/architecture/TECHNICAL_AUDIT.md`
- `docs/architecture/SYSTEM_ARCHITECTURE.md`
- `docs/architecture/PAYMENT_ARCHITECTURE.md`
- `docs/architecture/SECURITY_MODEL.md`
- `docs/audits/AUDIT_STRIPE.md`
- `docs/audits/AUDIT_CURRENCY.md`
- `docs/audits/AUDIT_WALLET.md`
- `docs/decisions/ADR_001_currency_model.md`
- `docs/decisions/ADR_002_stripe_webhooks.md`
- `docs/decisions/ADR_003_wallet_ledger.md`
- `docs/decisions/ADR_004_payment_risk_engine.md`

## État actuel du projet
- Projet : StrategyBuzzer
- Type : jeu de trivia multijoueur en ligne
- Stack principale : Laravel, Blade, Stripe, Gemini AI, Socket.io / Node.js, Firestore, Google Cloud VM
- Modes : Solo, Duo, Ligue, Maître du jeu
- Chemin principal : `/home/stevegroupe/StrategyBuzzer`

## Zones critiques actuelles
- Stripe
- multi-devise
- économie interne
- sécurité
- anti-fraude
- scalabilité mondiale

## Audits en cours / récents
- Audit Stripe : commencé et documenté
- Audit devise : commencé partiellement à partir de `Currency.php`, `CurrencyResolver.php`, `DetectCurrency.php`, `config/currency.php`
- Audit wallet : à compléter

## Règle documentaire
Toute décision importante concernant Stripe, devise, wallet, sécurité, anti-fraude ou architecture temps réel doit être ajoutée dans `docs/decisions`, `docs/audits` ou `docs/architecture` selon sa nature.
