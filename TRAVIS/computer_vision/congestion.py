"""
TRAVIS Congestion Detection Module
"""
def get_congestion_level(vehicle_count):
    if vehicle_count <= 5:
        return "Light"
    elif vehicle_count <= 12:
        return "Moderate"
    else:
        return "Heavy"
