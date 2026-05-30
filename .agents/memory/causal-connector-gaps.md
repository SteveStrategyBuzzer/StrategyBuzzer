---
name: hasCausalConnector gaps
description: Liste des connecteurs causaux manquants dans KernelTextHelpers qui causent cogIntegrity=0 sur des raisonnements valides.
---

**Rule:** KernelTextHelpers::hasCausalConnector() manque plusieurs marqueurs courants → faux négatifs cogIntegrity sur tf_reasoning_* et qcm_reasoning.

**Why:** La liste actuelle contient : because, since, therefore, thus, hence, as a result, leads to, results in, causes, due to, consequently, explains why, which means, means that. Manquent : "if", "given", "as ", "for ", "suggests", "indicates", "immediately", "leading to".

**How to apply:** Si cogIntegrity=0.5 sur un raisonnement qui semble valide, vérifier si le connecteur de la question est dans cette liste. Non urgent pour la génération (n'affecte que le scoring Phase 2), mais doit être corrigé avant calibration massive.
