"""Deal-level holdout for the sold-out suppression list.

A deal is held out (keeps its tasks, control) or suppressed (treatment) by this formula:

    h = int.from_bytes(sha256(f"{salt}:{deal_id}".encode("utf-8")).digest()[:8], "big")
    held_out = h < int(share * 2**64)

`salt` is the stripped content of the salt file and `deal_id` the stripped value from the
deals file. The comparison is on integers, so share 0 holds out nobody and share 1 everybody.
The read-out must reproduce the groups from the salt, the share and the deal list, so none
of them may change once the assignment has been written.
"""
import argparse
import csv
import hashlib
import json
import os
import re
import secrets
import sys
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
HELD_OUT = "held_out"
SUPPRESSED = "suppressed"
SALT_PATTERN = re.compile(r"[0-9a-f]{32}")


def validate_share(share):
    if isinstance(share, bool) or not isinstance(share, (int, float)) or not 0 <= share <= 1:
        raise ValueError(f"share must be a number between 0 and 1, got {share!r}")


def _require_text(name, value):
    if not isinstance(value, str) or not value.strip():
        raise ValueError(f"{name} must be a non-empty string")


def position(deal_id, salt):
    digest = hashlib.sha256(f"{salt}:{deal_id}".encode("utf-8")).digest()
    return int.from_bytes(digest[:8], "big")


def is_held_out(deal_id, salt, share):
    _require_text("deal_id", deal_id)
    _require_text("salt", salt)
    validate_share(share)
    return position(deal_id, salt) < int(share * 2**64)


def load_deal_ids(path):
    path = Path(path)
    with open(path, newline="", encoding="utf-8-sig") as f:
        reader = csv.reader(f)
        header = [c.strip() for c in next(reader, [])]
        if "deal_id" not in header:
            raise ValueError(f"{path.name}: missing column deal_id")
        index = header.index("deal_id")
        seen = {}
        for row in reader:
            deal_id = row[index].strip() if index < len(row) else ""
            if not deal_id:
                raise ValueError(f"{path.name} line {reader.line_num}: empty deal_id")
            seen.setdefault(deal_id, None)
    if not seen:
        raise ValueError(f"{path.name}: no deals")
    return list(seen)


def load_or_create_salt(path):
    path = Path(path)
    try:
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    except FileExistsError:
        pass
    else:
        with os.fdopen(fd, "w", encoding="utf-8") as f:
            f.write(secrets.token_hex(16) + "\n")
    salt = path.read_text(encoding="utf-8").strip()
    if not SALT_PATTERN.fullmatch(salt):
        raise ValueError(f"{path}: salt file is empty or damaged (expected 32 hex characters)")
    return salt


def params_path_for(salt_path):
    return salt_path.with_name(f"{salt_path.stem}.params.json")


def check_params(params_path, salt_path, share):
    """True when this salt's params are already recorded with the same share."""
    if not params_path.exists():
        return False
    if not salt_path.exists():
        raise ValueError(f"{params_path} exists but {salt_path} is missing; the recorded assignment needs its salt")
    params = json.loads(params_path.read_text(encoding="utf-8"))
    if not isinstance(params, dict) or params.get("salt_file") != salt_path.name:
        raise ValueError(f"{params_path} does not belong to {salt_path.name}")
    if params.get("share") != share:
        # The same salt with another share would silently give different groups.
        raise ValueError(f"{params_path}: this salt was used with share {params.get('share')}, not {share}")
    return True


def assign(deal_ids, salt, share):
    return [(d, HELD_OUT if is_held_out(d, salt, share) else SUPPRESSED) for d in deal_ids]


def _write_new(path, text):
    with open(path, "x", encoding="utf-8", newline="") as f:
        f.write(text)


def run(deals_path, out_path, salt_path, share, today=None):
    validate_share(share)
    out_path, salt_path = Path(out_path), Path(salt_path)
    params_path = params_path_for(salt_path)
    if out_path.exists():
        raise FileExistsError(f"{out_path} exists; an assignment is written once, before outcomes")
    deal_ids = load_deal_ids(deals_path)
    params_recorded = check_params(params_path, salt_path, share)
    salt = load_or_create_salt(salt_path)
    assigned_on = (today or date.today()).isoformat()
    groups = assign(deal_ids, salt, share)

    if not params_recorded:
        params = {"share": share, "salt_file": salt_path.name, "assigned_on": assigned_on}
        _write_new(params_path, json.dumps(params, indent=2) + "\n")
    try:
        f = open(out_path, "x", newline="", encoding="utf-8")
    except BaseException:
        if not params_recorded:
            params_path.unlink(missing_ok=True)
        raise
    try:
        with f:
            writer = csv.writer(f)
            writer.writerow(["deal_id", "group", "assigned_on"])
            writer.writerows((d, g, assigned_on) for d, g in groups)
    except BaseException:
        out_path.unlink(missing_ok=True)
        if not params_recorded:
            params_path.unlink(missing_ok=True)
        raise
    return groups


def main(argv=None):
    parser = argparse.ArgumentParser(prog="python3 -m holdout.assign")
    parser.add_argument("deals")
    parser.add_argument("--out", required=True)
    parser.add_argument("--salt-file", default=str(ROOT / "salt.txt"))
    parser.add_argument("--share", type=float, default=0.2)
    args = parser.parse_args(argv)
    try:
        groups = run(args.deals, args.out, args.salt_file, args.share)
    except (ValueError, OSError) as e:
        print(f"error: {e}", file=sys.stderr)
        return 1
    held = sum(1 for _, g in groups if g == HELD_OUT)
    print(
        f"share {args.share}: {held} {HELD_OUT}, {len(groups) - held} {SUPPRESSED}; "
        f"salt in {args.salt_file}, written to {args.out}",
        file=sys.stderr,
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
