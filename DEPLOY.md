# Deploying CyberDrill (self-hosted, Cloudflare Tunnel)

CyberDrill runs as three Docker containers on one machine:

| Container | What it does |
|---|---|
| `app` | The Laravel site (built from `Dockerfile`). Runs `php artisan migrate --force` every time it starts. |
| `db` | PostgreSQL. Data lives in the `db-data` Docker volume. |
| `tunnel` | Cloudflare Tunnel. It connects out to Cloudflare and publishes the site at `https://cyberdrill.hpcs.my`. No port is opened on the machine or the router. |

Cloudflare provides the HTTPS certificate. The app trusts the proxy in front of it (`bootstrap/app.php`), so `$request->ip()` is the visitor's address from Cloudflare's `X-Forwarded-For` header.

## What you need

- A machine that stays on, with Docker:
  - Windows: Docker Desktop.
  - Linux: Docker Engine with the Compose plugin.
  - Docker Compose **2.23.1 or newer**, because the database password is passed as a Compose secret. Check with `docker compose version`.
- The Cloudflare account that manages `hpcs.my`.
- Outbound internet access. The tunnel uses port 7844 outbound.

## 1. Create the tunnel in Cloudflare

1. In the Cloudflare dashboard, go to **Networking → Tunnels → Create a tunnel**.
2. Choose **Cloudflared**, name it `cyberdrill`, and create it.
3. On the install step, choose **Docker**. Copy the long token that follows `--token` in the command shown. Don't run the command itself, because `compose.yaml` runs cloudflared for you.
4. Open the tunnel's **Routes** tab and choose **Add route → Published application**:
   - Subdomain: `cyberdrill`
   - Domain: `hpcs.my`
   - Path: leave empty
   - Service URL: `http://app:80`
5. Open **DNS → Records** for `hpcs.my` and check that there is a proxied `CNAME` named `cyberdrill` pointing to `<tunnel-id>.cfargotunnel.com`. If it's missing, add it with Proxy status **Proxied**.

## 2. Get the code and the settings onto the server

```sh
git clone https://github.com/wakanda8/CyberDrill.git
cd CyberDrill
cp .env.production.example .env.production
```

Edit `.env.production` and fill in these values:

- `DB_PASSWORD`: a long random password. On Linux, `openssl rand -hex 24` makes one. It must not be empty. If the `db` container stops with `/run/secrets/db_password: No such file or directory`, either `DB_PASSWORD` is empty or Docker Compose is older than 2.23.1.
- `CLOUDFLARE_TUNNEL_TOKEN`: the token from step 1.
- `APP_KEY`: generate it after `DB_PASSWORD` is set, with:

  ```sh
  docker compose --env-file .env.production run --rm --no-deps app php artisan key:generate --show
  ```

  The first run builds the image, which takes a few minutes. Paste the `base64:...` value it prints into `APP_KEY`.

Never commit `.env.production`; it is already in `.gitignore`.

## 3. Start the site

```sh
docker compose --env-file .env.production up -d --build
docker compose --env-file .env.production exec app php artisan db:seed --force
```

`db:seed` loads the phishing, malware and spam scenarios. In production it creates no admin account.

Then open `https://cyberdrill.hpcs.my`, register your own account, and make it an admin:

```sh
docker compose --env-file .env.production exec app php artisan app:make-admin you@example.com
```

## 4. Check it

- In Cloudflare, under **Networking → Tunnels**, the `cyberdrill` tunnel shows as healthy.
- `https://cyberdrill.hpcs.my/logout` shows a plain "Method Not Allowed" page. A page with a stack trace means `APP_DEBUG` is still on.
- **Per-visitor login limits:**
  1. On a phone using mobile data, fail a login 6 times. The 6th attempt must be blocked.
  2. Within the same minute, log in with the same email on Wi-Fi. It must work.
  3. If Wi-Fi is blocked too, the app is not seeing the real visitor IP. Switch it to Cloudflare's `CF-Connecting-IP` header.

## Updating to a new version

```sh
git pull
docker compose --env-file .env.production up -d --build
```

Migrations run automatically when the app starts. Run `db:seed --force` again only when the built-in scenarios change. It updates the built-in scenarios and keeps the ones admins added.

## Changing the database password

PostgreSQL keeps the password it was first created with, even if you later change `DB_PASSWORD`. Change it in the database first, then in the settings file:

1. Open a database shell:

   ```sh
   docker compose --env-file .env.production exec db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
   ```

2. Type `\password`, enter the new password twice, then type `\q`.
3. Put the same password in `DB_PASSWORD` in `.env.production`.
4. Run `docker compose --env-file .env.production up -d`.

## Backups

The database lives only in the `db-data` volume.

Make a backup:

```sh
docker compose --env-file .env.production exec db sh -c 'pg_dump --clean --if-exists -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /tmp/backup.sql'
docker compose --env-file .env.production cp db:/tmp/backup.sql ./backup.sql
```

`--clean --if-exists` makes the backup drop each table before recreating it, so it can be restored over the live database.

Restore a backup. The site is offline while the app is stopped:

```sh
docker compose --env-file .env.production stop app
docker compose --env-file .env.production cp ./backup.sql db:/tmp/backup.sql
docker compose --env-file .env.production exec db sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /tmp/backup.sql'
docker compose --env-file .env.production start app
```

If `psql` stops with an error, the restore did not finish. Fix the error and run the restore again before starting the app.

The dump is copied out of the container rather than redirected with `>`, because Windows PowerShell would save it as UTF-16. Never run `docker compose down -v`, because `-v` deletes the database volume.

## Keeping it online

The containers restart on their own (`restart: unless-stopped`) whenever Docker is running.

- **Windows:**
  - In Docker Desktop, go to **Settings → General** and turn on **Start Docker Desktop when you sign in**.
  - In Windows **Settings → System → Power**, set the PC to never sleep.
- **Linux:** run `sudo systemctl enable docker`.
