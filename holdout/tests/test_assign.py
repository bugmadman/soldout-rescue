import csv
import io
import json
import stat
import tempfile
import unittest
from contextlib import redirect_stderr, redirect_stdout
from datetime import date
from pathlib import Path
from unittest import mock

from holdout.assign import (
    HELD_OUT,
    SUPPRESSED,
    assign,
    is_held_out,
    load_deal_ids,
    main,
    position,
    run,
)

SALT = "0123456789abcdef0123456789abcdef"
IDS = [f"D-{n}" for n in range(1, 5001)]
TODAY = date(2026, 11, 16)


class IsHeldOutTest(unittest.TestCase):
    def test_pinned_position(self):
        self.assertEqual(position("D-1", "s"), 1087700948886563319)
        self.assertEqual(position("D-1", "s") / 2**64, 0.05896438658986783)

    def test_pinned_groups_at_default_share(self):
        held = [d for d in IDS[:10] if is_held_out(d, "s", 0.2)]
        self.assertEqual(held, ["D-1", "D-8"])

    def test_share_just_above_and_below_a_position(self):
        self.assertTrue(is_held_out("D-1", "s", 0.059))
        self.assertFalse(is_held_out("D-1", "s", 0.058))

    def test_deterministic(self):
        first = [is_held_out(d, SALT, 0.2) for d in IDS]
        self.assertEqual(first, [is_held_out(d, SALT, 0.2) for d in IDS])

    def test_order_of_deals_does_not_matter(self):
        forward = dict(assign(IDS, SALT, 0.2))
        backward = dict(assign(list(reversed(IDS)), SALT, 0.2))
        self.assertEqual(forward, backward)

    def test_different_salt_changes_assignment(self):
        self.assertNotEqual(
            [is_held_out(d, SALT, 0.2) for d in IDS],
            [is_held_out(d, "other-salt", 0.2) for d in IDS],
        )

    def test_share_zero_holds_out_nobody(self):
        self.assertFalse(any(is_held_out(d, SALT, 0) for d in IDS))

    def test_share_one_holds_out_everybody(self):
        self.assertTrue(all(is_held_out(d, SALT, 1) for d in IDS))
        self.assertTrue(all(is_held_out(d, SALT, 1.0) for d in IDS))

    def test_observed_share_close_to_requested(self):
        for share in (0.1, 0.2, 0.5):
            observed = sum(is_held_out(d, SALT, share) for d in IDS) / len(IDS)
            self.assertAlmostEqual(observed, share, delta=0.03)

    def test_rejects_share_out_of_range_or_not_a_number(self):
        for share in (-0.01, 1.01, float("nan"), float("inf"), "0.2", None, True):
            with self.subTest(share=share), self.assertRaises(ValueError):
                is_held_out("D-1", SALT, share)

    def test_rejects_empty_salt_or_deal_id(self):
        for deal_id, salt in (("", SALT), ("  ", SALT), (None, SALT), ("D-1", ""), ("D-1", " "), ("D-1", None)):
            with self.subTest(deal_id=deal_id, salt=salt), self.assertRaises(ValueError):
                is_held_out(deal_id, salt, 0.2)


