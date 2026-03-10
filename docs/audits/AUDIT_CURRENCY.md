# AUDIT_CURRENCY

## Fichiers réellement audités
- `app/Support/Currency.php`
- `app/Services/CurrencyResolver.php`
- `app/Http/Middleware/DetectCurrency.php`
- `config/currency.php`

## État actuel observé
- La devise par défaut est `USD`.
- La devise de base économique est `CAD`.
- Les devises nominales sont `CAD`, `USD`, `EUR`, `GBP`.
- Les autres devises sont converties via des taux FX manuels dans `config/currency.php`.
- La devise est déterminée côté serveur via GeoIP puis stockée en session.
- `DetectCurrency` ne met la devise en session que si elle n’existe pas déjà.
- `Currency::convertBaseCentsTo()` centralise la règle monétaire.

## Points solides
- La devise n’est pas acceptée depuis le client.
- La logique de conversion est centralisée.
- La règle “nominal pour CAD/USD/EUR/GBP” est codée clairement.
- La logique “ne jamais descendre sous le nominal pour une devise forte” est présente.

## Risques identifiés
- La devise dépend encore trop du GeoIP initial et de la session.
- Le GeoIP ne représente pas forcément l’identité commerciale réelle du joueur.
- Les taux FX sont manuels et non historisés.
- Il n’existe pas encore de hiérarchie complète de résolution basée sur préférence utilisateur / pays de compte / historique d’achat.

## Décision cible
La devise ne doit pas dépendre uniquement de l’IP.
Le GeoIP doit servir de signal initial.
La devise commerciale du joueur doit à terme être résolue avec une priorité du type :
1. devise préférée du compte
2. devise déjà validée / utilisée
3. pays de compte ou de facturation si connu
4. GeoIP
5. fallback système
