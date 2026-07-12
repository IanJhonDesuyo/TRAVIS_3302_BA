from ultralytics import YOLO

# Automatically downloads yolov8n.pt on first run
model = YOLO("yolov8n.pt")

print("YOLO model downloaded successfully!")