---
name: player_kernel_cognitive_usage table design
description: Décisions durables sur la table de mémoire gameplay (consommation cognitive par joueur)
---

# player_kernel_cognitive_usage — décisions de design (verrouillées 2026-06-20)

Table = **mémoire gameplay DURABLE par joueur**, PAS un journal d'exposition par match.
1 ligne = ce joueur a vu 1 cognitif (famille+forme) d'un noyau.

## Règles verrouillées
- **UNIQUE = (user_id, kernel_code, cognitive_family, cognitive_form)** — SANS match_ref.
  **Why:** mémoire durable, pas par match ; une ré-exposition ne crée pas de doublon (idempotence write S1 via updateOrCreate sur ces 4 colonnes). cognitive_family DANS la clé car cognitive_form seul est ambigu (qcm peut être recognition OU reasoning → doivent coexister).
- **kernel_code string(128) NOT NULL** = identité métier/pédagogique lisible (ex. `D04-GEO-MON-EVE-MOR-00`), source de vérité gameplay.
- **question_intent_id nullable, SANS FK** = simple référence technique. **Why:** l'historique du joueur survit si le noyau est modifié/nettoyé/archivé.
- **match_ref string(128) NOT NULL** = colonne INFORMATIVE = match/session de la **première** consommation (jamais une clé d'unicité).
- Seule FK = user_id → users.id cascadeOnDelete (convention player_question_history).
- Ligne créée **seulement après exposition complète** : question + bonne réponse + SV.

## États dérivés (jamais stockés)
Calculés par joueur à partir des lignes : 0 ligne pour un kernel_code = VIERGE ; ≥1 ligne = TOUCHÉ ; recognition + reasoning + deceptive_trap présents = BACK_SUPPORT.

## Invariants
- READY_BANK inchangé ; le noyau reste disponible globalement pour les autres joueurs.
- Compat cross-DB : que des types Blueprint standards + unique simple + index standards → OK Postgres ET SQLite (tests in-memory).
- Index : pkcu_user_kernel_idx (user_id, kernel_code) ; pkcu_user_depth_domain_kernel_idx (user_id, depth, domain, kernel_code).
