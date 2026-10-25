## Gemensam header och chatt – 2026-10-25

Workflows inloggade hem-, prioriterings- och administrationssidor använder
Centralens serverrenderade header och samma offentliga chattmoduler.
Workflows egna uppgifter/rättigheter och pågående arbetsutkast behålls.
Profil och ärendekontext går till Centralens korrekta adress.

centralen-shell-api.php är en same-origin proxy med Workflowcookie/CSRF
och explicit underhållskontroll. CentralenShell signerar serveranrop med
audience centralen-shell-v1, 90 sekunders tidsfönster och engångsnonce.
Core kontrollerar aktuell Core-session och tillåter endast publicerade
chattrutter. Inga Corecookies, sessionshashar eller hemligheter når klienten.
Publik CORS gäller bara Centralens statiska resources, aldrig privata API:er.
Ingen ny SQL eller automatisk datakopiering behövs.

Bevis: Centralens DEBUG/tests/workflow-shared-shell-20261025.md.

## Lokal aktivering enligt användarbeslut – 2026-10-24

Använd endast Centralens konton och skapa vanliga Workflowprofiler automatiskt.
Kopplingen är nu aktiverad endast lokalt mot centralen.core_users efter
verifierade fullbackups/restore/hash/rollback. Två engineALTER och fyra
integrationstabeller är applicerade; alla422 gamlaWorkflowposter bevarade.
Gamla Workflowkonton importeras inte. HistoriskaID kräver bevisad koppling;
nya profiler får roll1 och add_new_order, aldrig automatisk adminåtkomst.
Privata.env är backupade och konfigureringen är inte lagrad iGit.
Rootbevis: Centralens DEBUG/tests/workflow-integration-20261024.md.
112 server-/CGI-kontroller och8browserprov PASS. Ingen extern publicering.

# Centralen och Workflow – integration 2026-10-24

## Identitet och datagränser

Centralens `core_users` är den enda källan för aktiva konton, verifiering och
spärr när `CENTRALEN_INTEGRATION_ENABLED=1`. Workflow läser den genom en separat,
explicit konfigurerad PDO-anslutning; ingen fallback till Workflow-databasen
finns för gemensam identitet. HTTPS krävs utanför loopback.

Workflow behåller sitt privata schema för uppgifter, steg, diskussioner,
notifieringar, behörigheter och historik. `workflow_user_links` kopplar centrala
UUID till befintliga heltals-ID utan att skriva om historiska referenser.
Den gamla `users`-tabellen är då endast profil-/historikunderlag och bevis vid
en uttrycklig engångskoppling, aldrig normal lösenordsauktoritet.

En verifierad Centralen-inloggning följs vid behov av bevis med det befintliga
Workflow-lösenordet. E-postadresser matchas inte automatiskt. Kopplingen är
unik åt båda håll och får inte överta någon annans konto. Fem lösenordsförsök
per central identitet och 15 minuter tillåts; en ny webbläsarsession nollställer
inte gränsen. Befintliga Workflowroller och `privileges` behålls. Centralt
administratörskap ger inte automatiskt Workflowadministration.

Normal Workflowinloggning och registrering går via Centralen i gemensamt läge.
Gamla Workflowinbjudningar stängs av utan mejl; adminvyn länkar i stället till
Centralens kontohantering. Användarbeslutet är endast Centralens användare: gamla Workflowkonton
importeras inte till Core. `WORKFLOW_AUTO_PROVISION=1` tillåter automatisk lokal
medarbetarprofil för verifierade, aktiva Corekonton. Profilen får roll1 och
exakt `add_new_order`; aldrig `admin`, `all` eller `maintenanceLogin`.
Profil, standardrätt och UUID-länk skapas i samma InnoDBtransaktion under
per-identitet-lås. Två samtidiga försök återanvänder samma heltals-ID.

