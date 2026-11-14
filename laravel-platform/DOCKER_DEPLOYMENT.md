# TWSD Connect — Docker Runtime Deployment Guide (`DOCKER_DEPLOYMENT.md`)

## 1. Prerequisites
- Docker Engine 24+ and Docker Compose v2+
- Minimum 2 vCPU, 4 GB RAM

## 2. Environment Configuration (Zero Committed Secrets)
```bash
cd laravel-platform
cp .env.example .env
```
Populate the following required variables in `.env` before starting containers:
- `DB_PASSWORD` & `DB_ROOT_PASSWORD` (strong random passwords)
- `REDIS_PASSWORD` (strong random password)
- `TWSD_SUPER_ADMIN_EMAIL` & `TWSD_SUPER_ADMIN_PASSWORD`
- `TWSD_INITIAL_ORG_ADMIN_EMAIL` & `TWSD_INITIAL_ORG_ADMIN_PASSWORD`

## 3. Build, Boot & Bootstrap
```bash
docker compose up -d --build
docker compose exec app ./scripts/install.sh
```

## 4. Run Full Automated Validation & PHPUnit Suite
```bash
docker compose exec app ./scripts/test.sh
./scripts/health-check.sh http://localhost:8080
```

## 5. Key Rotation & Operations
```bash
# Rotate RS256 OIDC signing key (keeps previous key in retiring status for JWKS verification)
docker compose exec app php artisan twsd:oidc:rotate-key

# Inspect queue worker logs
docker compose logs -f worker
```
