import tempfile
import unittest
from pathlib import Path

from holdout.export_list import candidates, main, suppressions

SCHEDULES = (
    "schedule_id,deal_id,merchant_id,start_date,end_date,cadence,top_up_qty,created_by\n"
    "S1,D-1,M-1,2026-01-01,,weekly,5,ops\n"
    "S2,D-2,M-2,2026-01-01,2026-11-09,weekly,5,ops\n"
    "S3,D-3,M-3,2026-11-11,,weekly,5,ops\n"
    "S4,D-4,M-4,2026-01-01,2026-11-10,weekly,5,ops\n"
    "S5,D-1,M-1,2026-06-01,,monthly,5,ops\n"
)


class ExportListTest(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.dir.cleanup)
        self.path = Path(self.dir.name) / "schedules.csv"
        self.path.write_text(SCHEDULES)

    def test_active_on_the_date_only_and_one_row_per_deal(self):
        ids = [r["deal_id"] for r in candidates(self.path, "2026-11-10")]
        self.assertEqual(ids, ["D-1", "D-4"])

    def test_end_date_is_inclusive_and_start_date_exclusive_of_the_past(self):
        self.assertEqual([r["deal_id"] for r in candidates(self.path, "2026-11-09")], ["D-1", "D-2", "D-4"])
        self.assertEqual([r["deal_id"] for r in candidates(self.path, "2026-11-11")], ["D-1", "D-3"])

    def test_bad_date_and_missing_column_are_errors(self):
        with self.assertRaises(ValueError):
            candidates(self.path, "10/11/2026")
        self.path.write_text("deal_id,merchant_id\nD-1,M-1\n")
        with self.assertRaises(ValueError):
            candidates(self.path, "2026-11-10")

    def test_held_out_deals_are_left_out_and_rows_carry_reason_source_date(self):
        cand = [{"deal_id": "D-1", "merchant_id": "M-1", "reason": "auto_replenish_active"},
                {"deal_id": "D-4", "merchant_id": "M-4", "reason": "auto_replenish_active"}]
        groups = [{"deal_id": "D-1", "group": "held_out"}, {"deal_id": "D-4", "group": "suppressed"}]
        self.assertEqual(
            suppressions(cand, groups, "2026-11-10"),
            [{"deal_id": "D-4", "merchant_id": "M-4", "reason": "auto_replenish_active",
              "source": "soldout-rescue", "added_on": "2026-11-10"}],
        )

    def test_candidate_without_assignment_or_with_unknown_group_is_an_error(self):
        cand = [{"deal_id": "D-1", "merchant_id": "M-1", "reason": "r"}]
        with self.assertRaises(ValueError):
            suppressions(cand, [], "2026-11-10")
        with self.assertRaises(ValueError):
            suppressions(cand, [{"deal_id": "D-1", "group": "maybe"}], "2026-11-10")

    def test_cli_end_to_end_matches_the_routing_file_layout_and_never_overwrites(self):
        d = Path(self.dir.name)
        self.assertEqual(main(["candidates", str(self.path), "--on", "2026-11-10", "--out", str(d / "c.csv")]), 0)
        (d / "a.csv").write_text("deal_id,group,assigned_on\nD-1,suppressed,x\nD-4,held_out,x\n")
        args = ["suppressions", str(d / "c.csv"), str(d / "a.csv"), "--on", "2026-11-10", "--out", str(d / "s.csv")]
        self.assertEqual(main(args), 0)
        self.assertEqual(
            (d / "s.csv").read_text().splitlines(),
            ["deal_id,merchant_id,reason,source,added_on", "D-1,M-1,auto_replenish_active,soldout-rescue,2026-11-10"],
        )
        self.assertEqual(main(args), 1)


if __name__ == "__main__":
    unittest.main()
