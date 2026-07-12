"""
Common helper functions used by TRAVIS
"""


def orientation(p, q, r):
    value = (q[1] - p[1]) * (r[0] - q[0]) - \
            (q[0] - p[0]) * (r[1] - q[1])

    if abs(value) < 1e-6:
        return 0

    return 1 if value > 0 else 2


def on_segment(p, q, r):
    return (
        min(p[0], r[0]) <= q[0] <= max(p[0], r[0])
        and
        min(p[1], r[1]) <= q[1] <= max(p[1], r[1])
    )


def segments_intersect(p1, q1, p2, q2):

    o1 = orientation(p1, q1, p2)
    o2 = orientation(p1, q1, q2)
    o3 = orientation(p2, q2, p1)
    o4 = orientation(p2, q2, q1)

    if o1 != o2 and o3 != o4:
        return True

    if o1 == 0 and on_segment(p1, p2, q1):
        return True

    if o2 == 0 and on_segment(p1, q2, q1):
        return True

    if o3 == 0 and on_segment(p2, p1, q2):
        return True

    if o4 == 0 and on_segment(p2, q1, q2):
        return True

    return False


def crossed_line(previous_point, current_point, line):

    return segments_intersect(
        previous_point,
        current_point,
        line[0],
        line[1]
    )

def scale_line(normalized_line, width, height):
    return (
        (
            int(normalized_line[0][0] * width),
            int(normalized_line[0][1] * height)
        ),
        (
            int(normalized_line[1][0] * width),
            int(normalized_line[1][1] * height)
        )
    )