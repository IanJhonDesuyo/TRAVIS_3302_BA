import unittest
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))

from direction_counter import DirectionCounter


class DirectionCounterTests(unittest.TestCase):
    def setUp(self):
        self.counter = DirectionCounter(
            inbound_line=((0, 100), (200, 100)),
            outbound_line=((0, 50), (200, 50)),
        )

    def test_bottom_to_top_crossing_inbound_line_counts_inbound(self):
        for point in [(100, 140), (100, 115), (100, 95), (100, 80)]:
            self.counter.update(1, point)

        self.assertEqual(self.counter.snapshot(), (1, 0))

    def test_top_to_bottom_crossing_outbound_line_counts_outbound(self):
        for point in [(100, 20), (100, 40), (100, 55), (100, 75)]:
            self.counter.update(2, point)

        self.assertEqual(self.counter.snapshot(), (0, 1))

    def test_track_cannot_be_counted_in_both_directions(self):
        for point in [(100, 140), (100, 95), (100, 40), (100, 55)]:
            self.counter.update(3, point)

        self.assertEqual(self.counter.snapshot(), (1, 0))

    def test_bottom_to_top_crossing_outbound_line_is_not_inbound(self):
        self.counter.update(11, (100, 70))
        self.counter.update(11, (100, 40))

        self.assertEqual(self.counter.snapshot(), (0, 0))

    def test_top_to_bottom_crossing_inbound_line_is_not_outbound(self):
        self.counter.update(12, (100, 80))
        self.counter.update(12, (100, 120))

        self.assertEqual(self.counter.snapshot(), (0, 0))

    def test_wrong_direction_on_dedicated_lines_is_ignored(self):
        self.counter.update(13, (100, 80))
        self.counter.update(13, (100, 120))
        self.counter.update(14, (100, 70))
        self.counter.update(14, (100, 30))

        self.assertEqual(self.counter.snapshot(), (0, 0))

    def test_diagonal_lines_do_not_depend_on_endpoint_order(self):
        counter = DirectionCounter(
            inbound_line=((180, 80), (20, 120)),
            outbound_line=((20, 40), (180, 60)),
        )
        counter.update(21, (100, 140))
        counter.update(21, (100, 80))
        counter.update(22, (100, 20))
        counter.update(22, (100, 80))

        self.assertEqual(counter.snapshot(), (1, 1))

    def test_small_line_jitter_is_not_counted(self):
        self.counter.update(31, (100, 103))
        self.counter.update(31, (100, 98))

        self.assertEqual(self.counter.snapshot(), (0, 0))


if __name__ == "__main__":
    unittest.main()
