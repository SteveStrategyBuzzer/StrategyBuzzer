# SYSTEM_ARCHITECTURE

## Vue d’ensemble
StrategyBuzzer est un jeu de trivia multijoueur avec économie interne, achats, déblocages et modes de jeu multiples.

## Stack actuelle connue
- Laravel : backend principal
- Blade : interface web actuelle
- Stripe : paiements
- Node.js / Socket.io : temps réel
- Firestore : certaines données de jeu
- Google Cloud VM : production

## Grands domaines fonctionnels
- Game
- Payments
- Wallet / économie interne
- Identity / sécurité
- Matchmaking / temps réel
- Quests / progression
- Avatars / boutique

## Direction d’architecture recommandée
À terme, organiser progressivement le code par domaines métier :
- `app/Domain/Game`
- `app/Domain/Payments`
- `app/Domain/Wallet`
- `app/Domain/Identity`

## Remarque
Ce document décrit la cible système et doit être enrichi au fur et à mesure des audits et des décisions validées.
