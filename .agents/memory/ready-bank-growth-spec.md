---
name: Ready_Bank growth specification — kernels per Depth
description: Official spec for Ready_Bank scaling. Unit = kernel (7 questions). Targets per Depth, production priority, phased growth.
---

# Ready_Bank Growth — Official Specification

## The unit of growth is the KERNEL (noyau), not the individual question

1 noyau = 1 sujet + 1 idée dominante + 7 questions cognitives:
  QCM Recognition, QCM Reasoning, QCM DeceptiveTrap,
  TF Recognition True, TF Recognition False, TF Reasoning True, TF Reasoning False

A typical match = 3 rounds × 20 questions = 60 questions → the bank must be reasoned
in "playable kernels" not raw question count.

## Anti-redundancy keys (what to track per player)
  - question_id
  - noyau_id
  - KEY_LEARNING_DIRECTION
  - DECEPTIVETRAP_SIGNATURE
  - LEARNING_SIGNATURE

DeceptiveTrap exception: may reuse the same subject under a different deceptive angle.
Conditions: never same cognitive immediately, never simple reformulation, real angle change,
different reconstruction, different DECEPTIVETRAP_SIGNATURE.

## Depth → game mode mapping + kernel targets

| Depth | Game modes                                               | Kernel target | Questions |
|-------|----------------------------------------------------------|---------------|-----------|
| 2     | Solo 1-9, Ligue Bronze                                   | 40 000        | 280 000   |
| 4     | Solo 11-29, Duo Novice, MJ Auto Novice                   | 120 000       | 840 000   |
| 6     | Solo 31-59, Boss 10/20/30, Duo Intermédiaire, MJ Auto    | 180 000       | 1 260 000 |
| 7     | Solo 61-79, Ligue Platine                                | 90 000        | 630 000   |
| 8     | Solo 81-89, Boss 40/50/60, Duo Expert, MJ Auto Expert    | 140 000       | 980 000   |
| 9     | Solo 91-99, Boss 70/80/90, Ligue Légende                 | 90 000        | 630 000   |
| 10    | Boss 100 / Cerveau Ultime / prestige                     | 25 000        | 175 000   |

**Total: 685 000 kernels × 7 = 4 795 000 questions**

## Production priority
1. Depth 6  (Solo actif + Duo + MJ intermédiaire — highest traffic)
2. Depth 4  (Duo Novice + MJ Novice)
3. Depth 8  (Duo Expert + Boss 40-60)
4. Depth 7  (Solo 61-79 + Ligue Platine)
5. Depth 9  (Boss 70-90 + Ligue Légende)
6. Depth 2  (Solo early + Ligue Bronze)
7. Depth 10 (Boss 100 prestige)

## Phased rollout (from spec)
1. Minimum opérationnel
2. Bêta fermée
3. Bêta publique
4. Lancement
5. Montée internationale

Kernel access rule: only VALIDATED_OK kernels are accessible for gameplay.

**Why:** Official architectural spec from user (2026-06-05). Defines the scaling model
for the international 1M-player target.