En matchande äldre e-post eller ett namn är endast en ledtråd: användaren
kan koppla den gamla profilen genom lösenordsbevis eller uttryckligen välja
en ny vanlig profil med CSRF-skydd. Den nya profilen ärver aldrig den gamla
profilens ID eller rättigheter. Namn/e-post i gamla tabellen är enbart
presentationscache (50/225 tecken); autentisering, nytt mottagarval och API
hämtar hela namnet och adressen från Core. För lång e-post lagras som intern
`identity.invalid`-markör i cachen, inte som en kapad identitet.

Vanliga Workflow-mottagarlistor visar endast aktiva, kopplade Coreprofiler.
En ännu okopplad Coreanvändare dyker upp efter egen SSO eller efter explicit
uppgifts-POST från Centralen. Listläsning och resultatåterhämtning skapar aldrig
profiler. Gammalt senaste-val filtreras och utkast med otillgänglig mottagare
bevaras men måste få ett aktuellt mottagarval innan de kan skickas.

## Inloggningsutbyte

1. Workflow `centralen-login.php?action=start` skapar state och verifierare i
   sin egen `workflow_session`, med Workflowbegränsad cookiepath och strict mode.
2. Centralens fasta authorize-endpoint tar state och SHA-256-utmaning, kräver
   en vanlig verifierad Centralensession och skapar en engångskod i
   `core_app_login_codes`. Kodens livslängd är 60 sekunder.
3. Workflow callback kontrollerar webbläsarsessionens state och verifierar
   kodens hash, mål, utgångstid och PKCE. Konsumtion sker under lås i samma
   centrala databastransaktion.
4. Varje efterföljande Workflowrequest återkontrollerar gemensamt konto och
   den ursprungliga `core_sessions`-posten. Spärr, borttagen verifiering eller
   återkallad Centralensession tar bort Workflowinloggningen utan fallback till
   gammal remember-cookie.

PHP-sessioncookies eller lösenord skickas inte mellan domänerna. Returmål är
fasta konfigurerade appadresser, inte godtyckliga användarinmatade URL:er.
SSO-entrypunkten tillåter endast autentisering under underhåll; vanlig appåtkomst
kräver fortfarande den uttryckliga rättigheten `maintenanceLogin`.

## Uppgifts-API

`php/functions/centralenTasks.php` accepterar endast POST med JSON och
`X-Integration-Signature = HMAC-SHA256(rawBody, WORKFLOW_INTEGRATION_SECRET)`.
Den gemensamma nyckeln är samma 64 hextecken som UTF-8-bytes, inte hexavkodad.
Kuvertet innehåller `action`, Unix-`timestamp`, 64-teckens slumpnonce och
`payload`. Alla åtgärder kräver aktörens aktuella `actor_session_hash`, tillförd
på Centralens server och verifierad mot Core; webbläsaren väljer aldrig den. Nonce är engångsbruk; högst 90 sekunders tidsavvikelse accepteras.
Nya nonce-utgångar skrivs med UTC oberoende av anslutningens tidszon.

- `context`: aktör och mottagare anges som centrala UUID. Returnerar koppling,
  `actor_provisionable`/`recipient_provisionable`, aktuell skapanderätt och
  serverkontrollerade Workflow-/kopplingslänkar.
- `create`: samma aktör/mottagare, stabilt UUID i `request_id`, titel,
  plaintextbeskrivning, `steps` och `priority` (`normal`/`asap`).
- `operation`: endast aktören kan återhämta resultatet för samma `request_id`.

Aktör och mottagare måste ha giltiga gemensamma konton och rätt ID-kopplingar.
`add_new_order` eller `all` krävs, men underhåll kräver dessutom uttrycklig
`maintenanceLogin`; `all` kringgår inte underhåll. Rättigheter kontrolleras
även vid exakt återförsök. Egen resultatåterhämtning kräver en giltig
Core-session, men får ske utan lokal profil eller efter återkallad skapanderätt.
Detta gör okända POST-resultat återhämtningsbara även före profilskapningen.
Underhåll kräver fortfarande uttrycklig underhållsrätt vid sådan läsning.

