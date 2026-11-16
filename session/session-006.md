# Session 006

Date: 2026-11-16 (CET). Colleagues' answers to the questions sent after phase 2 so far: Priya, Hana, Greg, Dan and Renee. No code was written; the work was reading the answers, recording what follows from them in `NOTES.md` and `PLAN.md`, and drafting the messages the owner sends. Time is not recorded for this session: the owner does not count discussions of this kind as development time (see README).

Conventions: as in session 001. Replies are condensed. The owner pastes the colleagues' messages and sends all messages; nothing is sent on their behalf.

---

## 1. Owner

Priya's reply: no £ rate per rep hour (ask Dan), she has not checked the CRM migration, the sync replay repeats activity rows (dedupe on task, time and duration), she does not know the non-rep `owner_id` values (ask Hana), and she asks how the 12 minutes per task was derived.

**Assistant:** Recorded in `NOTES.md` and `PLAN.md` (task 0.6 for the follow-ups to Dan and Hana, the dedupe key added to 2.5).

## 2. Owner

What to answer Priya about `duration_s`, and prepare questions for Dan and Hana.

**Assistant:** The 12 minutes is not from `duration_s`: it is `minutes_per_task` in the routing config (210 minutes a day at 12 per task), a scheduling assumption, and `duration_s` exists for calls only. Drafts for Priya, Dan (the £ rate per rep hour) and Hana (non-rep `owner_id`, migration oddities). Corrected in `NOTES.md`.

## 3. Owner

Analyse Priya's reply on the call-timing question (her "called" was any connected call of 2 minutes or more, whenever it happened; she does not know N; auto-replenish never joined; whether reps skip merchants is a question for Renee).

**Assistant:** Her definition has the same bias as ours the other way round, so the £11 is even less reliable. N can come from the data (days from the sold-out to the first call) with a sensitivity table, schedule coverage can be measured by us, selection stays a README caveat and a question for Renee. The fixed-window comparison replaces the first-call-before-restock rule: new task 2.7 before 2.4. No other addressee is needed beyond Renee, Dan and Hana. Recorded after the owner's yes; a draft question for Renee.

## 4. Owner

Priya asks to be told how much of the left-alone group a schedule topped up before it goes anywhere. Promise it, and add it to 2.7.

**Assistant:** Recorded as the one thing agreed with her; the share goes to her with the method before it goes into anything for Dan, and her answer is not waited for. An earlier claim that this had been written to the Inbox was wrong (no edit had been made); it was recorded then, as a decision.

## 5. Owner

Hana's answers: routing matches on `deal_id`; the suppression file is config and goes with the switch; regenerating it daily during the freeze is Greg's call and she will not run it nightly; review at 15:00 with Greg; she is free until 10:30; the page for Greg needs risk, rollback and when to stop. Move phase 5 forward and record her answers; is the question about `owner_id` still relevant?

**Assistant:** Phase 5 moved ahead of the rest of phase 2; task 5.7 (the page for Greg) added; 6.4 reworked around `deal_id`. The `owner_id` question was still open, since Hana's answers did not touch it.

## 6. Owner

Commit NOTES and PLAN, then roll the commit back: the work is not finished.

**Assistant:** Committed and reset; the changes stayed in the working tree. Later asked what "only the diff matters to her" meant: it was my guess about her limited window, not her words. The owner added that the product is not ready and only data is being collected, so no code was started.

## 7. Owner

Greg's first reply: one sentence on the change, one page, small, reversible, off by default, and Hana has seen it; today's review at 15:00 is reachable; Friday's gate is only for what he has already discussed; the page by Thursday morning.

**Assistant:** The realistic route is Friday's gate: the one-sentence description now and the page by Thursday morning; "Hana has seen it" is a condition and is not claimed before she has. A draft reply with the Hana promise removed (no slot agreed). Recorded; task 5.8 added.

## 8. Owner

Dan's message at 09:15 asks for one number for Friday's deck: what fixing sold-outs is worth a year.

**Assistant:** A range with named assumptions and not Priya's £11 times the queue; it comes after the schedule share has gone to Priya. The £ rate per rep hour decides whether an average call pays. A three-line draft for Dan, with one question on the rate; task 2.8 added.

## 9. Owner

Combine the earlier question to Dan and the status update into one message he can read on a phone; record it in the plan.

**Assistant:** Three lines, with a £20 placeholder for the rate that the owner confirms; recorded as task 0.7.

## 10. Owner

Greg's second reply: the automation half he can live with before Monday, the "call isn't wanted" half he will judge on the page; no regeneration during the freeze, the list that goes through Friday's gate is the list until 7 January, a change is an emergency he signs; it counts as discussed for Friday.

**Assistant:** The list becomes a static snapshot with a `reason` per row; the holdout moves to the deal level (a salted hash of `deal_id`), so 6.1 and 6.4 are reworked and come right after the routing tests. The "call isn't wanted" half depends on the text analysis. "I can live with it" is not a written OK. Recorded; the same text pasted a second time held nothing new.

## 11. Owner

Hana's reply about `owner_id` and the migration: not her area; she knows only the notes export has replayed rows.

**Assistant:** Checked on the data pack (aggregates only): every `owner_id` in `activities.csv` is one of R01–R24, so there are no non-rep owners in the CSV pack; 526 of 38,850 activity rows and 380 of 15,267 notes repeat on every field but the id; the activity file has dates only. Recorded. The claim about non-rep owners most likely comes from the README wording; a small sample through `GET /activities?merchant_id=` was added to 2.5.

## 12. Owner

Dan's reply at 12:00: no rep-hour rate, use ours and say so; Priya's work is solid, show why the £11 does not hold; upstairs wants a number; Greg before 15:00.

**Assistant:** The rate is ours: £20, tested at £15 to £30 (a placeholder for the owner to confirm). The £11: the data is sound, the extrapolation is not. One central figure with the range, labelled a model estimate that the holdout replaces. Greg already has the description. Recorded; follow-up to Dan drafted.

## 13. Owner

Tidy `NOTES.md` and `PLAN.md` for a new session: overlaps, duplicates, gaps.

**Assistant:** Rewrote both. Colleague answers became one section of facts and the decisions sit in the topic sections; open questions are one list and the Inbox is empty; stale items (a daily file, the 10:30 window, "the rate blocks the number") removed; 2.9 split out of 2.7; done tasks marked; a "Next steps" order added with the cut decision first.
## 14. Owner

Renee wrote first and asked to see the tool (a screenshot is fine). Her message and the reply that was sent.

**Assistant:** There was nothing to show, so no screenshot; a draft that says what the tool will do, that nothing changes on her list without Greg's OK, and asks the two questions on timing and skipped merchants. The owner sent a version without the promise of a screenshot. Recorded; 0.6 marked as sent.

## 15. Owner

Record this session, commit NOTES and PLAN, do not write the time. Discussions and the working out of who wants what are not development time and were not logged; note it in the README, with a soft postscript on the time for writing the AI instructions.

**Assistant:** `NOTES.md` and `PLAN.md` committed (`15da54e`). This log written without a time. README: a note that discussions with colleagues are not in the time table, and a postscript on the instructions for the AI tools, which are currently counted (25 minutes). The session log and README are not committed yet.
