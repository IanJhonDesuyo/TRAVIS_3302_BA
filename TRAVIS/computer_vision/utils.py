"""
Common helper functions used by TRAVIS
"""


def point_side(point, line):

    x, y = point

    (x1, y1), (x2, y2) = line

    return (x - x1) * (y2 - y1) - (y - y1) * (x2 - x1)


def crossed_line(previous_point, current_point, line):

    previous = point_side(
        previous_point,
        line
    )

    current = point_side(
        current_point,
        line
    )

    return previous * current < 0