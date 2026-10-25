# Workflow: delat skal

## Behörighet och data

Centralens core_users är kontoauktoritet. Aktiv, verifierad användare och
giltig Core-session krävs även när Workflowcookiesessionen finns kvar.
Workflows egna uppgifter och rättigheter ägs fortsatt av Workflow.
Centralens schema.messages styr den delade privata chatten; deltagarskap,
sändrätt, läskvitton och idempotens avgörs av dess befintliga tjänster.

## Gemensamma komponenter

| Funktion | Ägare | Workflowvariant |
| --- | --- | --- |
| Header | Centralen src/includes/header.php | Sidrubrik Workflow, profil till Core-inställningar |
| Privat chatt | Centralen chat-dock.js och Schema Api | Same-origin serverproxy |
| Tema | Centralen kontoinställningar och shell-theme.css | Samma valda palett |
| Uppgiftsdialog från chatt | Centralen workflow-task.js och Workflow createOrderUnified | Samma request-ID och återhämtning |
| Uppgiftslista/redigering | Workflows befintliga app/orderModal | Pågående utkast och scroll behålls |
| Navigering | Workflows macDock | Fullbredds bakgrund, centrerade kontroller |

## Transport och återhämtning

Webbläsaren använder enbart Workflows session/CSRF mot Workflows proxy.
Servern tillför Coreaktör och sessionshash och signerar ett tidsbundet anrop.
Inga lösenord, signeringshemligheter eller Core-sessioner skickas till UI.
Centralen avgränsar chatten till publicerade chattrutter och kontrollerar
aktuell identitet, behörighet och deltagarskap vid varje operation.

Okänt meddelande-/uppgiftssvar behåller samma befintliga request-ID och utkast.
Ingen automatisk ny skapning efter timeout. En misslyckad headerhämtning ska
lämna Workflows uppgifter åtkomliga och visa återförsök via omladdning.

## Tangentbord och viewport

Headerprofilen och ärendekontexten är riktiga länkar till Centralen.
Chattknapp, panel, Escape och återfokus följer Centralens beteende. Chattens
synliga höjd anpassas till header, menyrad och visualViewport. Kontroller
ska förbli nåbara på mobil och när Workflows redigeringsdialog är öppen.
Det delade skalet ändrar inte Workflowarbetsytans lagrade uppgiftsutkast.