`createOrderUnified` återanvänds. Trusted integration context anger skapare
även för notifieringen. `workflow_task_operations`, själva uppgiften, steg och
notifiering delar MySQLtransaktion. Samma avsikt återger samma uppgift; ändrad
avsikt under samma request-ID nekas409. Saknade/icke-transaktionella tabeller
nekas. Inga nya externa transporter eller mejl följer av integrationsvägen.

Beskrivningen HTML-kodas i adaptern eftersom Workflow visar den som rich HTML.
Titeln lagras som plaintext och kodas vid utskrift, inklusive gamla input-sinks.
Stegens befintliga kodade visning bevaras. Standardsvar är `{data:...}` eller
`{error:{code,message}}`; typade fel är `workflow_forbidden`403,
`workflow_conflict`409, `workflow_invalid`422 och `workflow_unavailable`503.

## Installation och verifierad nivå

`migrations/20261024_centralen_integration.sql` tillför fyra separata InnoDBtabeller:
user-links, nonces, operationskvittens och länkningsförsök. Den konverterar
också befintliga `users` och `privileges` till InnoDB för verkligt atomisk
autoprofilering. Den ändrar inga befintliga konto-, rättighets- eller
verksamhetsvärden. Konverteringen har särskilt godkänts av integrationsägaren
efter full backup och isolerad bevarande-/återgångskontroll. Centralens motsvarande kodtabell behövs
också. Tabellerna `query`, `steps` och `task_notifications` måste redan vara
InnoDB; runtime kontrollerar detta och konverterar aldrig automatiskt.

Privat konfiguration finns beskriven i `.env.example`. Privat `.env` konfigureras av integrationsägaren och inga hemligheter får
följa med Git. Användarbeslutet är att endast Corekonton ska vara aktiva och
vanliga profiler får skapas automatiskt. Ingen gammal kontolista importeras.
Full backup och isolerat migrations-/återgångsprov ska föregå aktivering.

Verifierat mot nya självständiga `centralen_test_*` på explicit loopbackprofil:

- `php tests/centralenIntegration.php --cgi`:112 PASS, varav24 riktiga
  PHP-CGI-kontroller för login, session, koppling, spärr, CSRF, signatur,
  skapa, återförsök, operation, konflikt och underhåll.
- Samma prov utan CGI täcker bland annat PKCE/state/audience/replay/utgång,
  verifiering/spärr/logout, bevarade ID, persistent rate limit, inga ärvda
  adminrättigheter, felmotorer, ingen dubbeluppgift/notifiering, och verklig
  triggerutlöst rollback av uppgift/steg/kvittens tillsammans, automatisk
  profilsamtransaktion, verklig tvåprocessers konkurrens och läsning efter
  serverfel före profilskapning eller återkallad skapanderätt.
- `node tests/centralenLoginBrowser.mjs`:8 viewport-/tillståndsprov vid
  320/390/1280px. Kräver de syntetiska CGI-fixtures som skapas med
  `WORKFLOW_CAPTURE_FIXTURE=1`. Skärmbilder under ignorerad DEBUG är visuellt
  granskade; ingen sidöverströmning och alla inloggningsfält minst44px höga.
- PHP/JS-syntax och `git diff --check` kontrolleras vid leverans.

Tester laddar aldrig appens `.env` och skickar inga mejl. De använder bara
`CENTRALEN_TEST_DB_PROFILE=isolated` och explicit `CENTRALEN_TEST_DB_HOST`,
`PORT`, `USER`, `PASSWORD`. Äldre integrationstester som läser appens databas
får inte köras som ersättning för denna isolerade verifiering.

De beskrivna testerna gör inga skrivningar i verksamhetsdatabaser, skickar
inga mejl och publicerar ingenting. Lokal aktivering och dess bevisnivå ägs av
Centralens samlade integrationsprotokoll; detta dokument beskriver kodkontrakt
och isolerad verifiering.
