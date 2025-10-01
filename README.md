# WIS4 Document Workflow Application

A containerized PoC for a document workflow with two roles: Administrator (task management) and Collaborator (task execution).

- Backend: CodeIgniter 4 (PHP) REST API
- Frontend: HTML + Bootstrap + Vanilla JS
- DB: MariaDB 10.9
- Object Storage: S3-compatible (S3 Ninja)
- Reverse Proxy: Nginx
- Orchestration: Docker Compose

## Quickstart (recommended)

1) Start everything and initialize the database (only if empty):

```bash
./run.sh
```

If you see a permission error, make it executable first:

```bash
chmod +x ./run.sh
./run.sh
```

2) Open the app:
- App: http://localhost
- S3 console (S3Ninja): http://localhost:9000

3) Login with demo accounts
- Administrator: admin@wis4.pt / password
- Collaborator: collaborator1@wis4.pt / password

The script waits for the database, then seeds it using `database/init.sql` only if the `users` table has no rows. It won’t duplicate sample data across runs.

## Manual start (alternative)

```bash
# Build and start in background
# (Use `docker compose` if your Docker supports it; otherwise `docker-compose`)
docker-compose up -d --build

# Wait ~1–2 minutes for MariaDB initialization (first run only)
# The file database/init.sql is applied automatically on the first run by MariaDB’s init process.
```

Services:
- http://localhost → Nginx (frontend + reverse proxy to backend)
- http://localhost:9000 → S3Ninja

## Project structure

```
backend/            # CodeIgniter 4 API
frontend/           # UI (index.html, dashboard.html)
database/init.sql   # Tables + demo data (idempotent table DDL; demo inserts only if empty via run.sh)
nginx/nginx.conf    # Reverse proxy config
docker-compose.yml  # Stack definition
run.sh              # Helper script (up + DB init if empty)
```

## Environment (from docker-compose)

Backend container env:
- DB_HOST=mariadb
- DB_USER=wis4_user
- DB_PASS=wis4_password
- DB_NAME=wis4_db
- S3_ENDPOINT=http://s3ninja:9000
- S3_PUBLIC_ENDPOINT=http://localhost:9000
- S3_BUCKET=wis4-documents
- S3_REGION=us-east-1
- S3_KEY=AKIAIOSFODNN7EXAMPLE
- S3_SECRET=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
- S3_USE_PATH_STYLE=true

DB container env:
- MYSQL_ROOT_PASSWORD=rootpassword
- MYSQL_DATABASE=wis4_db
- MYSQL_USER=wis4_user
- MYSQL_PASSWORD=wis4_password

## Database and data

Schema and demo data live in `database/init.sql`:
- Tables: users, tasks, task_execution (with FKs)
- Demo users (admin and collaborators) with password `password`
- Demo tasks for collaborators

Initialization behavior:
- On the very first DB startup, MariaDB runs `init.sql` automatically.
- The helper script `run.sh` re-seeds only if the `users` table is empty (prevents duplicates).

## Common URLs

- App: http://localhost
- S3 console (S3Ninja): http://localhost:9000

## Troubleshooting

- Ports in use (80/3306/9000): stop conflicting services or change ports.
- Check logs:
  ```bash
  docker-compose logs --no-color | less
  docker-compose logs backend
  docker-compose logs mariadb
  ```
- DB not ready yet: give it ~60–120 seconds on the first run. The script waits automatically.
- File upload issues: ensure S3Ninja is up on 9000; backend creates bucket on demand.

## Security notes (PoC)

- Demo passwords are public; change for real deployments.
- Consider HTTPS, CSRF, JWT, validation hardening, and rate limiting before production use.

## License

This repository is intended for PoC and evaluation.
