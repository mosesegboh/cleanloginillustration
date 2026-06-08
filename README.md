# HFM Backend Landing Page Exercise

Laravel 13 + React implementation for the HF Markets backend landing page exercise.

## What Is Included

- Figma-matched register and login pages at `/register` and `/login`.
- Stateless JSON endpoints under `/api`, so terminal/API-client requests do not depend on cookies or browser sessions.
- SQLite persistence for signups.
- Server-side validation using Laravel Form Requests.
- Full country/calling-code metadata from `giggsey/libphonenumber-for-php`, exposed through an application provider interface and cached metadata decorator.
- DTO and repository interface for normalized signup writes.
- Feature flags for signup/login availability.
- Branded SVG favicon.
- Dockerized PHP, Composer, Node/Vite, and SQLite runtime.
- Laravel and react frame work for chosen for built-in security, scalability and maintainability purposes amongst other advantageous reasons otherwise pure php would have been used.

## Requirements

For local non-Docker setup:

- PHP `^8.3`
- Composer 2
- Node `^20.19.0` or `>=22.12.0`
- PHP extensions: `mbstring`, `pdo_sqlite`, `sqlite3`
- Laravel Valet only if you want the custom local domain flow

Docker can be used instead if your host PHP/Node extensions are not ready.

## Database Table Choice

Submitted registrations are stored in the `signups` table rather than Laravel's default `users` table. This project treats the page as a landing-page registration and credential-validation exercise, not a full Laravel authentication implementation with guards, sessions, remember tokens, password resets, or email verification.

The `signups` table keeps the assessment-specific fields together: name, country, country code, phone number, email, and password hash. The default `users` table remains from Laravel's starter migration but is intentionally unused by this landing-page registration flow.

If this were extended into a full production account system, the identity/authentication data should move into `users`, with landing-page or profile-specific fields either added through a profile table or a clearly named registration source table.

## Reviewer Notes

Git history is not required for this submission because the assessment specifies that the project files should be sent directly as a zipped folder. The zip is intended to contain the application source, lock files, Docker files, migrations, tests, `.env.example`, and built frontend assets, without relying on a Git remote or commit history for review.

## Security Notes

- Passwords are never stored in plain text. Signup passwords are hashed with Laravel's `Hash` facade before persistence.
- Login errors use a generic credential message so the API does not reveal whether an email address exists.
- Browser signup and login form submissions use Laravel's web routes with CSRF protection. The React app receives the CSRF token from the Blade shell and sends it as the `X-CSRF-TOKEN` header.
- API signup and login endpoints remain stateless JSON endpoints, so terminal/API-client requests do not depend on browser cookies or sessions.
- All submitted fields are normalized and validated server-side with Laravel Form Requests. Frontend validation is only for user experience.
- Arrays and objects submitted where scalar strings or booleans are expected are rejected as validation errors before casting, avoiding noisy PHP type coercion edge cases.
- First and last names use a strict server-side allowlist for human-name characters. Accepted name characters are letters, spaces, hyphens, apostrophes, and periods. URL-like values such as `https//:.`, markup, digits, slashes, colons, control characters, and suspicious punctuation are rejected before persistence.
- The `signups.email` column has a unique index, and duplicate insert races are caught and returned as validation-style errors.
- Signup and login API routes are rate-limited to reduce signup spam and brute-force login attempts.
- Signup includes a hidden honeypot field and a signed, cache-backed, one-time timing challenge to reduce basic robot submissions without relying on browser sessions.
- The `Signup` model hides the password hash from JSON serialization.
- The submission zip excludes `.env`, `vendor`, `node_modules`, runtime logs, sessions, cache files, and compiled local state.
- The included `.env.example` and Docker Compose settings are for local assessment testing. For production, use `APP_DEBUG=false`, generate a private `APP_KEY`, set a production `APP_URL`, and provide environment-specific secrets outside the repository.
- Signup challenge signing fails closed outside the test environment if `APP_KEY` is missing.

## Signup Anti-Spam Flow

The browser registration form receives a signed sessionless challenge when `/register` is rendered. That challenge is submitted with the form and validated by the backend. The challenge nonce is stored in cache and consumed after a successful validation, so the same challenge cannot be replayed. This adds basic protection against scripted form posts while keeping the API usable from terminal clients without cookies or sessions.

The signup anti-spam checks are:

- Hidden honeypot field: real users do not see or fill `companyWebsite`; basic bots often fill it and are rejected.
- Timing challenge: submissions that arrive too quickly after challenge creation are rejected.
- Signed challenge token: clients cannot forge or tamper with the challenge payload without the application key.
- One-time nonce: issued challenge nonces are cached and consumed during validation so replayed signup attempts are rejected.
- Challenge lifetime: old challenge tokens expire after `SIGNUP_CHALLENGE_LIFETIME_SECONDS`.
- Rate limiting: signup has both email/IP and IP-only limits to reduce spam with rotating email addresses.

