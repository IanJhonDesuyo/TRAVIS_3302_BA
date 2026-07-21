"""
TRAVIS Congestion Detection Module
"""
def get_congestion_level(vehicle_count, light_max=5, heavy_min=13):
    if vehicle_count <= light_max:
        return "Light"
    elif vehicle_count < heavy_min:
        return "Moderate"
    else:
        return "Heavy"
