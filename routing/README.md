# routing

Builds each rep's morning task list from yesterday's sold-outs. Owned by Merchant Ops (Hana day to day). Runs overnight; lists are ready by about 07:00.

```
python3 -m routing.plan input/todays_soldouts.csv
python3 -m unittest discover -s tests     # or: python3 -m pytest
```

## What it does

1. Takes the sold-outs for one rep (`input/todays_soldouts.csv` is yesterday's file for one rep).
2. Creates one restock task per sold-out. It works from that file only. It has no view of anything else that may be happening to the deal.
3. Drops do-not-contact merchants and anything in `suppressed_deal_ids`. Pauses merchants after too many failed attempts. With `suppress_soldout_tasks` on, also drops restock tasks listed in the sold-out suppression file (callbacks are kept).
4. Promised callbacks go first, whatever they are worth. Someone told the merchant we would ring.
5. The rest are ranked by expected GP: `(called - left_alone) x value_30d_gbp`, using the restock priors.
6. Fills the rep's day. Whatever doesn't fit carries over to tomorrow and shows as overdue.

## Config

| File | What's in it |
|---|---|
| `config/routing.json` | Minutes per day, minutes per task, pause threshold, `suppressed_deal_ids`, `suppress_soldout_tasks`, `suppression_file` |
| `priors.json` | Restock priors used for ranking; also the priors for other task types |
| `config/env_overrides.json` | Environment values. Loaded last, on top of the above, and takes precedence (`priors` over `priors.json`, `routing` over `config/routing.json`) |
| `input/suppressions.csv` | Sold-out suppression list: `deal_id`, `merchant_id`, `reason`, `source`, `added_on`. Ships empty |

Notes for anyone changing things:

- All values are read at start of run. There is no hot reload.
- `suppressed_deal_ids` is a plain list of deal ids. A suppressed deal gets no task. Ops use it for deals in dispute or under a manual arrangement.
- Value per task comes from the input file; routing does not compute it.
- The input file's column order matters to the importer, so add columns at the end.

## Sold-out suppression

Agreed in October so reps stop getting tasks that don't need them.

- Switch: `suppress_soldout_tasks` in `config/routing.json`, default `false`. Only JSON `true` turns it on. A `routing` value in `config/env_overrides.json` wins over `routing.json`, so check both.
- Off: the file named by `suppression_file` is not read at all, and the output is exactly what it was before the switch existed.
- On: `plan.py` reads `suppression_file` (path relative to the routing root, default `input/suppressions.csv`; a missing file suppresses nothing) and drops a restock task when its `deal_id` is listed, or its `merchant_id` is listed in a row with an empty `deal_id` (merchant-wide).
- File columns: `deal_id`, `merchant_id`, `reason`, `source`, `added_on`. A row needs a `deal_id` or a `merchant_id`. A row with a `deal_id` suppresses that deal only (its `merchant_id` is ignored); a row with an empty `deal_id` suppresses the whole merchant. Surrounding spaces and a UTF-8 BOM are tolerated; an empty file is an error, a header-only file suppresses nothing; `reason` and `source` are free text for the audit trail.
- Promised callbacks are never dropped by this list: someone told the merchant we would ring. `suppressed_deal_ids` and do-not-contact still drop everything, callbacks included.
- Rolling back means switching it off, nothing else.

## Ops

- Changes go to Wednesday change review, then Merchant Ops deploys. Config changes count as changes.
- Peak change freeze: nothing touches routing or its config until 7 January.
- Version: 2.16.0 (2.16.0 wired the sold-out suppression into `plan.py` behind the switch, callbacks exempt; 2.15.0 added the switch and file)
