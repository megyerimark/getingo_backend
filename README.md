# Getingo API

A Getingo Laravel 13 backendje. Ez a repository kizárólag az API-t tartalmazza; Angular build, `dist/` és `.angular/` cache nem kerülhet bele.

## Követelmények

- PHP 8.3+
- Composer
- MySQL/MariaDB, PostgreSQL vagy fejlesztéshez SQLite
- működő SMTP szolgáltatás az email-verifikációhoz és jelszó-visszaállításhoz
- Stripe kulcsok csak akkor, ha a Premium számlázás aktív
- sandboxolt kódfuttató (Judge0 / OneCompiler / Piston) a Python, C# és SQL futtatáshoz

## Helyi indítás

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

A frontend alapértelmezetten `http://localhost:4200`, az API `http://localhost:8000`. A first-party Angular kliens **Sanctum session-cookie + CSRF** hitelesítést használ; nem kell access tokent `localStorage`-ban tárolni.

Az adatbázist meglévő adatok mellett mindig normál migrációval frissítsd:

```bash
php artisan migrate --force
```

Production adatbázison ne használd a `migrate:fresh` parancsot.

## Email és queue

Regisztráció után a fiók létrejötte és az emailküldés külön hibakezelést kapott: SMTP-hiba nem fordítja vissza tévesen a sikeres regisztrációt 500-as válasszá. Jelszó-visszaállításnál levélküldési hiba 503-as választ ad. Productionben állíts be valódi SMTP-t és futó queue workert, ha a notificationöket sorba állítod.

## Kódfuttatás

A `POST /api/code/run` csak bejelentkezett, emailben hitelesített felhasználónak érhető el és felhasználónként limitált. A repository szándékosan **nem** állít be publikus Judge0 végpontot alapértelmezésként. Legalább egy szolgáltatót konfigurálj:

```env
CODE_RUNNER_PYTHON_PROVIDER=judge0
CODE_RUNNER_CSHARP_PROVIDER=onecompiler
CODE_RUNNER_SQL_PROVIDER=onecompiler
CODE_RUNNER_FALLBACK_PROVIDER=judge0

JUDGE0_URL=https://sajat-jail.example.com
JUDGE0_AUTH_TOKEN=
ONECOMPILER_URL=https://api.onecompiler.com/v1
ONECOMPILER_API_KEY=
PISTON_URL=
PISTON_AUTH_TOKEN=
```

Hosszú távon saját vagy dedikált Judge0/Piston példány javasolt.

## Admin és karbantartás

Admin létrehozása:

```bash
php artisan getingo:create-admin admin@example.com --name="Administrator"
```

Production ellenőrzés:

```bash
php artisan app:doctor
php artisan route:list
```

A scheduler fusson percenként, mert a Sanctum token és audit-retention takarítás erre épül:

```cron
* * * * * cd /var/www/getingo && php artisan schedule:run >> /dev/null 2>&1
```

## Tesztek

```bash
php artisan test
```

## Telepítés

A részletes biztonsági és production beállításokat a `SECURITY_SETUP.md`, a minta environmentet a `.env.production.example`, a webszerver mintákat a `deploy/` tartalmazza.
