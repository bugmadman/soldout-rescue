import json
import tempfile
import unittest
from pathlib import Path
from unittest import mock

from routing.plan import ROOT, build_plan, load_config, load_rows, load_soldout_suppression

PRIORS = {"restock": {"called": 0.5, "left_alone": 0.4}}
HEADER = "deal_id,merchant_id,reason,source,added_on\n"


def row(task_id, deal_id, merchant_id, callback="0", value="100.00", dnc="0"):
    return {
        "task_id": task_id, "merchant_id": merchant_id, "merchant_name": f"Test {merchant_id}",
        "deal_id": deal_id, "value_30d_gbp": value, "callback_promised": callback,
        "do_not_contact": dnc, "failed_attempts": "0",
    }


ROWS = [
    row("T-1", "D-1", "M-1"),
    row("T-2", "D-2", "M-2", callback="1"),
    row("T-3", "D-3", "M-3", value="50.00"),
]

# Four tasks fit the day, so some of this list is carried over.
DAY_ROWS = [
    row("T-1", "D-1", "M-1", value="100.00"),
    row("T-2", "D-2", "M-2", callback="1"),
    row("T-3", "D-3", "M-3", value="60.00"),
    row("T-4", "D-4", "M-4", value="90.00"),
    row("T-5", "D-5", "M-4", value="40.00"),
    row("T-6", "D-6", "M-4", callback="1"),
    row("T-7", "D-7", "M-5", value="80.00"),
    row("T-8", "D-8", "M-6", value="70.00", dnc="1"),
    row("T-9", "D-9", "M-7", callback="1"),
    row("T-10", "D-10", "M-8", value="20.00"),
]
DAY_LIST = (
    HEADER
    + "D-1,M-1,auto-replenish,merchant_ops,2026-11-01\n"
    + "D-2,M-2,auto-replenish,merchant_ops,2026-11-01\n"
    + ",M-4,on hold,merchant_ops,2026-11-01\n"
    + "D-8,M-6,x,merchant_ops,2026-11-01\n"
    + "D-9,M-7,x,merchant_ops,2026-11-01\n"
)


def config(switch, suppression_file, **values):
    return {
        "minutes_per_day": 210, "minutes_per_task": 12, "max_failed_attempts": 3,
        "suppressed_deal_ids": [], "suppress_soldout_tasks": switch, "suppression_file": str(suppression_file),
        **values,
    }


def day_config(switch, suppression_file):
    return config(switch, suppression_file, minutes_per_day=48, suppressed_deal_ids=["D-9"])


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


class SwitchSafetyTest(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.dir.cleanup)
        self.file = Path(self.dir.name) / "suppressions.csv"
        self.file.write_text(DAY_LIST)
        self.off = build_plan(DAY_ROWS, PRIORS, day_config(False, self.file))

    def test_switch_off_equals_a_config_without_the_suppression_keys(self):
        without_keys = day_config(False, self.file)
        del without_keys["suppress_soldout_tasks"], without_keys["suppression_file"]
        self.assertEqual(self.off, build_plan(DAY_ROWS, PRIORS, without_keys))
        self.assertEqual(task_ids(self.off), ["T-2", "T-6", "T-1", "T-4", "T-7", "T-3", "T-5", "T-10"])

    def test_shipped_routing_json_has_the_switch_off(self):
        self.assertIs(json.loads((ROOT / "config" / "routing.json").read_text())["suppress_soldout_tasks"], False)

    def test_switch_on_drops_only_listed_restock_tasks(self):
        today, carried = build_plan(DAY_ROWS, PRIORS, day_config(True, self.file))
        dropped = {"T-1", "T-4", "T-5"}
        kept = [p for p in self.off[0] + self.off[1] if p["task_id"] not in dropped]
        self.assertEqual(today + carried, kept)
        self.assertEqual(task_ids((today, carried)), ["T-2", "T-6", "T-7", "T-3", "T-10"])

    def test_switch_on_refills_the_day_from_the_carried_over_list(self):
        today, carried = build_plan(DAY_ROWS, PRIORS, day_config(True, self.file))
        self.assertEqual([p["task_id"] for p in today], ["T-2", "T-6", "T-7", "T-3"])
        self.assertEqual([p["task_id"] for p in carried], ["T-10"])

    def test_do_not_contact_and_suppressed_deal_ids_still_drop_everything(self):
        for switch in (False, True):
            with self.subTest(switch=switch):
                ids = task_ids(build_plan(DAY_ROWS, PRIORS, day_config(switch, self.file)))
                self.assertNotIn("T-8", ids)
                self.assertNotIn("T-9", ids)

    def test_header_only_or_missing_file_with_switch_on_changes_nothing(self):
        self.file.write_text(HEADER)
        missing = Path(self.dir.name) / "missing.csv"
        for path in (self.file, missing):
            with self.subTest(path=path.name):
                self.assertEqual(build_plan(DAY_ROWS, PRIORS, day_config(True, path)), self.off)

    def test_rollback_restores_the_original_plan(self):
        on = build_plan(DAY_ROWS, PRIORS, day_config(True, self.file))
        self.assertNotEqual(on, self.off)
        back = build_plan(DAY_ROWS, PRIORS, day_config(False, self.file))
        self.assertEqual(back, self.off)
        self.assertEqual([i["task_id"] for i in back[0]], ["T-2", "T-6", "T-1", "T-4"])
        self.assertEqual([i["task_id"] for i in back[1]], ["T-7", "T-3", "T-5", "T-10"])


