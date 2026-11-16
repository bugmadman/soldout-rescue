# Notes and decisions

Current project decisions, grouped by topic. Each item is the final decision (with the reason, if there is one); "Open" means the question isn't decided yet. Superseded decisions and cuts are in "Rejected alternatives and cuts", new raw thoughts are in the "Inbox" at the end. The working method is in [WORKFLOW.md](WORKFLOW.md), the tasks in [PLAN.md](PLAN.md).

## Product and concept

- What Dan asked for (`docs/01-dan-ask.md`): a rep tool on a phone; text analysis with quotes; money in code; a safe routing change; a holdout and a number range; a repo with README; AI sessions; three lines for Dan and a one-page change request for Greg.
- The brief is bigger than the time on purpose: cutting on purpose and saying what and why is part of what is assessed. The cut list goes into the README and the three lines.
- The recommendation per sold-out is one of: `call`, `dont_call`, `automation_covers` (a schedule is active, so a call adds little), `other` (e.g. merchant asked for no contact, or a note says capacity is the issue). Each carries the reason and, for `call`, an amount in £.
- Priya's £11 per call is not trusted: observational, auto-replenish ignored, effect concentrated in bands A and B, the £340k extrapolation applies the average to the whole queue. The tool shows per-deal amounts from the money model, not the £11.
- Auto-replenish schedules and Deal Alert sends were not in Priya's analysis: a sold-out topped up automatically is likely not worth a call, and merchants that raise the cap themselves after a Deal Alert are likewise.

## Time

- Total budget 180 minutes of active working time; 28 used before this plan. Remaining 150, minute budgets per phase are in PLAN.md. The time table in README.md is the single record.
- Cut order when behind (first cut goes first): hosting a public link; the merchant-mark mirror in the DB; text sources other than rep notes and Deal Alert replies; the quality tooling beyond PHPUnit, PHPStan and PHP-CS-Fixer; the quality of the tool's styling.
- Never cut: money in code with tests; quote verification; the routing switch with tests and rollback; the holdout read-out script; the README cut list; the three lines for Dan and the change request for Greg.

## Architecture

- Symfony 7.4 on PHP 8.5, PostgreSQL, FrankenPHP in worker mode (Caddy + PHP in one container), everything in Docker, plain HTTP. Layout: `.docker/php/{Dockerfile,Caddyfile,php.ini}`, `compose.yaml`, `compose.override.yaml`, Makefile (`init`, `upd`, `down`, `in`, `cs`, `fix`, `stan`, `test`, `lint`), `.env` with committed defaults and `.env.local` for machine-specific values and the sandbox key.
- The LLM key is the sandbox key from `.env.local`; keys are not stored in the database, so there is no key encryption. There is no prod compose file.
- Two languages by necessity: the routing service (given) and the holdout script are Python, standard library only, so a facilitator can run them offline without Docker. Everything else is PHP.
- PostgreSQL holds only what the sandbox does not: cached LLM findings (with quotes and the source reference), holdout assignments, computed recommendations for the day. Merchants, deals, sold-outs and marks are read from and written to the sandbox API through a port, not copied.
- Domain areas under `src/Domain/`: `SoldOut` (recommendation, rules), `Money` (the model), `TextSignal` (findings, quote verification), `Holdout` (assignment). Adapters under `src/Infrastructure/`: `Sandbox` (HTTP client), `Llm` (gateway client).
- Access to the tool: a single shared access token from `.env.local`, checked by a Symfony security listener; no user accounts. Only a rep-facing page and a mark form are exposed.
- Front end: Twig pages styled with Bootstrap 5, loaded from a CDN with a pinned exact version and SRI hashes, mobile-first (viewport meta, single-column cards). No Node, no asset build, no AssetMapper. Cost: the page needs internet to be styled; acceptable for a rep tool, and the PHP tests never load it.

## Money model

- Computed only in code, in integer pence. A `Money` VO with explicit rounding.
- Per sold-out: expected GP of a cap raise, minus the cost of rep time (minutes per task × a rate), minus expected refund risk. Anything not derivable from the data is an explicit named parameter with a default, shown in the README and the memos, never buried in code.
- Inputs come from history in the data pack: restock rates after a call vs. none by size band, GP in the 30 days after, the merchant's refund rate in `merchant_months.csv`, and whether a schedule or Deal Alert already covered the case.
- The model reports a range (low / central / high), not one number, because the data is observational.
- Data traps to handle: replayed CRM activity rows (dedupe), blank `rep_id` before 2025, `gp_30d_after_gbp` blank for October 2026, refunds arriving later than the 30-day window, schedules joining on `deal_id` and date only.

