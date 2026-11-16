# Session 010

Date: 2026-11-16 (CET). The hand-in: the README caveats, the replies from Dan, Greg and Renee, the stop rule, then the notes, the time log and the commit.

Conventions: as in session 001. Replies are condensed.

---

## 1. Owner

Is the README all we have time for?

**Assistant:** Yes in practice: the time log showed 172 of 180 minutes used and 15 are reserved for the hand-in. Listed what the hand-in needs (README, the messages to Dan and Greg, the session export, the submission) and that everything unfinished goes into the cut list, not silently dropped.

## 2. Owner

Add the live URL (https://soldout.madbugs.dev) and the owner's caveats: little time to check the code, no CI/CD, endpoint mocks not as they should be, no users, no filters, many more ideas than the three hours allowed.

**Assistant:** Added a "Caveats" section and the URL to the README. When the owner said HTTPS is on, rewrote the Basic auth limit: the live site is HTTPS, a local run on plain HTTP sends the password in the clear.

## 3. Owner

Are all the other items in the README as not done?

**Assistant:** Not all. Added the whole text analysis (phase 4), 6.3 and the full recommendation rules (3.2) to the not-done paragraph. Found that 5.2 was done (commit `ff1a352`) but not ticked in `PLAN.md`; ticked it.

## 4. Owner

Draft the two messages: the one-page request for Greg and the three lines for Dan.

**Assistant:** Drafted both from `NOTES.md`. The request covers the change, the static list keyed by `deal_id`, the held-out share, risk, rollback, when to stop, the review (not claiming Hana had seen the diff) and Greg's call on timing. Dan's three lines say there is no yearly number yet and why the £11 does not extrapolate.

## 5. Owner

Dan asked what reps do with the freed time and whether it is worth more than the calls.

**Assistant:** From routing's README: the day is filled in expected-GP order and the rest carries over, so freed minutes go to the next queued tasks, mostly overdue ones. Not measured: the value of the marginal task. Drafted an honest answer; the owner sent it at 14:55 together with the three lines.

## 6. Owner

Renee named a merchant (Wren Brow & Lash, York, owned by Jas Mann).

**Assistant:** Checked it in the data pack: `M10003`, two deals with active weekly schedules since 2024-09-02, 25 sold-outs, and Deal Alert replies saying no action is needed. The tool does not use schedules in its recommendation yet (`automation_covers` is not built), so it most likely recommends a call. Recorded in `NOTES.md` and as a known miss in the README.

## 7. Owner

Greg replied: the second half was right to drop; he wants a stop rule that is a number with a named watcher; a Python diff is code, not config, so January; no OK before Hana has seen the diff.

**Assistant:** Agreed on all three: it is code under the freeze, and no OK is claimed before Hana's review (5.6 not done). For the stop rule computed the noise in 30-day GP among scheduled deals (mean £19.25, sd £23.21, n=8,165; about £1.5 standard error for 1,230 against 306 deals) and proposed a £3 threshold, with the caveats (sold-outs treated as independent, band mix ignored, a smaller gap is indistinguishable from noise). The watcher is left to the owner. Recorded in `NOTES.md` and the README.

## 8. Owner

Commit the README and the notes, add about 15 minutes to the time log (not counting the chat with colleagues), and save the session in the same commit.

**Assistant:** Time table: 15 minutes (the owner's estimate), total 187 of 180, which is 7 over the budget. This log written. `PLAN.md` (the 5.2 tick) is left out of the commit as the owner named only the README and the notes.