For API clients, call `GET /api/signup-challenge`, wait at least `SIGNUP_CHALLENGE_MINIMUM_SECONDS`, then include the returned `signupStartedAt`, `signupChallengeNonce`, and `signupChallengeToken` fields in `POST /api/signups`.

## Test From The Zipped Submission Without Docker

Use this flow if you receive the submitted zip and want to run it directly on your machine.

System requirements:

- PHP `^8.3`
- Composer 2
- Node `^20.19.0` or `>=22.12.0`
- PHP extensions: `mbstring`, `pdo_sqlite`, `sqlite3`
- SQLite support enabled in PHP CLI

Setup from the directory where the zip file was downloaded:

```bash
unzip hfm-landing-page.zip
cd hfm-landing-page

composer install
npm ci

cp .env.example .env
remember to update the path to the sqlite file location, local to your machine
php artisan key:generate
mkdir -p database
touch database/database.sqlite
php artisan migrate

npm run build
php artisan serve --host=127.0.0.1 --port=8001
```

The submitted zip should include `.env.example`, not `.env`. The reviewer creates their own `.env` with `cp .env.example .env`, then generates a fresh local `APP_KEY`.

If you are testing the zip on another machine, especially macOS, do not reuse a `.env` copied from the original developer machine. A copied `.env` can contain an absolute SQLite path from another computer, such as `/home/moses/Downloads/...`, which will make migrations fail on the new machine.

By default, this project works without setting `DB_DATABASE` because Laravel uses `database/database.sqlite`. If you do add `DB_DATABASE` to `.env`, update it to the reviewer machine's absolute path, for example:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/Users/reviewer/path/to/hfm-landing-page/database/database.sqlite
```

If `php artisan migrate` says `Nothing to migrate`, that is fine only when the current machine already has a migrated SQLite database for this project. For a clean review, delete any copied `database/database.sqlite`, create a fresh empty file, and run migrations again.

Open:

```text
http://127.0.0.1:8001/register
http://127.0.0.1:8001/login
```

Run checks from the project root, meaning the folder that contains `artisan`, `composer.json`, `package.json`, and `docker-compose.yml`:

```bash
php artisan test --display-warnings
vendor/bin/pint --test
npm run build
```

## Local Setup With Laravel Valet

Run these commands from the project root:

```bash
cd /home/moses/Downloads/myProjects/hfm/hfm-landing-page

composer install
npm ci

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

npm run build
valet link hfm-landing
```

Open:

```text
http://hfm-landing.test/register
http://hfm-landing.test/login
```

Optional HTTPS:

```bash
valet secure hfm-landing
```

Then use:

```text
https://hfm-landing.test/register
```

## Local Setup Without Valet

Use this fallback if Valet is not installed:

Run these commands from the project root:

```bash
cd /home/moses/Downloads/myProjects/hfm/hfm-landing-page

composer install
npm ci

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

npm run build
php artisan serve --host=127.0.0.1 --port=8001
```

Open:

```text
http://127.0.0.1:8001/register
http://127.0.0.1:8001/login
```

## Docker Setup

Run Docker commands from the project root, meaning the folder that contains `docker-compose.yml`:

```bash
cd /home/moses/Downloads/myProjects/hfm/hfm-landing-page
docker compose up -d --build
```

Open:

```text
http://localhost:8000/register
http://localhost:8000/login
```

The container creates and migrates the SQLite database automatically at:

```text
/var/www/html/storage/database/database.sqlite
```

Useful Docker commands, also from the project root:

```bash
docker compose ps
docker compose logs app
docker compose down
```

## Possible Error Messages And Solutions

### SQLite database file does not exist

Error:

```text
The SQLite database configured for this application does not exist
Database file at path [...] does not exist
```

Cause:

- For local non-Docker setup, SQLite needs an actual empty database file before migrations can run.
- If the path shown in the error points to another machine, such as `/home/moses/Downloads/...`, the copied `.env` contains a stale absolute `DB_DATABASE` path.
- If you are testing with Docker, do not run host `php artisan migrate` unless you are intentionally testing outside Docker. Docker uses its own SQLite path inside the container.

Local non-Docker fix:

```bash
cd hfm-landing-page
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
php artisan migrate
```

If `.env` already exists and contains an old absolute SQLite path, either remove the `DB_DATABASE=...` line or set it to the current machine's absolute path:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/Users/reviewer/path/to/hfm-landing-page/database/database.sqlite
```

The path must be the path on the machine currently running the project. Do not leave a path copied from another computer, such as `/home/moses/Downloads/...`.

