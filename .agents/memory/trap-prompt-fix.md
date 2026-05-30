---
name: Trap prompt fix
description: Les 5 nouveaux champs cognitive_contract trap étaient dans DECEPTIVE_CONTRACT_FILL_KEYS PHP mais invisibles pour l'IA Node.
---

**Rule:** Quand on ajoute un champ dans KernelContentBuilder::DECEPTIVE_CONTRACT_FILL_KEYS (PHP), il faut obligatoirement l'ajouter aussi dans question-api.js à 3 endroits : instruction block, JSON schema template, et DECEPTIVE_CC_KEYS / validation.

**Why:** Le PHP fusionne avec `isset($cc[$key])` — si le champ est absent de la réponse IA, il reste null même si le skeleton l'a initialisé.

**How to apply:** À chaque ajout de champ trap en PHP, vérifier les 3 couches Node : ~ligne 1991 (instruction), ~ligne 2043 (schema), ~ligne 2135 (DECEPTIVE_CC_KEYS) + section validate() pour booleans/arrays.

Champs ajoutés (2026-05-29) : trap_carriers (array), implicit_hypothesis (str≥20), natural_hypothesis_triggered (bool), hypothesis_overturned_after_full_read (bool), reconstruction_required (str≥25).
