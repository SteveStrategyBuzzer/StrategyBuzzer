# ADR_001 - Currency model

## Contexte
StrategyBuzzer doit afficher et facturer dans plusieurs devises tout en gardant une logique économique stable.

## Décision
- La devise de base économique est `CAD`.
- Les prix de base sont stockés en CAD cents.
- Les devises `CAD`, `USD`, `EUR`, `GBP` restent nominales 1:1.
- Les devises plus faibles que CAD sont ajustées à la hausse via FX.
- Les devises plus fortes que CAD ne doivent jamais réduire le prix sous le nominal.

## Règle complémentaire
La devise ne doit pas dépendre uniquement de l’IP.
Le GeoIP est un signal d’initialisation, pas une vérité commerciale absolue.

## Conséquences
- la conversion doit rester centralisée
- la résolution de devise devra évoluer vers une hiérarchie basée sur le compte joueur
