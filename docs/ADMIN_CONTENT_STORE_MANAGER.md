# Admin Content & Store Manager — Spécification Produit

**Statut** : Documenté — non implémenté  
**Priorité** : Module futur central  
**Date de rédaction** : 2026-05-12  
**Auteur** : Product / Architecture

---

## Vision

Interface d'administration StrategyBuzzer style "Spotify for Artists / dashboard créateur".

Un panneau unique où l'administrateur peut **voir, modifier, ajouter et publier** tous les éléments visibles par les joueurs — sans toucher au code.

Objectif fondamental : **zéro modification de code** pour :
- ajouter un son
- créer un avatar ou un pack
- définir une quête
- publier un item boutique
- changer un prix
- activer une ambiance
- ajouter un domaine de questions

---

## Architecture Cible

### Principes

- **Laravel admin dashboard** — routes dédiées préfixées `/admin/`
- **Données en DB** — aucune valeur hardcodée dans les vues Blade
- **Pages joueur DB-driven** — boutique, profil, avatars, sons, ambiances, quêtes lisent exclusivement les items actifs depuis la DB
- **Toggle actif/inactif** — sans modification de code
- **Accès sécurisé** — rôles et permissions (ex : `role:admin`, `role:content_manager`)
- **Journal des modifications** — chaque action admin horodatée avec l'auteur

### Stack

```
Laravel 10 (backend + Blade admin)
├── Middleware AdminAuth (shared secret ou rôle DB)
├── AdminActionLog (table + trait)
├── Controllers Admin (un par section)
├── DB tables (une par catégorie de contenu)
└── API internes pour preview joueur
```

---

## Sections du Dashboard

### 1. Boutique

**Contenu gérable :**
- Items boutique (nom, description, prix en Pièces de Compétence / Intelligence, image)
- Activation / désactivation d'un item sans suppression
- Catégories (avatars, sons, ambiances, packs premium, items débloqués)
- Ordre d'affichage par catégorie
- Badge "Nouveau" / "Promo" / "Limité"

**Tables DB concernées :**
- `boutique_items` (id, slug, category, name_json, description_json, price_competence, price_intelligence, image_url, active, sort_order, badge, created_at, updated_at)

**Impact joueur :** page Boutique relit `boutique_items WHERE active=true`

---

### 2. Avatars & Packs

**Contenu gérable :**
- Packs d'avatars (nom, image couverture, prix, tier : Débutant / Confirmé / Expert)
- Avatars individuels dans chaque pack (nom, image, tier, compétences associées)
- Créer un nouveau pack (formulaire : nom, images, tier, compétences)
- Activer / désactiver un pack ou un avatar individuel
- Aperçu rendu de l'avatar avant publication

**Tables DB :**
- `avatar_packs` (id, slug, name_json, cover_image, tier, price, active, sort_order)
- `avatars` (id, pack_id, slug, name_json, image_url, tier, active, sort_order)
- `avatar_skills` (id, avatar_id, skill_code, params_json) — lié à `AvatarSkillService`

**Impact joueur :** sélecteur d'avatar, boutique, profil

---

### 3. Sons

**Sous-sections :**

#### 3a. Sons bonne / mauvaise réponse
- Upload ou URL du fichier audio
- Nom, catégorie (correct / incorrect), aperçu lecture
- Activation / désactivation
- Pack d'appartenance (pour vente groupée)

#### 3b. Sons buzzer
- Sons de buzz au moment de la réponse
- Idem : upload, aperçu, activation

#### 3c. Musique d'ambiance
- Musique des menus, lobbies, écrans de résultat
- Contexte d'utilisation (menu principal, lobby, résultat)
- Loop / fade-in / volume par défaut

#### 3d. Musique gameplay
- Musique pendant une question
- Paramètres : tempo, intensity level suggéré

**Tables DB :**
- `sound_items` (id, slug, type ENUM[correct,incorrect,buzzer,ambiance,gameplay], name_json, file_url, context, active, pack_id, volume_default, loop)

