# holdout

Picks which deals on the sold-out suppression list are held out. Held-out deals keep their restock tasks (control); the rest are suppressed (treatment). Comparing the two groups' outcomes gives the real effect of the list.

```
python3 -m holdout.assign <deals.csv> --out assignments.csv [--salt-file salt.txt] [--share 0.2]
python3 -m unittest discover -s tests
```

Run both from this folder. Standard library only, no network.

## How a deal is assigned

```
h = first 8 bytes of sha256("<salt>:<deal_id>"), as an unsigned big-endian integer
held out when h < int(share * 2**64)
```

The salt is the content of the salt file with surrounding whitespace removed; `deal_id` is the value from the deals file with surrounding whitespace removed. The same salt and share always give the same groups, whatever the order of the deals. To rebuild the groups, the read-out needs the salt, the share and the deal list.

## The CLI

- Input: a CSV with a `deal_id` column (other columns are ignored). Duplicates are dropped, first-seen order is kept. A missing `deal_id` column, a file with no deals, or an empty `deal_id` (a blank line included) is an error that names the line.
- Salt: read from `--salt-file` (default `salt.txt` in this folder). If the file is missing, a new random salt (32 hex characters) is created, readable by the owner only. An empty or damaged salt file is an error. The salt is never printed.
- Params: `<salt file name without extension>.params.json` next to the salt file records the share, the salt file name and the date of the first assignment. A run with the same salt and a different share is refused, and so is a run whose params file exists without its salt or belongs to another salt file.
- Output: `--out` gets `deal_id,group,assigned_on`, where `group` is `held_out` or `suppressed` and `assigned_on` is the run date. An existing output file is never overwritten. The params are written before the output; if the output cannot be written, the partial output and any params created by that run are removed.
- The share used and the number of deals in each group go to stderr.

## The suppression list export

```
python3 -m holdout.export_list candidates <auto_replenish_schedules.csv> --on YYYY-MM-DD --out candidates.csv
python3 -m holdout.assign candidates.csv --out assignments.csv
python3 -m holdout.export_list suppressions candidates.csv assignments.csv --on YYYY-MM-DD --out suppressions.csv
```

`candidates` keeps the deals with a schedule active on the date (`start_date <= date`, `end_date` empty or `>= date`): a snapshot, so schedules created or ended later do not change the list. `suppressions` drops the held-out deals and writes the routing layout (`deal_id,merchant_id,reason,source,added_on`; `reason` is `auto_replenish_active`). The list is keyed by `deal_id`, so every future sold-out of a listed deal is suppressed. A candidate without an assignment is an error, and an existing output file is never overwritten. The "call is not wanted" half is added as rows with their own `reason` once the text analysis exists.

## Rules

- The assignment is written once, before the list is built and before any outcome is known, and never changes afterwards: the list is static until 7 January.
- Keep the salt file, the params file and the assignments file with the suppression list. Without the salt the groups cannot be rebuilt.
- These three files are run artefacts that hold real deal ids: they stay out of git.
