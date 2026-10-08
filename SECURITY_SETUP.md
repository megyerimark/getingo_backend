# Getingo backend – aktuális production és security beállítás

Ez a dokumentum a jelenlegi kódhoz tartozik. A hitelesítés elsődleges módja first-party Angular SPA esetén **Laravel Sanctum session-cookie + CSRF**.

## 1. Adatbázis-frissítés

Meglévő környezetben:

```bash
php artisan migrate --force
```

Éles vagy megőrzendő adatbázison **ne** használj `migrate:fresh`, `migrate:reset` vagy hasonló adatromboló parancsot. Migráció előtt készíts ellenőrzött mentést.

## 2. Production `.env`

Kiindulási minta: `.env.production.example`. A repository MySQL + database session/cache beállítást mutat, mert ehhez nem kell külön Redis infrastruktúra. Redis használható és nagyobb terhelésnél ajánlott, de nem kötelező.

Minimum:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://getingo.hu
FRONTEND_URL=https://getingo.hu

DB_CONNECTION=mysql
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database

SANCTUM_STATEFUL_DOMAINS=getingo.hu,www.getingo.hu
CORS_ALLOWED_ORIGINS=https://getingo.hu,https://www.getingo.hu
```

Az `.env` soha ne kerüljön Gitbe.

## 3. Sanctum SPA hitelesítés

A frontend kérésmenete:

1. `GET /sanctum/csrf-cookie`
2. login / register kérés `withCredentials: true`
3. védett API-k session cookie-val és CSRF-védelemmel

Ne építs új funkciót `localStorage`-ban tárolt bearer tokenre. A backend továbbra is a Sanctum csomagot használja, de a Getingo saját Angular kliensének kanonikus megoldása a session-cookie auth.

## 4. Admin érzékeny műveletek

Az audit-export és a törlési bizonyíték külön rövid életű admin újrahitelesítést kér (`POST /api/admin/sensitive/unlock`). Felhasználó manuális törléséhez kötelező az indok, opcionálisan privát bizonyítékfájl csatolható. A fájl nem publikus storage-ban marad.

## 5. Fióktörlés és fizetési adatok

Fióktörléskor a munkamenetek, személyes tanulási adatok és a felhasználói fiók törlődnek. A `subscription_payments` rekordokat a kód nem törli automatikusan; a user kapcsolat nullázható. A tényleges számviteli megőrzési időt és az anonimizálás részleteit könyvelővel/jogi szakemberrel kell véglegesíteni.

## 6. Jelszó-visszaállítás

Sikeres reset után a backend visszavonja a Sanctum tokeneket és database session driver esetén minden korábbi felhasználói sessiont. Productionben ezért a `SESSION_DRIVER=database` (vagy olyan központi store, amelyből a sessionök visszavonhatók) javasolt.

## 7. Stripe webhook

A webhook aláírás ellenőrzött. Az eseményazonosítók naplózva vannak az ismételt feldolgozás ellen, subscription/invoice eseménynél pedig a backend az aktuális Stripe objektumot kérdezi le, nem bízik kizárólag a késve érkező event snapshotban.

Kötelező:

```env
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_PREMIUM_MONTHLY_PRICE_ID=
STRIPE_PREMIUM_YEARLY_PRICE_ID=
```

Csak a konfigurált Getingo price ID-k adhatnak Premium jogosultságot.

## 8. Kódfuttató

A `POST /api/code/run` bejelentkezéshez + email-hitelesítéshez kötött és user-alapú perces/órás/napi limittel védett. Publikus közösségi Judge0 végpont nincs alapértelmezve. Productionben saját/dedikált sandboxot vagy megfelelő kvótás szolgáltatást konfigurálj.

A Laravel/PHP folyamat soha ne futtasson közvetlenül felhasználói forráskódot `exec`, `shell_exec` vagy hasonló hívással.

## 9. Reverse proxy és rate limit

Ajánlott topológia:

```text
Internet → CDN/WAF → Nginx → Laravel/PHP-FPM → DB/cache
```

Külön WAF/rate limit ajánlott login, register, password reset, search, quiz és code-runner végpontokra. `TRUSTED_PROXIES=*` ne legyen beállítva közvetlenül internetről elérhető origin szerveren.

## 10. Scheduler, queue, mentés, monitoring

Scheduler:

```cron
* * * * * cd /var/www/getingo && php artisan schedule:run >> /dev/null 2>&1
```

Ha `QUEUE_CONNECTION=database`, futtass queue workert is. Legyen rendszeres adatbázis- és privát storage-mentés, visszaállítási próba, log/hibafigyelés és riasztás.

## 11. Release ellenőrzés

```bash
php artisan app:doctor
php artisan route:list
php artisan test
```

A release előtt külön ellenőrizd: SMTP, Stripe webhook, Sanctum cookie domain, HTTPS, CORS, storage jogosultság, scheduler/queue, code-runner és backup restore.