**Impact joueur :** sons sélectionnables dans le profil / boutique, lecture en jeu

---

### 4. Ambiances Visuelles

**Contenu gérable :**
- Thèmes visuels (fond, couleurs, particules, animation d'entrée)
- Aperçu rendu dans un iframe "vue joueur"
- Prix, activation, pack d'appartenance

**Tables DB :**
- `visual_themes` (id, slug, name_json, preview_url, css_vars_json, active, price, pack_id)

**Impact joueur :** sélecteur d'ambiance dans le profil / boutique, rendu en jeu

---

### 5. Quêtes

#### 5a. Quêtes permanentes
- Titre, description, objectif (ex : gagner 10 parties), récompense
- Activation / désactivation par quête
- Ordre d'affichage

#### 5b. Quêtes quotidiennes
- Pool de quêtes quotidiennes (la DB en pioche N aléatoirement chaque jour)
- Fréquence de rotation, nombre actif par jour
- Récompense (type + quantité)
- Conditions d'éligibilité (mode de jeu, niveau requis)

**Tables DB :**
- `quests` (id, slug, type ENUM[permanent,daily], title_json, description_json, objective_type, objective_target, reward_type, reward_amount, coin_type, active, eligible_modes_json, min_level)

**Impact joueur :** page Quêtes, DailyQuestService, récompenses

---

### 6. Packs Premium & Prix

**Contenu gérable :**
- Packs Stripe (nom, description, contenu, prix multi-devises)
- Prix par devise (EUR, USD, GBP, CAD, MAD, …) via `CurrencyDetectionService`
- Activation / désactivation d'un pack
- Période de promotion avec prix temporaire

**Tables DB :**
- `premium_packs` (id, slug, name_json, description_json, stripe_price_id_json, active, promo_price_json, promo_until)

**Impact joueur :** boutique premium, Stripe Checkout

---

### 7. Items Débloqués & Contenu Actif/Inactif

**Vue transversale :**
- Liste de tous les items (toutes catégories confondues) avec leur statut actif/inactif
- Filtre par catégorie, statut, date de création
- Toggle rapide sans ouvrir la fiche
- Bulk activation / désactivation

---

### 8. Aperçu Joueur avant Publication

**Fonctionnalité clé :**
- Iframe "vue joueur" simulant l'affichage d'un item (avatar, son, ambiance, quête, boutique)
- Bouton "Prévisualiser" sur chaque fiche avant publication
- État `draft` → `published` en deux étapes

**Architecture :**
- Items en DB avec champ `status ENUM[draft, active, inactive]`
- L'iframe preview charge une route `/admin/preview/{type}/{id}` avec `?admin_token=`

---

### 9. Question Bank — Supervision

**Vue de la progression du remplissage de la banque :**

#### 9a. Vue Buckets
- État de remplissage par bucket : `(mode × sub_domain × cognitive_type × depth × difficulty_level)`
- Barre de progression : `present / target_matches_per_profile`
- Couleur : rouge (0-30%), orange (30-70%), vert (70-100%)
- Filtre par mode (Solo / Duo / Boss / Ligue), sub_domain, langue, depth

#### 9b. Métriques Question Bank
| Métrique | Description |
|---|---|
| Nombre de blocs | Total `question_groups` par segment |
| Niveau des blocs | Distribution `difficulty_level` / `boss_level` |
| Profondeur | Distribution `difficulty_depth` (3-10) |
| Diversité | Nombre de `concept_family` distincts par segment |
| Domaine / sous-domaine | Répartition par `sub_domain` |
| Source | Gemini vs seed vs OpenAI |
| Validés / Non validés | `validated=true/false` |
| Dernière génération | Timestamp dernier insert par segment |

#### 9c. Worker Health
- Statut du worker (lock Redis actif ou non)
- Dernier succès (`last_success`)
- Derniers rejets (`last_rejects` — 25 entrées)
- Taux de rejet par code (`concept_family_share`, `missing_translations`, etc.)
- Rate limiter : budget utilisé dans la minute courante

**Tables lues :**
- `question_groups`, `question_translations`
- Redis : `qb:worker:*`

**Impact admin :** supervision opérationnelle, pas de modification directe des groupes (lecture seule dans un premier temps)

---

### 10. Accès aux Comptes Joueurs (Debug)

**Fonctionnalité :**
- Recherche par ID joueur, email, Player Code
- Vue profil complet : pièces, avatars, quêtes, historique matches
- Correction d'environnement : crédit / débit manuel de pièces (avec journal)
- Reset de quête quotidienne
- Affichage du journal `critical_actions_log` par joueur

**Tables lues / écrites :**
- `users`, `coin_ledger`, `critical_actions_log`, `match_snapshots`

**Sécurité :** actions irréversibles avec confirmation + double log (action admin + `critical_actions_log`)

---

## Sécurité & Accès

### Middleware

```php
// AdminAuth middleware
// Option A : shared secret (actuel, simple)
// Option B : colonne role sur users (scalable)
```

Recommandation évolution :
```
users.role ENUM[player, content_manager, admin, super_admin]
```

| Rôle | Accès |
|---|---|
| `content_manager` | Boutique, avatars, sons, ambiances, quêtes (pas debug joueurs) |
| `admin` | Tout sauf super_admin actions |
| `super_admin` | Tout + suppression, config système |

### Journal des modifications

**Table** : `admin_action_log`
```sql
id, admin_user_id, action_type, target_type, target_id, 
before_json, after_json, ip_address, created_at
```

Chaque modification d'item déclenche un insert dans `admin_action_log` via trait `LogsAdminAction` (pattern identique à `LogsCriticalAction`).

---

## Plan de Migration DB (futur)

Les tables actuelles ont des valeurs partiellement hardcodées dans le code PHP/Blade. La migration safe sera :

1. **Créer les nouvelles tables** (`boutique_items`, `avatar_packs`, `avatars`, `sound_items`, `visual_themes`, `quests`, `premium_packs`)
2. **Seeder de migration** — peupler depuis les valeurs actuellement hardcodées
3. **Switcher les Blade/controllers** pour lire depuis DB
4. **Activer l'admin dashboard** — les valeurs sont maintenant en DB
5. **Supprimer les hardcodes** — nettoyage du code

Aucune donnée joueur affectée. Rétrocompatibilité garantie par les seeders.

---

## Implémentation Ordre Suggéré (quand on démarrera)

| Phase | Scope | Effort estimé |
|---|---|---|
| Phase 1 | Question Bank supervision (lecture seule) | Faible — données déjà en DB/Redis |
| Phase 2 | Accès comptes joueurs (debug) | Moyen — lecture + actions simples |
| Phase 3 | Boutique & Prix (CRUD) | Moyen — migration tables |
| Phase 4 | Avatars & Packs (CRUD + upload) | Élevé — gestion fichiers |
| Phase 5 | Sons & Ambiances (CRUD + upload + preview) | Élevé |
| Phase 6 | Quêtes (CRUD + rotation daily) | Moyen |
| Phase 7 | Preview joueur (iframe draft) | Faible si architecture DB est en place |
| Phase 8 | Rôles & permissions granulaires | Moyen |

**Phase 1 peut démarrer immédiatement** — toutes les données Question Bank sont déjà en DB et Redis, aucune migration requise.

---

## Ce que ce module remplace

| Aujourd'hui | Après Admin Manager |
|---|---|
| Modifier un prix → PR + deploy | Modifier un prix → clic admin |
| Ajouter un avatar → code Blade + asset | Créer un avatar → formulaire admin |
| Activer une quête → config PHP | Activer une quête → toggle |
| Déboguer un joueur → accès DB direct | Rechercher joueur → interface admin |
| Voir l'état de la banque → redis-cli + psql | Dashboard Question Bank visuel |

---

*Ce document est la référence de spécification. Il sera mis à jour au démarrage de l'implémentation.*