class ShippedInputTest(unittest.TestCase):
    def setUp(self):
        self.rows = load_rows(ROOT / "input" / "todays_soldouts.csv")
        with mock.patch("routing.plan.load_soldout_suppression", return_value=(set(), set())):
            self.baseline = build_plan(self.rows)

    def test_shipped_config_output_equals_a_baseline_without_suppression(self):
        with mock.patch("routing.plan.load_suppressions", side_effect=AssertionError("file read with switch off")):
            self.assertEqual(build_plan(self.rows), self.baseline)

    def test_rollback_on_shipped_input_restores_the_baseline(self):
        everything = self.baseline[0] + self.baseline[1]
        listed = [p["deal_id"] for p in everything if p["reason"] == "restock"][:2]
        with tempfile.TemporaryDirectory() as d:
            path = Path(d) / "suppressions.csv"
            path.write_text(HEADER + "".join(f"{deal},,x,merchant_ops,2026-11-01\n" for deal in listed))
            on_config = {**load_config(), "suppress_soldout_tasks": True, "suppression_file": str(path)}
            on = build_plan(self.rows, config=on_config)
            off = build_plan(self.rows, config={**on_config, "suppress_soldout_tasks": False})
        kept = [p for p in everything if p["reason"] == "callback" or p["deal_id"] not in listed]
        self.assertEqual(len(set(listed)), 2)
        self.assertEqual(on[0] + on[1], kept)
        self.assertEqual(off, self.baseline)


class OverridePrecedenceTest(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.dir.cleanup)
        self.root = Path(self.dir.name)
        (self.root / "config").mkdir()
        patcher = mock.patch("routing.plan.ROOT", self.root)
        patcher.start()
        self.addCleanup(patcher.stop)

    def write(self, routing, overrides):
        base = {"minutes_per_day": 210, "minutes_per_task": 12, "max_failed_attempts": 3}
        (self.root / "config" / "routing.json").write_text(json.dumps({**base, **routing}))
        (self.root / "config" / "env_overrides.json").write_text(json.dumps({"routing": overrides}))

    def list_deal(self, name, deal_id):
        (self.root / name).write_text(HEADER + f"{deal_id},,x,merchant_ops,2026-11-01\n")

    def test_override_false_switches_off_routing_json_true(self):
        self.list_deal("s.csv", "D-1")
        self.write({"suppress_soldout_tasks": True, "suppression_file": "s.csv"}, {"suppress_soldout_tasks": False})
        self.assertEqual(load_soldout_suppression(load_config()), (set(), set()))

    def test_override_true_switches_on_routing_json_false(self):
        self.list_deal("s.csv", "D-1")
        self.write({"suppress_soldout_tasks": False, "suppression_file": "s.csv"}, {"suppress_soldout_tasks": True})
        self.assertEqual(load_soldout_suppression(load_config()), ({"D-1"}, set()))

    def test_non_boolean_override_leaves_the_switch_off(self):
        self.list_deal("s.csv", "D-1")
        for value in ("true", 1, "yes"):
            with self.subTest(value=value):
                self.write(
                    {"suppress_soldout_tasks": True, "suppression_file": "s.csv"},
                    {"suppress_soldout_tasks": value},
                )
                self.assertEqual(load_soldout_suppression(load_config()), (set(), set()))

    def test_override_of_suppression_file_is_used(self):
        self.list_deal("a.csv", "D-1")
        self.list_deal("b.csv", "D-2")
        self.write({"suppress_soldout_tasks": True, "suppression_file": "a.csv"}, {"suppression_file": "b.csv"})
        self.assertEqual(load_soldout_suppression(load_config()), ({"D-2"}, set()))


if __name__ == "__main__":
    unittest.main()
