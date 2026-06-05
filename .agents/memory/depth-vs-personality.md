---
name: Depth vs Adversary Personality separation
description: Depth belongs to questions; Personality belongs to students/Bosses. Never conflate them.
---

# Depth ≠ Adversary Personality — Two Separate Systems

## The rule
Depth and adversary personality are independent and must NEVER be conflated.

**DEPTH** (belongs to questions):
- Defines cognitive complexity of a question
- Defines degree of knowledge required
- Influences: difficulty, vocabulary, reasoning, trap, SV content
- Stored on `question_groups.difficulty_depth` (integer 1–10)
- Determines WHICH questions are served to a player

**ADVERSARY PERSONALITY** (belongs to students and Boss Solo):
- Defines the opponent's behavior during gameplay
- Attributes: buzz %, buzz timing, abstention %, accuracy %, domain radar, play style
- Stored in boss_profiles / student_bands in `config/question_bank_profiles.php`
- Determines HOW the opponent plays

## Key example
Boss 50 / Champion → questions Depth 8
  - The Boss does NOT become Depth 8
  - Boss 50 is ASSOCIATED WITH questions of Depth 8
  - Champion personality (radar sport/cuisine, aggressiveness, confidence) is preserved
  - Depth 8 complexity applies to the questions served, not to the Boss itself

## How to apply
- When picking questions for a Boss or student: look up their depth assignment, fetch questions of that depth
- When describing an opponent's behavior: use personality attributes (buzz%, radar, etc.)
- Never say "Boss X is a Depth Y opponent" — say "Boss X plays with Depth Y questions"
- Mode mappings in config/question_bank_profiles.php link game levels to depths, not to personalities

**Why:** User explicitly corrected a conceptual conflation during SV quality implementation session (2026-06-05).
