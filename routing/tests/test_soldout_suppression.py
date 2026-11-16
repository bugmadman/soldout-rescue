import tempfile
import unittest
from pathlib import Path

from routing.plan import build_plan

PRIORS = {"restock": {"called": 0.5, "left_alone": 0.4}}


def row(task_id, deal_id, merchant_id, callback="0", value="100.00"):
    return {
        "task_id": task_id, "merchant_id": merchant_id, "merchant_name": f"Test {merchant_id}",
        "deal_id": deal_id, "value_30d_gbp": value, "callback_promised": callback,
        "do_not_contact": "0", "failed_attempts": "0",
    }


ROWS = [
    row("T-1", "D-1", "M-1"),
    row("T-2", "D-2", "M-2", callback="1"),
    row("T-3", "D-3", "M-3", value="50.00"),
]


def config(switch, suppression_file):
    return {
        "minutes_per_day": 210, "minutes_per_task": 12, "max_failed_attempts": 3,
        "suppressed_deal_ids": [], "suppress_soldout_tasks": switch, "suppression_file": str(suppression_file),
    }


def task_ids(plan):
    today, carried = plan
    return [p["task_id"] for p in today + carried]


class SoldoutSuppressionTest(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.dir.cleanup)
        self.file = Path(self.dir.name) / "suppressions.csv"

    def test_switch_off_does_not_read_the_file(self):
        self.file.write_text("not,a,suppression,file\n")
        off = build_plan(ROWS, PRIORS, config(False, self.file))
        no_file = build_plan(ROWS, PRIORS, config(False, Path(self.dir.name) / "missing.csv"))
        self.assertEqual(off, no_file)
        self.assertEqual(task_ids(off), ["T-2", "T-1", "T-3"])

    def test_switch_on_drops_listed_deal_and_keeps_listed_callback(self):
        self.file.write_text(
            "deal_id,merchant_id,reason,source,added_on\n"
            "D-1,M-1,auto-replenish,merchant_ops,2026-11-01\n"
            "D-2,M-2,auto-replenish,merchant_ops,2026-11-01\n"
        )
        self.assertEqual(task_ids(build_plan(ROWS, PRIORS, config(True, self.file))), ["T-2", "T-3"])

    def test_merchant_wide_row_with_spaces_and_bom_drops_that_merchant(self):
        self.file.write_text(
            "deal_id,merchant_id,reason,source,added_on\n ,  M-3 ,x,merchant_ops,2026-11-01\n",
            encoding="utf-8-sig",
        )
        self.assertEqual(task_ids(build_plan(ROWS, PRIORS, config(True, self.file))), ["T-2", "T-1"])

    def test_row_with_both_ids_suppresses_the_deal_only(self):
        self.file.write_text("deal_id,merchant_id,reason,source,added_on\nD-1,M-3,x,merchant_ops,2026-11-01\n")
        self.assertEqual(task_ids(build_plan(ROWS, PRIORS, config(True, self.file))), ["T-2", "T-3"])

    def test_only_json_true_turns_the_switch_on(self):
        self.file.write_text("deal_id,merchant_id,reason,source,added_on\nD-1,,x,merchant_ops,2026-11-01\n")
        for value in ("true", "false", 1, None):
            with self.subTest(value=value):
                self.assertEqual(task_ids(build_plan(ROWS, PRIORS, config(value, self.file))), ["T-2", "T-1", "T-3"])


if __name__ == "__main__":
    unittest.main()
