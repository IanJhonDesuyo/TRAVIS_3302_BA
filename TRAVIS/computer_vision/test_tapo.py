import cv2

USERNAME = "travis"
PASSWORD = "Travis@123"
IP = "192.168.1.23"

rtsp_url = f"rtsp://{USERNAME}:{PASSWORD}@{IP}:554/stream2"

print("Connecting to:", rtsp_url)

cap = cv2.VideoCapture(rtsp_url)

if not cap.isOpened():
    print("❌ Cannot connect to camera.")
    exit()

print("✅ Connected successfully!")

while True:
    ret, frame = cap.read()

    if not ret:
        print("Cannot read frame.")
        break

    cv2.imshow("TRAVIS TAPO Camera", frame)

    if cv2.waitKey(1) == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()