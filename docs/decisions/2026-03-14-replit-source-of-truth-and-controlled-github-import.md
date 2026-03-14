# Decision — Replit remains source of truth; GitHub imports must be controlled

Date: 2026-03-14  
Project: StrategyBuzzer

Context:
A commit from the VM was pushed to GitHub (`1596094d`) containing economy ledger cleanup and documentation.

However, Replit already had many commits ahead on `main`, creating a divergence between Replit and GitHub.

A merge attempt in Replit created conflicts in critical files:
- AvatarController.php
- DuoMatchmakingService.php
- LeagueIndividualService.php
- QuestService.php
- config/coins.php

The merge was aborted to protect the integrity of the Replit codebase.

Decision:
Replit remains the main development environment and operational source of truth.

GitHub commits coming from the VM must not be blindly merged into Replit.

Instead:
- documentation can be imported directly
- code must be reviewed and ported manually if needed

Operational rule:
Avoid using:

git pull origin main
git merge origin/main

inside Replit unless a controlled merge strategy is prepared.

Goal:
Keep the StrategyBuzzer codebase stable while allowing VM-side documentation and audits to be tracked.
