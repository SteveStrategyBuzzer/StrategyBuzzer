---
name: TAXONOMY_LEARNING_PROFILE
description: Profil d'apprentissage par (domaine/sous-domaine/depth) qui permet à Taxonomy d'apprendre des recadrages KEY_STRUCTURE et d'ajuster sa génération future.
---

# TAXONOMY_LEARNING_PROFILE (VALIDÉ)

Rôle : permettre à Taxonomy d'apprendre des recadrages émis par KEY_STRUCTURE et d'éviter de répéter la même dérive sur un Sous-domaine déjà connu.

## Structure
```json
{
  "domain": "Géographie",
  "sub_domain": "Capitales",
  "depth": 4,
  "recadrage_history": [
    { "reason": "SUBJECTS_TOO_GENERIC", "action": "INCREASE_SPECIALIZATION", "count": 3 },
    { "reason": "DOMINANT_IDEAS_TOO_CLOSE_TO_SUBJECT", "action": "INCREASE_GRAINING_DISTANCE", "count": 2 }
  ],
  "last_taxonomy_instruction": "INCREASE_SPECIALIZATION"
}
```
Clé d'identité : (domain + sub_domain + depth). L'historique s'accumule par cette clé.

## Usage
Avant de (re)produire un Sous-domaine déjà connu, Taxonomy lit son profil et ajuste la génération.
Ex. historique SUBJECTS_TOO_GENERIC x3 → éviter sujets trop larges, favoriser des entités précises, forcer des sujets instances du sous-domaine.

## Actions possibles (vocabulaire taxonomy_action)
- INCREASE_SPECIALIZATION → produire des Sujets plus précis
- WIDEN_SUBDOMAIN → Sous-domaine trop étroit, élargir légèrement
- INCREASE_GRAINING_DISTANCE → Idées Dominantes trop proches du Sujet
- ENFORCE_MINIMAL_FORMAT → réduire les phrases, garder axes courts
- RECENTER_SUBDOMAIN_CONSTRUCTION → Sous-domaine mal cadré (JAMAIS reconstruire)
- ALIGN_TO_DEPTH_EXPECTATION → égrainage pas assez profond pour le Depth (nom officiel UNIQUE, verrouillé ; ALIGN_TO_DEPTH_PROFILE supprimé)

**Why:** Le ruleset existe pour que Taxonomy ne reçoive jamais un simple FAIL mais un recadrage actionnable ré-exploitable dans le temps. Le profil est la mémoire longue de cette boucle KEY_STRUCTURE→Taxonomy.
