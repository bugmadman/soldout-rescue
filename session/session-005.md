# Session 005

Date: 2026-11-16 (CET). Phase 2, money model, tasks 2.1–2.3. About 33 minutes of active time (the owner's estimate was 30–35; start and end not recorded).

Conventions: as in session 001. Assistant replies are given in full but condensed in wording.

---

## 1. Owner

While there are no answers yet, which phase can be taken on?

**Assistant:** Phase 2, the money model. It needs no answers: the rep-hour rate stays a named parameter with a default, and only 2.6 waits for Priya. Phases 5.1–5.3 and 6.1–6.2 (Python) are independent too. Not yet: 0.4/0.5 (the owner sends the first messages), 4.2, 5.4/5.6, 6.4.

## 2. Owner

Start phase 2.

**Assistant:** Task 2.1: six invented fixture CSVs in `tests/Fixtures/data-pack/` (a `reps.csv` was added to test the non-rep `owner_id` trap). `make lint` green. Not committed until checked.

## 3. Owner

Commit 2.2, start 2.3 (after "Commit 2.1, start 2.2" earlier).

**Assistant:** 2.1 committed (`6c79674`). 2.2: the `developer` agent wrote the `Money` VO and `RoundingMode`; the `reviewer` agent found overflow and factor-precision problems in `multiplyBy` and some test gaps; fixed (factor limited to 9 digits, boundary tests). Committed (`35265f1`). Questions from the owner: typed class constants need PHP 8.3 (the project runs 8.5, no issue); why decimal strings and integer ratios instead of "multiply by 100" (floats break money, `1.005 * 100` gives 100.49999999999999).

## 4. Owner

Review findings and open questions on 2.3.

**Assistant:** 2.3: `app:estimate-history` and the estimator, written by `developer`, reviewed by `reviewer`. Fixes: "called" now means the first call is on or before the restock (749 sold-outs moved), an `ActivityType` enum, atomic output write, reader validation, 120-column lines, more tests. GP difference per call (pence, all cut): A 496, B 208, C 30, D 83, against Priya's 1100. The estimates are observational and biased; written to `NOTES.md`. Two review findings were deliberately left unfixed (collection classes, Domain/Infrastructure layering). The owner asked why the rules were not followed: the rules exist, the agent did not apply them and the brief to the agent did not name the collection rule; the second brief also told the agent not to restructure, which was a time decision taken without asking.

## 5. Owner

Record that the code was not fully reviewed by a human because of the time limit; collections and layering are not fixed for the same reason (tech debt); record the session and the time (about 30–35 minutes).

**Assistant:** README: new "Review status" and "Tech debt" sections and the time row (33 minutes, total 112 of 180, 68 remaining). `NOTES.md`: the decision moved to the cuts section, time updated. `PLAN.md` budget line updated. 2.3 is not committed yet.
