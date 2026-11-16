"""Build the static sold-out suppression list from the auto-replenish schedules.

Two steps, run once before the list goes through the gate:
1. `candidates`: deals with a schedule active on the export date -> a deals CSV (input of holdout.assign).
2. `suppressions`: candidates minus the held-out deals -> the routing file `input/suppressions.csv`.

A schedule is active on a date when start_date <= date and end_date is empty or >= date. The list is a
snapshot of that date: schedules created or ended later do not change it.
"""
import argparse
import csv
import sys
from datetime import date
from pathlib import Path

from holdout.assign import HELD_OUT, SUPPRESSED

REASON = "auto_replenish_active"
SOURCE = "soldout-rescue"
CANDIDATE_COLUMNS = ["deal_id", "merchant_id", "reason"]
SUPPRESSION_COLUMNS = ["deal_id", "merchant_id", "reason", "source", "added_on"]


def _read(path, required):
    with open(path, newline="", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        header = [h.strip() for h in reader.fieldnames or []]
        missing = [c for c in required if c not in header]
        if missing:
            raise ValueError(f"{Path(path).name}: missing column(s) {', '.join(missing)}")
        reader.fieldnames = header
        return [{k: (v or "").strip() for k, v in row.items() if k} for row in reader]


def candidates(schedules_path, on):
    """One row per deal with a schedule active on `on` (ISO date string), first-seen order."""
    date.fromisoformat(on)
    found = {}
    for row in _read(schedules_path, ["deal_id", "merchant_id", "start_date", "end_date"]):
        if not row["deal_id"]:
            raise ValueError(f"{Path(schedules_path).name}: a schedule row has no deal_id")
        if row["start_date"] <= on and (not row["end_date"] or row["end_date"] >= on):
            found.setdefault(row["deal_id"], {"deal_id": row["deal_id"], "merchant_id": row["merchant_id"],
                                              "reason": REASON})
    return list(found.values())


def suppressions(candidate_rows, assignment_rows, on):
    """Candidates whose assignment is `suppressed`. A candidate without an assignment is an error."""
    groups = {r["deal_id"]: r["group"] for r in assignment_rows}
    rows = []
    for c in candidate_rows:
        group = groups.get(c["deal_id"])
        if group is None:
            raise ValueError(f"deal {c['deal_id']} has no assignment: assign before exporting")
        if group not in (HELD_OUT, SUPPRESSED):
            raise ValueError(f"deal {c['deal_id']}: unknown group {group!r}")
        if group == SUPPRESSED:
            rows.append({**c, "source": SOURCE, "added_on": on})
    return rows


def _write_new(path, columns, rows):
    with open(path, "x", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=columns)
        writer.writeheader()
        writer.writerows(rows)


def main(argv=None):
    parser = argparse.ArgumentParser(prog="holdout.export_list")
    sub = parser.add_subparsers(dest="step", required=True)
    c = sub.add_parser("candidates")
    c.add_argument("schedules")
    c.add_argument("--on", required=True, help="export date, YYYY-MM-DD")
    c.add_argument("--out", required=True)
    s = sub.add_parser("suppressions")
    s.add_argument("candidates")
    s.add_argument("assignments")
    s.add_argument("--on", required=True, help="export date, YYYY-MM-DD")
    s.add_argument("--out", required=True)
    args = parser.parse_args(argv)
    try:
        if args.step == "candidates":
            rows = candidates(args.schedules, args.on)
            if not rows:
                raise ValueError("no deal has an active schedule on that date")
            _write_new(args.out, CANDIDATE_COLUMNS, rows)
            print(f"{len(rows)} candidate deals", file=sys.stderr)
        else:
            date.fromisoformat(args.on)
            cand = _read(args.candidates, CANDIDATE_COLUMNS)
            rows = suppressions(cand, _read(args.assignments, ["deal_id", "group"]), args.on)
            _write_new(args.out, SUPPRESSION_COLUMNS, rows)
            print(f"{len(rows)} suppressed, {len(cand) - len(rows)} held out", file=sys.stderr)
    except (ValueError, OSError) as e:
        print(f"error: {e}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
