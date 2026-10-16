# TRAVIS VPS deployment

This package runs the TRAVIS web portal and APIs behind Caddy, with MariaDB and
the Random Forest/K-Means API on an internal Docker network. Neither MariaDB
nor the machine-learning API publishes a port to the internet.

The mobile application is intentionally not containerized. Build it locally
with its production API base set to the public HTTPS URL, for example:

```text
EXPO_PUBLIC_API_URL=https://travis-nasugbu.tech/api/
```

The gate/edge computer continues to run only the camera/YOLO computer-vision
workload and uses `TRAVIS_SERVICE_API_KEY` when calling protected VPS endpoints.

## First start

1. Copy `.env.example` to `.env` and replace every `CHANGE_TO_...` value.
2. Start with `TRAVIS_SITE_ADDRESS=:80` while testing through the VPS IP.
3. Run `docker compose up -d --build` from the package directory.
4. Check `docker compose ps` and `docker compose logs --tail=100 app ml db caddy`.
5. After DNS points to this VPS, set `TRAVIS_SITE_ADDRESS` to the domain and
   run `docker compose up -d`. Caddy will then obtain and renew HTTPS.

Database initialization scripts run only when the MariaDB volume is empty.
Back up the database and runtime directories before upgrades.

