# Hostinger staging deployment

This legacy shared-hosting workflow is archived. Current deployments must use
the VPS Docker environment at `https://travis-nasugbu.tech/`.

After extracting the archive in Hostinger File Manager:

1. Copy `config/local.php.example` to `config/local.php`.
2. Replace only `REPLACE_WITH_HOSTINGER_DATABASE_PASSWORD` with the database
   password stored in Hostinger.
3. Do not share or download `config/local.php` after it contains the password.
4. Visit `/TRAVIS/` and test the landing page and login.

The archive intentionally excludes Python, ML models, mobile sources,
database dumps, tests, private runtime uploads, and service credentials.
Public announcement images under `assets/uploads/announcements` remain included
because the imported database may reference them.
