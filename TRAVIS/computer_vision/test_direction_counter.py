import unittest
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))

from direction_counter import DirectionCounter


class DirectionCounterTests(unittest.TestCase):
    def setUp(self):
        self.counter = DirectionCounter(
            inbound_line=((50, 0), (50, 200)),
            outbound_line=((100, 0), (100, 200)),
        )

    def test_leftward_vehicle_counts_only_as_inbound(self):
        for point in [(120, 100), (90, 100), (60, 100), (40, 100)]:
            self.counter.update(1, point)

        self.assertEqual(self.counter.snapshot(), (1, 0))

    def test_rightward_vehicle_counts_only_as_outbound(self):
        for point in [(30, 100), (60, 100), (90, 100), (110, 100)]:
            self.counter.update(2, point)

        self.assertEqual(self.counter.snapshot(), (0, 1))

    def test_track_cannot_be_counted_in_both_directions(self):
        for point in [(120, 100), (90, 100), (60, 100), (40, 100),
                      (60, 100), (90, 100), (110, 100)]:
            self.counter.update(3, point)

        self.assertEqual(self.counter.snapshot(), (1, 0))

    def test_leftward_crossing_either_gate_is_inbound(self):
        self.counter.update(11, (120, 100))
        self.counter.update(11, (90, 100))

        self.assertEqual(self.counter.snapshot(), (1, 0))

    def test_rightward_crossing_either_gate_is_outbound(self):
        self.counter.update(12, (30, 100))
        self.counter.update(12, (60, 100))

        self.assertEqual(self.counter.snapshot(), (0, 1))


if __name__ == "__main__":
    unittest.main()
