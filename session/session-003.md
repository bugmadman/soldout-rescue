# Session 003

Date: 2026-11-16 (CET). Audit of the plan against the brief. About 10 minutes of active time (owner's estimate).

Conventions: as in session 001. Assistant replies are given in full but condensed in wording.

---

## 1. Owner

Three requests: check `PLAN.md` against `docs/00`–`08` and find tasks that are missing or contradict the brief; find everything in `NOTES.md` and `PLAN.md` that does not come from this project; build a table of open questions (the question, who to contact, the file it came from).

**Assistant:** Read `PLAN.md`, `NOTES.md`, `README.md`, `docs/00`–`08`, `docs/notes.md`, the routing README and the routing config. Changed no files. The plan covers all eight of Dan's points, with four contradictions and nine gaps.

Contradictions with the brief:
- A facilitator may run the tests and "the detector" offline, from a file, on unseen data. The plan read data only through the sandbox API, and the PHP part lives in Docker, whose image build needs the network.
- Task 3.5 took `marked_by` from "the access token's rep", but 3.6 is one shared token that carries no rep.
- Greg has to be told early, yet the task was the last one of phase 5; the messages to Priya and Renee were also at the end of their phases, although answers take time.
- "Today is Sunday 15 November, the freeze starts tomorrow" is not in the brief. The team's clock (`GET /me`) decides, and the routing explainer still lists a Wednesday 15:00 review and a last Friday check before the freeze, so "the real number only after 7 January" is not proven.

Missing tasks:
- Text signals did not feed the recommendation: they were only shown in the detail view.
- The holdout and the suppression list were not linked: the export came before the assignment, and routing sees `task_id` and `deal_id`, not the sold-out id.
- Nobody sent the change request to Greg: the Submit panel does not deliver it.
- The text sample was not tied to Renee's book (North H&B), so her check had nothing to compare.
- No question to Hana before coding, although Dan says she knows what people trip over.
- Three data traps were absent from task 2.5: refunds arriving after 30 days, the early-2026 CRM migration with an odd `owner_id`, Deal Alert sends only from August 2025.
- No phone screenshots, although public hosting is the first cut.
- "Submit" did not list the fields of the Submit panel.
- The 48-hour limit from first opening the Desk was recorded nowhere.

Smaller points: Priya was to be asked for automation data that is already in the pack and belongs to Merchant Ops; the time figures in `PLAN.md` and `NOTES.md` (28 used) disagreed with the README (43 used); no update of the routing README after the change; no task for the database tables.

Not from this project, in `NOTES.md` and `PLAN.md`: no employer, other project or personal file is named. Wording that reads as carried over: "adapted to this project" in task 0.1; "no key encryption, no prod compose file"; "the quality tooling beyond PHPUnit, PHPStan and PHP-CS-Fixer"; "No Node, no asset build, no AssetMapper"; "Built for the time box… coarse tasks"; `make updb` named in `AGENTS.md` but absent from the plan. The owner's choices rather than the brief's: the whole stack, Bootstrap from a CDN, the shared token, the task tiers, the bootstrap interval and the salted hash. Facts absent from the brief: "Sunday 15 November" and "28 minutes used".

Open questions, 16 in a table, among them: what "the detector" is and its data format (hiring team); which deadline binds and what day it is on the team's clock (hiring team, `GET /me`); what Greg needs to say yes and whether the review and the Friday check are reachable (Greg); what to do with `env_overrides.json` (Hana); whether a daily update of `suppressions.csv` counts as a config change under the freeze (Hana, Greg); how the holdout joins the suppression list (Hana); the £ rate per rep hour (Priya, then Dan); the CRM migration (Priya, Hana); whether the findings match what a rep knows (Renee); hosting or a run command with screenshots, the model and the size of the LLM run (owner).

## 2. Owner

Yes, record the findings.

**Assistant:** Updated `PLAN.md` and `NOTES.md`. Nothing committed. The plan now has 45 tasks.

Decisions made by the assistant, for the owner to check:
- The sandbox port gets a second adapter that reads CSV files from a data-pack directory, so the verdicts run offline (task 3.1). "The sandbox API as the only data source" moved to the rejected list.
- `marked_by` is the rep selected in the tool (task 3.5).
- Task 5.5 was removed; the export of the suppression list is now 6.4, right after the holdout assignment, and leaves the holdout share out.
- The quality-tooling item was removed from the cut order: nothing is there to cut.
- `updb` was added to the Makefile targets, because `AGENTS.md` names it.
- The budget was aligned with the README time log.

Other changes in the plan: new 0.5 (first messages to Greg, Hana and Priya before any code, the team's day and time from `GET /me`), with 2.6 and 5.6 now only recording the answers; new 4.8 (verified signals feed the recommendation rules); the extra data traps in 2.5; the sample from Renee's book in 4.2; screenshots in 3.7; the routing README and version in 5.2; two dates for the real number in 6.3; the change request sent to Greg in chat in 7.2; the Submit fields in 7.4; "written for this project" in 0.1.

Changes in the notes: the carried-over wording removed; both deadlines recorded; five superseded decisions moved to "Rejected alternatives and cuts" with reasons; twelve open questions in the inbox, each with the person to ask.

Warning given: the CSV adapter in 3.1 and task 4.8 cost time against a spare of 2 minutes.

## 3. Owner

Write this session to `session/` and log the time for this work: 10 minutes by the owner's estimate.

**Assistant:** Wrote this file. Added a row to the README time table (10 minutes; total 53 of 180, remaining 127; start and end not recorded, so marked as the owner's estimate). Updated the budget line in `PLAN.md` and the time line in `NOTES.md`: phases 1–7 plan 135 minutes against 127 remaining, so the plan is 8 minutes over. Put the overrun in the notes inbox as an open question; what to cut is the owner's decision. Nothing committed.