class FileTest(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.dir = Path(self._tmp.name)
        self.addCleanup(self._tmp.cleanup)
        self.salt_file = self.dir / "salt.txt"
        self.params_file = self.dir / "salt.params.json"

    def deals(self, text, name="deals.csv"):
        path = self.dir / name
        path.write_text(text, encoding="utf-8")
        return path

    def read_out(self, path):
        with open(path, newline="", encoding="utf-8") as f:
            return list(csv.reader(f))


class LoadDealIdsTest(FileTest):
    def test_dedupes_keeping_first_seen_order(self):
        path = self.deals("merchant_id,deal_id\nM-1,D-3\nM-2, D-1 \nM-3,D-3\nM-4,D-2\nM-5,D-1\n")
        self.assertEqual(load_deal_ids(path), ["D-3", "D-1", "D-2"])

    def test_tolerates_bom_and_spaced_header(self):
        path = self.dir / "bom.csv"
        path.write_text("﻿ deal_id ,x\nD-1,a\n", encoding="utf-8")
        self.assertEqual(load_deal_ids(path), ["D-1"])

    def test_header_only_is_an_error(self):
        with self.assertRaisesRegex(ValueError, "no deals"):
            load_deal_ids(self.deals("deal_id\n"))

    def test_missing_deal_id_column_is_a_clear_error(self):
        with self.assertRaisesRegex(ValueError, "missing column deal_id"):
            load_deal_ids(self.deals("id,merchant_id\nD-1,M-1\n"))

    def test_empty_file_is_missing_column(self):
        with self.assertRaisesRegex(ValueError, "missing column deal_id"):
            load_deal_ids(self.deals(""))

    def test_blank_line_in_single_column_file_names_the_line(self):
        with self.assertRaisesRegex(ValueError, "line 3: empty deal_id"):
            load_deal_ids(self.deals("deal_id\nD-1\n\nD-2\n"))

    def test_spaces_only_deal_id_names_the_line(self):
        with self.assertRaisesRegex(ValueError, "line 3: empty deal_id"):
            load_deal_ids(self.deals("deal_id\nD-1\n  \n"))

    def test_short_row_is_an_empty_deal_id(self):
        with self.assertRaisesRegex(ValueError, "line 3: empty deal_id"):
            load_deal_ids(self.deals("merchant_id,deal_id\nM-1,D-1\nM-2\n"))

    def test_line_number_counts_quoted_newlines(self):
        with self.assertRaisesRegex(ValueError, "line 4: empty deal_id"):
            load_deal_ids(self.deals('deal_id,note\nD-1,"a\nb"\n,x\n'))


class RunTest(FileTest):
    def test_writes_assignment_salt_and_params(self):
        deals = self.deals("deal_id\nD-2\nD-1\nD-2\n")
        out = self.dir / "assignments.csv"
        groups = run(deals, out, self.salt_file, 0.5, today=TODAY)

        salt = self.salt_file.read_text(encoding="utf-8").strip()
        self.assertRegex(salt, r"^[0-9a-f]{32}$")
        self.assertEqual(stat.S_IMODE(self.salt_file.stat().st_mode), 0o600)
        expected = [(d, HELD_OUT if is_held_out(d, salt, 0.5) else SUPPRESSED) for d in ("D-2", "D-1")]
        self.assertEqual(groups, expected)
        self.assertEqual(
            self.read_out(out),
            [["deal_id", "group", "assigned_on"]] + [[d, g, "2026-11-16"] for d, g in expected],
        )
        self.assertEqual(
            json.loads(self.params_file.read_text(encoding="utf-8")),
            {"share": 0.5, "salt_file": "salt.txt", "assigned_on": "2026-11-16"},
        )

    def test_reuses_existing_salt(self):
        self.salt_file.write_text(SALT + "\n", encoding="utf-8")
        groups = run(self.deals("deal_id\n" + "\n".join(IDS[:300])), self.dir / "a.csv", self.salt_file, 0.2)
        self.assertEqual(self.salt_file.read_text(encoding="utf-8"), SALT + "\n")
        self.assertEqual(groups, assign(IDS[:300], SALT, 0.2))

    def test_second_run_with_same_salt_and_share_gives_same_groups_and_keeps_params(self):
        deals = self.deals("deal_id\n" + "\n".join(IDS[:300]))
        first = run(deals, self.dir / "a.csv", self.salt_file, 0.2, today=TODAY)
        second = run(deals, self.dir / "b.csv", self.salt_file, 0.2, today=date(2026, 11, 20))
        self.assertEqual(first, second)
        self.assertEqual(json.loads(self.params_file.read_text(encoding="utf-8"))["assigned_on"], "2026-11-16")

    def test_two_salt_files_in_one_folder_are_independent(self):
        deals = self.deals("deal_id\nD-1\n")
        run(deals, self.dir / "a.csv", self.dir / "a.txt", 0.2)
        run(deals, self.dir / "b.csv", self.dir / "b.txt", 0.3)
        self.assertEqual(json.loads((self.dir / "a.params.json").read_text(encoding="utf-8"))["share"], 0.2)
        self.assertEqual(json.loads((self.dir / "b.params.json").read_text(encoding="utf-8"))["share"], 0.3)

    def test_refuses_params_of_another_salt_file(self):
        deals = self.deals("deal_id\nD-1\n")
        run(deals, self.dir / "a.csv", self.salt_file, 0.2)
        other = self.dir / "salt.dat"
        other.write_text(SALT + "\n", encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "does not belong to salt.dat"):
            run(deals, self.dir / "b.csv", other, 0.2)
        self.assertFalse((self.dir / "b.csv").exists())

    def test_refuses_to_overwrite_output(self):
        out = self.dir / "assignments.csv"
        out.write_text("earlier\n", encoding="utf-8")
        with self.assertRaisesRegex(FileExistsError, "written once"):
            run(self.deals("deal_id\nD-1\n"), out, self.salt_file, 0.2)
        self.assertEqual(out.read_text(encoding="utf-8"), "earlier\n")
        self.assertFalse(self.salt_file.exists())
        self.assertFalse(self.params_file.exists())

    def test_refuses_salt_recorded_with_another_share(self):
        deals = self.deals("deal_id\nD-1\n")
        run(deals, self.dir / "a.csv", self.salt_file, 0.2)
        with self.assertRaisesRegex(ValueError, "share 0.2, not 0.3"):
            run(deals, self.dir / "b.csv", self.salt_file, 0.3)
        self.assertFalse((self.dir / "b.csv").exists())

    def test_refuses_params_without_salt(self):
        self.params_file.write_text(json.dumps({"share": 0.2, "salt_file": "salt.txt"}), encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "missing"):
            run(self.deals("deal_id\nD-1\n"), self.dir / "a.csv", self.salt_file, 0.2)
        self.assertFalse(self.salt_file.exists())

    def test_refuses_empty_or_damaged_salt_file(self):
        for content in ("", "  \n", SALT[:16] + "\n", SALT.upper() + "\n"):
            with self.subTest(content=content):
                self.salt_file.write_text(content, encoding="utf-8")
                with self.assertRaisesRegex(ValueError, "salt file is empty or damaged"):
                    run(self.deals("deal_id\nD-1\n"), self.dir / "a.csv", self.salt_file, 0.2)
                self.assertFalse((self.dir / "a.csv").exists())

    def test_refuses_invalid_share_before_touching_files(self):
        with self.assertRaises(ValueError):
            run(self.deals("deal_id\nD-1\n"), self.dir / "a.csv", self.salt_file, 1.5)
        self.assertFalse(self.salt_file.exists())
        self.assertFalse((self.dir / "a.csv").exists())

    def test_missing_column_writes_nothing(self):
        with self.assertRaisesRegex(ValueError, "missing column deal_id"):
            run(self.deals("id\nD-1\n"), self.dir / "a.csv", self.salt_file, 0.2)
        self.assertFalse(self.salt_file.exists())
        self.assertFalse(self.params_file.exists())
        self.assertFalse((self.dir / "a.csv").exists())

    def test_failed_output_write_leaves_nothing_that_blocks_a_rerun(self):
        deals = self.deals("deal_id\nD-1\nD-2\n")
        out = self.dir / "a.csv"
        broken = mock.Mock()
        broken.writerows.side_effect = OSError("disk full")
        with mock.patch("holdout.assign.csv.writer", return_value=broken), self.assertRaises(OSError):
            run(deals, out, self.salt_file, 0.2)
        self.assertFalse(out.exists())
        self.assertFalse(self.params_file.exists())
        run(deals, out, self.salt_file, 0.3)
        self.assertTrue(out.exists())

    def test_output_that_cannot_be_opened_removes_new_params(self):
        with self.assertRaises(FileNotFoundError):
            run(self.deals("deal_id\nD-1\n"), self.dir / "missing" / "a.csv", self.salt_file, 0.2)
        self.assertFalse(self.params_file.exists())

    def test_failed_output_keeps_params_recorded_earlier(self):
        deals = self.deals("deal_id\nD-1\n")
        run(deals, self.dir / "a.csv", self.salt_file, 0.2)
        with self.assertRaises(FileNotFoundError):
            run(deals, self.dir / "missing" / "b.csv", self.salt_file, 0.2)
        self.assertTrue(self.params_file.exists())


class MainTest(FileTest):
    def call(self, *argv):
        out, err = io.StringIO(), io.StringIO()
        with redirect_stdout(out), redirect_stderr(err):
            code = main(list(argv))
        return code, out.getvalue(), err.getvalue()

    def test_reports_share_and_counts_on_stderr_and_never_prints_salt(self):
        self.salt_file.write_text(SALT + "\n", encoding="utf-8")
        deals = self.deals("deal_id\n" + "\n".join(IDS[:1000]))
        code, out, err = self.call(str(deals), "--out", str(self.dir / "a.csv"), "--salt-file", str(self.salt_file))
        held = sum(1 for _, g in assign(IDS[:1000], SALT, 0.2) if g == HELD_OUT)
        self.assertEqual(code, 0)
        self.assertEqual(out, "")
        self.assertIn(f"share 0.2: {held} held_out, {1000 - held} suppressed", err)
        self.assertNotIn(SALT, err)

    def test_refusal_is_an_error_exit(self):
        out_file = self.dir / "a.csv"
        out_file.write_text("x\n", encoding="utf-8")
        code, out, err = self.call(
            str(self.deals("deal_id\nD-1\n")), "--out", str(out_file), "--salt-file", str(self.salt_file)
        )
        self.assertEqual(code, 1)
        self.assertIn("written once", err)

    def test_missing_deals_file_is_an_error_exit(self):
        code, _, err = self.call(
            str(self.dir / "nope.csv"), "--out", str(self.dir / "a.csv"), "--salt-file", str(self.salt_file)
        )
        self.assertEqual(code, 1)
        self.assertIn("error:", err)


if __name__ == "__main__":
    unittest.main()
