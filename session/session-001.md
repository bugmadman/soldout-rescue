# Session 001

Date: 2026-11-15. Start 20:16, log written 20:36 (CET).

Conventions: long pasted documents are not repeated here; each is listed with the file it was saved to under `docs/`. Assistant replies are given in full but condensed in wording.

---

## 1. Owner

Build a mobile tool that shows reps today's sold-out deals with a recommendation (call or not) and an amount in £, plus computes money in code, parses texts via an LLM with quotes, and adds a safe switch to the routing. Prove the effect with a holdout rather than trusting Priya's £11. Deliver a repository with a README (what was cut and why) and two memos, for Dan and for Greg.

First: I will send the requirements, and you must save all of them to a `docs` folder and add `docs` to `.gitignore`.

**Assistant:** Created `docs/` with `00-brief.md` (overview of the task) and `.gitignore` containing `docs/` and `.DS_Store`. The folder is not a git repository yet, so `.gitignore` takes effect after `git init`, which was not run. Asked for the detailed requirements.

## 2. Owner — pasted document: Dan's ask ("Sold-outs: the ask") with the hiring team's rules

Saved as `docs/01-dan-ask.md`.

**Assistant:** Saved. Noted that the sections "How to hand in" and "Sandbox API" were not in the paste, and asked for them, plus `routing.zip` and Priya's memo.

## 3. Owner — pasted document: "How to hand in"

Saved as `docs/02-how-to-hand-in.md`.

**Assistant:** Saved. Key points: only the three lines for Dan are mandatory; the deadline is Thursday 09:00; the change request for Greg is not sent automatically and must be sent to him in chat; the detector must run offline; keep commit history; do not commit the sandbox key; README must cover how to run, decisions, what is missing or cut, and where AI is used.

## 4. Owner — pasted document: "Groupon in one page"

Saved as `docs/03-groupon-in-one-page.md`.

**Assistant:** Saved. Summarised the domain: cap, restock, Deal Alert, auto-replenish, GP, and the tension between lost sales and the merchant's limits.

## 5. Owner — pasted document: Priya's memo "Restock calls: £11 GP per call", with four comments

Saved as `docs/04-priya-memo.md`.

**Assistant:** Saved. Recomputed the weighted figure (£11.08), which is arithmetically correct. Method concerns: observational data, not randomised; auto-replenish and Deal Alert were not controlled for; survivorship; the £340k annual figure applies the average to the whole queue although the effect is concentrated in bands A and B. Greg's comment says automated cap raises come through his team, not before January.

## 6. Owner — question

Were the comments on "Restock calls: £11 GP per call" saved?

**Assistant:** Yes, all four are at the end of `docs/04-priya-memo.md`: Dan, Marcus, Greg and Renee.

## 7. Owner — pasted document: "Data pack README"

Saved as `docs/05-data-pack-readme.md`.

**Assistant:** Saved. Listed the files, their known problems (replayed CRM sync, 500-character cut, garbled characters, blank `rep_id` before 2025, refunds file covers about a third) and who to ask. Noted the schedule and Deal Alert files were added after Priya's analysis. Asked for the actual files.

## 8. Owner — pasted document: "How restock tasks get routed" (files to follow)

Saved as `docs/06-routing-explainer.md`.

**Assistant:** Saved. Key points: one task per sold-out regardless of automation; the switch `suppress_soldout_tasks` exists but nothing acts on it; Greg's written OK is required; Wednesday 15:00 change review; peak freeze from Monday to 7 January.

## 9. Owner — pasted document: "Sandbox API"

Saved as `docs/07-sandbox-api.md`.

**Assistant:** Saved. Key points: endpoints, USD 20 LLM budget, thinking models need `max_tokens` of 2000 or more or thinking disabled, no forced `tool_choice`, always check `stop_reason`. Asked for the key via an environment variable, not in chat.

## 10. Owner — pasted document: "Sandbox API reference"

Saved as `docs/08-sandbox-api-reference.md`.

**Assistant:** Saved. Noted a small difference with the previous document (filters `city` and `size_band` on `/merchants`), to be checked against `openapi.json`.

## 11. Owner

The data pack and routing are also in `docs`.

**Assistant:** Found `docs/data-pack/` (11 CSV files) and `docs/routing/` (service and tests). Read the code. Findings: the routing plan makes one task per sold-out, ranks by `(called − left_alone) × value_30d_gbp`, fills 210 minutes at 12 minutes a task; the switch and suppression file are not wired. `config/env_overrides.json` sets temporary priors of 0.46 and 0.43 against 0.62 and 0.41 in `priors.json`, which shrinks reported expected GP about seven times without changing order. Since `docs/` is not committed, the routing code must be copied into the repository.

## 12. Owner

Check everything in `docs` for rule violations, API keys, or data that could affect the process. It must be safe, with no chance of a leak.

**Assistant:** Scanned all documents, CSV files and code. No keys, tokens, passwords, emails, phone numbers or hidden instructions were found. The only invisible character is a joiner inside an emoji. The routing code has no network or process calls. Notes: free-text records must be treated as data when sent to an LLM; do not commit the CSVs; copy `routing` without `env_overrides.json`; keep the key only in `.env`.

## 13. Owner

Create `notes.md` and write there the "things to watch" and "two recommendations for the repository".

**Assistant:** Created `docs/notes.md` (in the ignored folder, since they are working notes).

## 14. Owner

Now create `README.md` with how much time we have spent, simple and short, with a structure; we will return to it later. Also `session/session-****.md` with our whole conversation. We have 3 hours and must see how much time we have used.

**Assistant:** Created `README.md` with a time table (20 of 180 minutes used at 20:36, 160 remaining) and a planned structure, and this file.

## 15. Owner

We have no routing folder.

**Assistant:** Copied `docs/routing/` to `routing/` in the project root; the 11 existing tests pass. `config/env_overrides.json` was kept because the code reads it on every run.

## 16. Owner

This should go into `notes.md`.

**Assistant:** Added a section on the routing copy and `env_overrides.json` to `docs/notes.md`.
