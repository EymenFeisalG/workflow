---
version: alpha
name: "Workflow"
description: "Svensk uppgiftsarbetsyta med Centralens gemensamma profil och privata chatt."
colors:
  primary: "#225ac7"
  header: "#181818"
  background: "#f5f5f5"
  foreground: "#ffffff"
typography:
  sans:
    fontFamily: "system-ui, sans-serif"
omitted:
  - section: rounded
    reason: "Befintliga runtimekomponenter äger radier; inga nya tokenvärden genereras."
  - section: spacing
    reason: "Runtime CSS äger befintliga avstånd och geometribaserad skalreservation."
components:
  header: {}
  chat: {}
  navigation: {}
---

# Workflow Design System

## Overview

Produktvy för medarbetarnas uppgifter, delegering och prioritering. Språket är
svenska; Centralens delade skal använder dess språkdata. Befintliga uppgiftskort
och redigering behålls. Referens för skalet är den faktiska Centralenheadern och
Workflows frostade menyrad. Undvik nya dekorativa kort eller hjälptexter i skalet.

Runtime är tokenägare: Centralens resources/site/style_one/css/tokens.css,
shell.css, shell-theme.css, profile.css och chat.css. Detta dokument beskriver
dem och genererar inga tokens. Workflows ui/style/css/centralen-shell.css är
bara adapter mot befintliga app.css och macDock.css; ingen kopia av chatten.

## Colors

Det svarta standardskalet använder header #181818 och foreground #ffffff.
Kontots ocean/forest/plum-paletter hämtas från Centralen och samma shell-theme.css.
Arbetsytans befintliga background #f5f5f5 bevaras. Primary #225ac7 tillhör
Centralens delade åtgärder. Nya färger introduceras inte av integrationen.

## Typography

Skal och chatt använder Centralens system-ui med befintliga fallbacks och
storlekar. Befintlig Workflowtypografi i uppgiftsytan behålls. Långa profilnamn
får ellips; rubrik och chattknapp förblir nåbara på mobil.

## Layout

Profil vänster, centrerad sidrubrik, chatt höger i samma delade header.
Fullbreddsmenyn centrerar sina kontroller. Headerns och dockans faktiska mått
reserverar utrymme; chatt, uppgiftsyta och fasta knappar ska inte överlappa.
Workflows befintliga appscrollning och modalernas lager behålls.

## Elevation & Depth

Mörk header och frostad menyrad ger orientering. Chatten använder Centralens
befintliga lager och native uppgiftsdialog. Ingen extra dekorativ skuggyta.

## Shapes

Profilbild är rund; chattens radier är samma som Centralens. Menybakgrunden
går kant till kant; befintliga rundade menykontroller behålls.

## Components

Header renderas av Centralens src/includes/header.php, med verklig profil och
behörighetsstyrd chatt. Profilen länkar till Centralens kontoinställningar.
Chattens JS, utkast, felåterhämtning och tangentbord är samma moduler i båda
apparna. Egna Workflowdialoger och uppgiftsrättigheter ändras inte.

## Motion

Skalets befintliga reduced-motion-regler gäller. Ingen ny entréanimation.

## Accessibility

Riktiga profil- och ärendelänkar, namngiven chattknapp, synligt tangentbordsfokus,
Escape och återfokus. Smal viewport och öppna menyer testas. Serverfel i skalet
ska ge en nåbar återförsöksväg utan att radera Workflows pågående uppgiftsutkast.
