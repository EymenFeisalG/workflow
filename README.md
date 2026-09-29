# Workflow

Ett snabbt, modulärt och responsivt ärende- och uppgiftshanteringssystem byggt i PHP och MySQL. Systemet är utformat för digitala byråer och företag för att hantera uppdrag, kontaktpersoner, checklistor, tidsrapportering och medarbetartilldelning i realtid.

---

## Innehållsförteckning
1. [Översikt & Teknikstack](#översikt--teknikstack)
2. [Katalogstruktur & Arkitektur](#katalogstruktur--arkitektur)
3. [Behörighetssystemet (Permissions & Privileges)](#behörighetssystemet-permissions--privileges)
4. [Databas & Datamodell](#databas--datamodell)
5. [Orderflöde & Gränssnitt](#orderflöde--gränssnitt)
6. [Utvecklingsriktlinjer & Regler](#utvecklingsriktlinjer--regler)
7. [Installation & Driftsättning](#installation--driftsättning)

---

## Översikt & Teknikstack

- **Backend**: PHP 8.0+ (Objektorienterat med procedur-endpoints för AJAX).
- **Databas**: MySQL / MariaDB via egen PDO/MySQLi-databaswrapper (`database.class.php`).
- **Frontend**: Mobile-first responsiv design, Vanilla JS & jQuery, ren CSS med flexbox/grid och relativa enheter (`rem`, `%`, `vw/vh`).
- **Webbserver**: IIS (`web.config`) eller Apache (`.htaccess`).
- **Inga tunga ramverk**: Snabb sidladdning utan kompileringsteg eller tunga externa npm/composer-beroenden i produktion.

---

## Katalogstruktur & Arkitektur

```text
workflow/
├── admin/                     # Administratörspanel
│   ├── inc/                   # Moduler (users.php, settings.php m.fl.)
│   └── index.php              # Admin-dashboard
├── DEBUG/                     # Sandlåda för testfiler, inspectors och UI-previews (git-ignorerad)
│   ├── preview_order_modal.html
│   └── test_order_system.php
├── php/
│   ├── classes/               # Kärnklasser (OOP)
│   │   ├── admin.class.php    # Användaradministration
│   │   ├── auth.class.php     # Autentisering, sessioner och behörighetskontroll
│   │   ├── database.class.php # Databasanslutning, escaping och PDO-querys
│   │   ├── mailer.class.php   # E-postnotifieringar (tilldelning, färdiga steg m.m.)
│   │   ├── main.class.php     # Huvudaffärslogik: orders, kontaktpersoner, navigering
│   │   └── taskCorrection.class.php # Behörig korrigering av uppgifter
│   └── functions/             # AJAX-endpoints
│       ├── addOrder.php       # Skapar ny uppgift med steg och bilagor
│       ├── getOrderForEdit.php # Läser aktiv uppgift för korrigering
│       ├── saveOrderCorrection.php # Sparar korrigerad uppgift
│       ├── submitOrderDecision.php # Attest/komplettering
│       ├── checkStep.php      # Avbockning av delmoment i checklistan
│       ├── deleteOrder.php    # Flyttar uppgift till papperskorgen
│       ├── focusOrder.php     # Sätter/rensar enskilt uppdrag i fokus i sessionen
│       ├── getOrders.php      # Hämtar uppdragskort asynkront baserat på filter
│       ├── logout.php         # Säker sessionsavslutning
│       └── searchCustomers.php# Snabb autocomplete-sökning för kontaktpersoner
├── resources/                 # Tredjepartsbibliotek (t.ex. TinyMCE för textredigering)
├── ui/
│   ├── js/
│   │   ├── app.js             # Huvudapplikationens event-hanterare och orderhämtning
│   │   ├── general.js         # Gemensamma UI-hjälpfunktioner och alert-modaler
│   │   └── orderModal.js      # Pappersmodalen: utkastsystem, autocomplete, checklistor
│   └── style/
│       ├── css/
│       │   ├── app.css        # Dashboard-layout och uppdragskort
│       │   ├── general.css    # Grundtypografi och modaler
│       │   └── orderModal.css # Pappersmodalens animationer, responsivitet och knappar
│       └── images/            # Ikoner och logotyper
├── changeorder.php            # Omdirigering från gamla redigeringslänkar
├── global.php                 # Applikationens bootstrapping (session, auth, main, underhållskontroll)
├── home.php                   # Huvudvy / Dashboard med uppdragsöversikt
├── Maintenance.php            # Landningssida vid aktivt underhållsläge
├── order.php                  # Enskild uppgiftsvisning
├── register.php               # Användarregistrering
├── settings.php               # Läser miljövariabler (.env) utan Composer
├── web.config                 # IIS URL Rewrite och säkerhetsregler
├── workflow.php               # Kanban- och prioriteringsvy
└── README.md                  # Projekt- och arkitekturdokumentation
```

---

## Behörighetssystemet (Permissions & Privileges)

Systemet använder en **tvånivås behörighetsmodell**: en övergripande basroll (`user_role`) och granulära rättigheter (`privileges`).

### 1. Basroller (`users.user_role`)
Varje användare i tabellen `users` har ett heltalsvärde:
- `1` = **Arbetare / Medarbetare**: Standardroll för utförare.
- `2` = **Admin / Projektledare**: Full administrativ roll.

### 2. Rättighetstabellen (`privileges`)
I tabellen `privileges` mappas ett `userid` mot specifika textnycklar (`privilege`).

| Rättighetsnyckel | Beskrivning & Påverkan i Gränssnittet |
|---|---|
| `admin` | Ger tillgång till administratörspanelen (`/admin/`). Kontrolleras i `admin/index.php`. |
| `add_new_order` | Ger behörighet att skapa nya uppgifter. Styr om knappen `Ny uppgift` visas i sidomenyn och om modalen initieras. |
| `orders_show_all` | Tillåter användaren att se alla uppdrag inom företaget, inte bara de som tilldelats användaren personligen. |
| `changeOrder` | Ger rätt att korrigera aktiva uppgifter som någon annan har skapat. Skaparen får alltid korrigera sin egen aktiva uppgift. |
| `deleteOrder` | Ger rätt att kasta uppdrag i papperskorgen eller radera dem. |
| `maintenanceLogin`| **Underhållsbehörighet**: Tillåter inloggning och arbete även när systemet är satt i underhållsläge. |
| `all` | **Superuser-wildcard**: Ger automatiskt sant (`true`) för alla standardanrop till `$auth->hasRight($right)`. |

### 3. Hur behörigheter kontrolleras i koden
Behörighetskontroll görs via `auth`-instansen:
```php
if ($auth->hasRight('add_new_order')) {
    // Visa knappen "Ny uppgift"
}
```

Metodsignaturen i `php/classes/auth.class.php`:
```php
public function hasRight($right, $allCheck = true)
```

### 4. Viktiga utvecklardetaljer och undantag

> [!IMPORTANT]
> **Sessionscachning av rättigheter**:
> När en användare loggar in (eller vid första sessionsinitiering via `auth::initRights()`) läses alla rader från `privileges` in och sparas i `$_SESSION['rights']` som en array.
> **Konsekvens**: Om du ändrar en användares rättigheter i databasen eller i adminpanelen måste användaren **logga ut och in igen** (eller sessionen förnyas) för att de nya rättigheterna ska slå igenom.

> [!NOTE]
> **Undantaget för `maintenanceLogin`**:
> När underhållsläget kontrolleras i `global.php` anropas:
> ```php
> $auth->hasRight('maintenanceLogin', false);
> ```
> Notera `$allCheck = false`! Detta innebär att wildcardet `all` **inte** ger automatisk tillgång under underhåll. Användaren måste uttryckligen ha en rad med `maintenanceLogin` i tabellen `privileges`. Detta förhindrar att administratörer råkar störa underhållsarbete om de inte specifikt flaggats för det.

---

## Databas & Datamodell

Systemet bygger på en relationsdatabas i MySQL:

- **`users`**: Innehåller användarkonton (`id`, `username`, `password`, `user_role`, `email`, `firstname`, `lastname`, `hourly_rate`, `registered`).
- **`privileges`**: Behörighetsmappning (`id`, `userid`, `privilege`).
- **`orders`**: Huvudtabell för uppdrag (`order_id`, `company_name`, `company_domain`, `org`, `contact`, `order_desc`, `order_date`, `order_status`, `worker`, `asap`, `customer_id` m.fl.).
- **`customers`**: Kontaktpersons- och kundregister (`id`, `name`, `org`, `url`, `contact`, `comment`, `created_at`). Används för autoslutförande vid skapande av uppdrag.
- **`order_steps`**: Delmoment och checklistor kopplade till ett uppdrag (`id`, `orderid`, `step_text`, `step_status`, `created_at`).
- **`order_files`**: Uppladdade bilagor och skärmdumpar (`id`, `orderid`, `filename`, `filepath`, `uploaded_at`).
- **`settings`**: Globala systeminställningar sparade som nyckel/värde-par (`maintenance`, `site_name` etc.).

### Noteringar för databasen:
1. **Historiska tid- och betaldata**: Gamla databasfält behålls, men vyer och anrop för tidsrapportering och utbetalning används inte längre.
2. **Lösenordshashning**: Befintliga konton hashas via MD5 i `database::escape($str, true)`. Nyutveckling bör migrera autentiseringen till moderna `password_hash()` med bcrypt/argon2.

---

## Orderflöde & Gränssnitt

### 1. Terminologi: "Kontaktperson"
Uppgiften har ett eget obligatoriskt namn. Kontaktperson är valfri och öppnas med plusknappen. Webbadress och valfria inloggningsuppgifter för valfri tjänst ligger i samma sektion. En namngiven kontaktperson sparas automatiskt i kontaktregistret och kan sökas fram för fler uppgifter. Uppgiften behåller också en kopia av kontaktuppgifterna. Databasändringen finns i `migrations/20260929_order_contact.sql`.

### 2. Pappersmodalen (Clean Sheet of Paper)
Orderinmatningen är designad som ett minimalistiskt, fysiskt ark papper som glider upp från skärmens nederkant:
- **Translucent fokusveil**: Bakgrunden blir lätt suddig (`backdrop-filter: blur(0.2rem)`) med en mjuk mörk slöja, så att tidigare uppdrag och sidomenyn i bakgrunden fortfarande är synliga och orienterbara.
- **Bottenkant som går utanför**: Papperskortet är förankrat i bottenkanten och dess nederkant blöder utanför skärmkanten utan rundade hörn eller bottenram, vilket ger en ren känsla av ett pappersdokument på ett skrivbord.
- **Knappar & Interaktion**: Taktila, distinkta knappar ("Avbryt" i ljus ram, "Skapa uppgift" i mörk skifferfärg med hover-lyft), ren switch för akutmarkering och en enkel dra-och-släpp-yta för bilagor.

### 3. Tilldela medarbetare & Delegeringsminne (Email-modell)
I modalens överkant finns en snabb mottagar-väljare inspirerad av e-postklienter:
- **Standardval (Mig själv)**: När en uppgift initieras är den automatiskt tilldelad den inloggade användaren (`$_SESSION['user']['userid']`). Inga extra klick krävs för personliga uppgifter.
- **Delegering till kollegor**: Klick på tilldelningspillen fäller ut en teamlista med avatarer, roller och snabbsökning.
- **Smart minne (Förstahandsval)**: Systemet sparar automatiskt den senast delegerade kollegan i `localStorage` (`workflow_last_delegated_worker`). Nästa gång en uppgift skapas visas en klickbar snabbknapp: `⚡ Senast: [Kollega]`, vilket gör att delegering till samma person sker med **ett enda klick**.

### 4. Delegeringshistorik & Uppföljning ("Skapade uppdrag")
För att enkelt kunna följa upp allt man skapat och delegerat till andra:
- **Historikkort på sidan (`.createdHistoryCard`)**: Ett integrerat kort på dashboarden som visar realtidsstatistik över skapade uppdrag (Pågående, Delegerade, Klara) samt en interaktiv lista över nyligen skapade uppgifter med statusbricka och tilldelad utförare (`👤 Du` vs `⚡ Kollega`).
- **Filtrering i huvudflödet**: En ny navigeringsknapp `SKAPADE` i sidomenyn hämtar och listar alla uppdrag där `query.creator = inloggad_användare`.
- **Backend-stöd**:
  - [`main::getWorkersList()`](file:///C:/inetpub/wwwroot/websites/workflow/php/classes/main.class.php): Hämtar alla aktiva medarbetare.
  - [`main::getMyCreatedOrders($limit)`](file:///C:/inetpub/wwwroot/websites/workflow/php/classes/main.class.php): Hämtar skapade uppdrag med utföraruppgifter.
  - [`main::getMyCreatedOrdersStats()`](file:///C:/inetpub/wwwroot/websites/workflow/php/classes/main.class.php): Sammanställer delegeringsstatistik.
  - [`php/functions/getMyCreatedOrders.php`](file:///C:/inetpub/wwwroot/websites/workflow/php/functions/getMyCreatedOrders.php): AJAX JSON-endpoint för asynkron uppdatering.

### 5. Säkert Utkastsystem (Drafts via LocalStorage)
För att säkerställa att utvecklare eller projektledare aldrig förlorar text eller delmoment vid oavsiktliga klick eller sidladdningar:
- Alla ändringar sparas automatiskt i webbläsarens `localStorage` under nyckeln `workflow_order_draft_v1`.
- Vid återöppning återställs kontaktperson, webbadress, beskrivning, akuta flaggor, tilldelad medarbetare och delmoment direkt.
- En diskret statusindikator visar när utkastet är sparat. Vid framgångsrikt skapande rensas utkastet automatiskt.

### 6. Diskussion i uppgifter
Varje uppgift har en texttråd som kan läsas av skaparen, den nuvarande utföraren och användare med `orders_show_all`. Nya inlägg kan skrivas medan uppgiften är pågående, granskas eller kompletteras. Slutförda och borttagna uppgifter har läsbar historik.

`php/functions/taskThread.php` läser sidor om högst 50 inlägg via `GET` (`orderId`, valfritt `beforeId` eller `afterId`). `POST` med CSRF-token använder `action=send` och `body` för nya inlägg eller `action=read` och `messageIds[]` för inlägg som har visats. Varje inlägg och dess notiser sparas i samma databastransaktion. Notiser till skapare och utförare ligger kvar som olästa tills mottagaren öppnar tråden; knappen för att markera övriga notiser som lästa påverkar inte chattinlägg.

---

## Utvecklingsriktlinjer & Regler

När du vidareutvecklar Workflow gäller följande fasta regler:

### Frontend & Responsivitet
1. **Mobile-First**: Alla gränssnitt ska alltid konstrueras utifrån mobila skärmar först och expandera med media queries (`min-width: 48rem` osv.).
2. **Relativa enheter**: Använd `rem`, `%`, `vw/vh` för marginaler, paddings och layout istället för fasta pixelmått (`px`).
3. **Flexbox & Grid**: Använd moderna CSS-layouttekniker. Undvik äldre float-baserade lösningar i nya komponenter.

### Debug & Testning
- **Mappen `DEBUG/`**: Alla filer som skapas för att testa funktioner, inspektera sessioner eller visa UI-prototyper **ska alltid placeras i `DEBUG/`**. Detta håller produktionskatalogen ren och isolerad. Mappen är exkluderad i `.gitignore`.

---

## Installation & Driftsättning

1. **Klona repot**:
   ```bash
   git clone <repo-url> C:\inetpub\wwwroot\websites\workflow
   ```
2. **Konfigurera miljövariabler**:
   Kopiera `.env.example` till `.env` och fyll i databasanslutning samt SMTP-uppgifter:
   ```ini
   DB_HOST=localhost
   DB_NAME=workflow
   DB_USER=root
   DB_PASS=hemligt
   
   SMTP_HOST=smtp.mailgun.org
   SMTP_USER=postmaster@doman.se
   SMTP_PASS=lösenord
   SMTP_PORT=587
   ```
3. **Webbserver (IIS / Apache)**:
   - För IIS finns färdiga omskrivningsregler i `web.config`.
   - Kontrollera att `upload/`-katalogen har skrivrättigheter för `IUSR` / `IIS_IUSRS` (eller `www-data` på Linux).
4. **Verifera installationen**:
   Navigera till `http://localhost/` eller den konfigurerade IIS-bindningen. Logga in med administratörskonto.

### Databasmigreringar

Git versionshanterar SQL-filerna i `migrations/`, men inte databasens innehåll. Lägg en ny schemaändring i en ny, tidsstämplad `.sql`-fil, till exempel `migrations/20260930_add_due_date.sql`. Använd **en SQL-sats per fil**, ändra aldrig en redan körd fil och skapa en ny fil för nästa ändring. Ta separat backup före större ändringar; migreringarna ersätter inte backup av data.

Kör från projektroten med PHP CLI:

```powershell
php bin/migrate.php --status --expected-db=workflow
php bin/migrate.php --up --expected-db=workflow
```

`schema_migrations` sparar filnamn, kontrollsumma och körningstid i den anslutna databasen. Verktyget kör bara väntande filer, stoppar ändrade eller saknade tidigare filer och kräver att `--expected-db` matchar både konfigurationen och den faktiska anslutningen. Vid fel avbryts körningen. MySQL kan spara en schemaändring även om nästa steg misslyckas; kontrollera därför databasen innan du försöker igen.

Valet **Kom ihåg mig på den här enheten** använder tabellen `remember_tokens` från `20260930_03_remember_tokens.sql`. Inloggningen varar upp till 400 dagar och förnyas när den ihågkomna cookien används. Utloggning återkallar cookien. Om användaren rensar cookies eller inte använder appen inom giltighetstiden behövs en ny inloggning.

Den äldre `20260929_order_contact.sql` är redan manuellt körd lokalt och i produktion. När ett befintligt schema tas i bruk ska man först kontrollera att ändringen finns och sedan registrera just den filen utan att köra den igen:

```powershell
php bin/migrate.php --baseline=20260929_order_contact.sql --expected-db=workflow
```

GitHub Actions kör väntande migreringar mot `xlfood_se_db_workflow` **före** FTPS-uppladdningen. Miljön `production` behöver `DB_HOST` och `DB_USER` som variabler samt `DB_PASS` som hemlighet. Databasnamnet är låst i arbetsflödet. Håll varje schemaändring kompatibel med den tidigare appversionen eftersom den körs innan filerna publiceras. `.env`, SQL-filerna och migreringsverktyget laddas inte upp till webbservern.

---
*Dokumentationen underhålls löpande i takt med att nya moduler implementeras i Workflow.*
