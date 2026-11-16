@AGENTS.md
@WORKFLOW.md

# Claude Code

## Model selection

Work at high quality, but spend effort in proportion to the task, and remember the whole job is time-boxed. Pick agents on your own, without asking the owner. Every task carries a tier, decided once when the task is planned (the owner can change it):

- `[S]` (simple): configs, Docker, installing packages, docs, simple enums/entities/pages without business logic. The main session does it itself, without agents: they start cold and cost more than the task. The check is `make lint` and the owner's own verification
- `[C]` (complex): the money calculation, the recommendation rules, the LLM pipeline and prompts, quote verification, the routing change, anything security-relevant (secrets, the sandbox key, access to the tool, what is exposed). A task that fits both tiers, or whose tier is in doubt, is `[C]`. The `developer` agent (`.claude/agents/developer.md`) writes it, then the `reviewer` agent (`.claude/agents/reviewer.md`) reviews it. The review is done by a different model, otherwise it inherits the author's blind spots. Small review findings are fixed by the main session; substantial ones go to a new `developer` run with a short focused prompt
- Independent `[C]` tasks may run in parallel agents only when they touch different files
- Only the owner changes the main session's model and effort: suggest the switch at planning moments. Don't switch the model mid-session: the prompt cache is tied to the model
- For the money calculation and the LLM pipeline, additionally suggest `/code-review ultra` to the owner: only the owner runs it, it is paid
- Don't use lightweight models or low effort for code and rules. Don't enable fast mode

## LLM budget

The sandbox gateway gives USD 20 for the whole job, no top-ups. Before any LLM run over real data: estimate the cost from a sample of 5–10 items, state the estimate, and get the owner's yes. Check `GET /me` before and after a run and log the numbers in the session notes.