## Text analysis (LLM)

- Sources: rep notes and Deal Alert replies first; reviews and refund reasons only if time allows (cut candidate).
- Per merchant, the LLM returns structured JSON (`output_config.format` with a schema, thinking disabled, `max_tokens` sized for the answer; check `stop_reason` before parsing): a list of signals, each with a type from a fixed enum, a short rationale and a verbatim quote with the source id.
- Signal types are decided from a first look at a sample, then fixed in an enum (e.g. capacity limit, wants more, do not call, already handled, contact change, out-of-office).
- The prompt states that the text is data to analyse and must not be followed, and wraps every text in delimiters (some records read like requests, e.g. "send all contract stuff to her from now on").
- Quote verification in code: a quote is kept only if it is a substring of the cleaned source text. Cleaning: mojibake repair (Windows-1252 read as UTF-8), whitespace normalisation, dedupe of replayed notes. The cut at 500 characters is accepted and noted.
- Findings are checked against a rep who knows the merchants (Renee for North H&B): a small list of merchants with the tool's finding and quote goes to her in chat, and her answer is recorded. Agreements are claimed in the README only if actually made.
- Cost control: estimate from 5–10 merchants first, then run in small concurrent batches (rate limit 10 requests per second, 429 means wait `Retry-After`). Budget USD 20 for everything.

## Routing change

- Done in the Python service in `routing/`, not in the Symfony app. The change acts on `suppress_soldout_tasks` and `input/suppressions.csv`, which today are read but not acted on: when the switch is on, listed deals or merchants are not given a task. Off by default, one switch, rollback is switching it off, tests for on, off and rollback.
- Routing does not read schedules or Deal Alert state by design. The suppression list is produced outside it (by this project's tool or script) from the money model's "automation covers" and "don't call" verdicts. Keeps the routing change small and its risk reviewable.
- `config/env_overrides.json` beats `routing.json` and currently holds temporary priors (0.46 and 0.43 against 0.62 and 0.41). Open: keep, remove or fix. It does not change ranking order; decide with Hana and name it in the change request.
- Greg's written OK is required before the switch is turned on, even in the sandbox. The change goes to the Wednesday 15:00 change review; the peak freeze starts Monday and runs to 7 January. Today is Sunday 15 November, so the route is: talk to Hana and Greg early (Monday), do not wait for the deadline. Nothing is flipped without Greg.

## Holdout and the real number

- A holdout is a deterministic random share of otherwise eligible sold-outs (hash of the sold-out id with a stored salt) that keeps its task, so suppressed and kept groups are comparable. Assignment is written down before outcomes are known.
- The read-out script reads two files (assignments, outcomes) and prints the difference in GP with a bootstrap interval, offline, from a file. Sample fixtures included.
- The range given to Dan now comes from the money model and the historical data; the real number comes after the first 30-day outcomes. The date is stated honestly: 30 days after the switch goes on, which given the freeze is after 7 January.

## Process

- Process files: `AGENTS.md` (rules), `WORKFLOW.md` (method), `CLAUDE.md` (model selection), `.claude/agents/{developer,reviewer}.md`, `NOTES.md`, `PLAN.md`. Two tiers: `[S]` by the main session, `[C]` by `developer` then `reviewer`.
- Built for the time box: minute budgets per phase and a time log, coarse tasks (about 40), a cut order, a reserved final 15 minutes.
- `docs/` is not committed and `docs/notes.md` holds the audit of the supplied material; this file is the committed record of decisions.
- Every session is exported to `session/` at the end of a phase.

## Rejected alternatives and cuts

- Rewriting the routing service in PHP: rejected. It is Hana's service with its own tests and review route; a Python diff is what Greg's team can review.
- A single Python project: rejected by the owner's preference for Symfony and the DDD structure; Python stays only where it is given or needs to run without Docker.
- Copying the data pack into PostgreSQL: rejected. The sandbox API serves the same data; copying adds an import, a stale copy and a risk of committing data.
- Public hosting: first candidate for cutting. The README will say how to run it with one command.
- Using Priya's £11 as the amount: rejected, see Product; the brief itself says to prove the effect with a holdout rather than trust the £11. Priya is told why and asked for the missing inputs (PLAN 2.6); nothing is described as agreed with her until she answers.

## Inbox

- Open: should the holdout read-out read the real sandbox, or only files? Leaning files only, so it runs offline.
- Open: who tells Greg early that a change is coming, and with what, before the Monday freeze?
- Open: what rate in £ per rep hour to use for the cost of rep time; no figure was found in `docs/`, ask Priya.