On a new machine, the simplest reset is:

```bash
cd hfm-landing-page
rm .env
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
php artisan migrate
```

Docker fix:

```bash
cd hfm-landing-page
docker compose up -d --build
```

The Docker container creates and migrates its own SQLite database automatically at `/var/www/html/storage/database/database.sqlite`.

### Nothing to migrate

Message:

```text
INFO  Nothing to migrate.
```

Meaning:

- This is not an error. It means Laravel found the migrations table and all migration files have already been run against the current database.
- If you are reviewing a clean zipped submission, this usually means a previously migrated `database/database.sqlite` file is already present.

Recommended clean-review reset:

```bash
cd hfm-landing-page
rm -f database/database.sqlite
touch database/database.sqlite
php artisan migrate
```

Do not include a populated local SQLite database in the submitted zip unless the instructions explicitly ask for seeded data. This project is designed for the reviewer to create a fresh SQLite database locally.

## Tests And Checks

Local checks must be run from the project root:

```bash
cd /home/moses/Downloads/myProjects/hfm/hfm-landing-page
php artisan test --display-warnings
vendor/bin/pint --test
npm run build
```

Docker checks must also be run from the project root:

```bash
cd /home/moses/Downloads/myProjects/hfm/hfm-landing-page
docker compose build
docker compose run --rm app php artisan test --display-warnings
docker compose run --rm app vendor/bin/pint --test
```

## API Examples

Successful signup from an API client requires a sessionless signup challenge. Browser users receive this automatically when loading `/register`; terminal clients should request it first and include the returned challenge fields in the signup payload.

This example uses `jq` only to merge the challenge JSON into the payload:

```bash
BASE_URL=http://hfm-landing.test
SIGNUP_CHALLENGE="$(curl -sS "$BASE_URL/api/signup-challenge")"

sleep 2

curl -X POST "$BASE_URL/api/signups" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "$(jq -cn --argjson challenge "$SIGNUP_CHALLENGE" \
    '{"firstName":"Moses","lastName":"Egboh","country":"NG","countryCode":"234","phoneNumber":"8012345678","email":"moses@example.com","password":"Secure1!","acceptedTerms":true,"companyWebsite":""} + $challenge')"
```

Login validation:

```bash
BASE_URL=http://hfm-landing.test

curl -X POST "$BASE_URL/api/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"moses@example.com","password":"Secure1!"}'
```

For Docker, replace `http://hfm-landing.test` with `http://localhost:8000`.

Example rejected signup with suspicious name content:

```bash
BASE_URL=http://hfm-landing.test
SIGNUP_CHALLENGE="$(curl -sS "$BASE_URL/api/signup-challenge")"

sleep 2

curl -X POST "$BASE_URL/api/signups" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "$(jq -cn --argjson challenge "$SIGNUP_CHALLENGE" \
    '{"firstName":"https//:.","lastName":"Egboh","country":"NG","countryCode":"234","phoneNumber":"8012345678","email":"blocked-name@example.com","password":"Secure1!","acceptedTerms":true,"companyWebsite":""} + $challenge')"
```

This returns a `422` validation response and does not insert a signup row.

## Feature Flags

These can be toggled through environment variables:

```env
FEATURE_SIGNUP_ENABLED=true
FEATURE_LOGIN_ENABLED=true
SIGNUP_CHALLENGE_MINIMUM_SECONDS=2
SIGNUP_CHALLENGE_LIFETIME_SECONDS=7200
```

## Packaging For Submission

Build and test before zipping. Run these commands from the project root:

```bash
cd /home/moses/Downloads/myProjects/hfm/hfm-landing-page
npm run build
php artisan test --display-warnings
vendor/bin/pint --test
```

Then create a clean zip from the parent directory, so the archive contains the `hfm-landing-page` folder itself:

```bash
cd /home/moses/Downloads/myProjects/hfm

zip -r hfm-landing-page.zip hfm-landing-page \
  -x "hfm-landing-page/vendor/*" \
  -x "hfm-landing-page/node_modules/*" \
  -x "hfm-landing-page/.env" \
  -x "hfm-landing-page/database/database.sqlite" \
  -x "hfm-landing-page/storage/logs/*" \
  -x "hfm-landing-page/storage/framework/cache/*" \
  -x "hfm-landing-page/storage/framework/sessions/*" \
  -x "hfm-landing-page/storage/framework/views/*"
```

The zip keeps source code, lock files, Docker files, migrations, tests, `.env.example`, and built frontend assets, while excluding local dependencies, secrets, and local SQLite data.

Do not include `.env` in the submitted zip. It can contain local absolute paths, generated secrets, or environment-specific settings. The reviewer should run `cp .env.example .env` on their own machine.
