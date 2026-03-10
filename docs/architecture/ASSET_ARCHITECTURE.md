# ASSET_ARCHITECTURE

## Principe
Le code et les assets lourds ne doivent plus vivre dans la même logique de versionnement.

## Structure retenue
- Code applicatif: `/home/stevegroupe/StrategyBuzzer`
- Assets hors Git: `/home/stevegroupe/strategybuzzer_assets`
- Backups assets: `/home/stevegroupe/strategybuzzer_backups/assets`
- Archives manuelles: `/home/stevegroupe/strategybuzzer_archives`

## Types d'assets concernés
- avatars
- backgrounds
- generated_images
- sounds
- exports lourds

## Règle
Les assets lourds ne doivent plus être utilisés comme mémoire Git principale.
Git garde le code. Les assets sont sauvegardés séparément.
