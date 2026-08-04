# UI-audit: TekstTV WordPress-plugin

## Oordeel

De UI is inmiddels visueel behoorlijk bruikbaar, maar nog niet consequent WPDS. De grootste tekortkomingen zitten in toegankelijk gedrag, responsive details en het grote aantal eigen componentstijlen.

Voor deze audit is de officiële WPDS-documentatieserver gebruikt voor onder andere `Button`, `Card`, `CollapsibleCard`, `Dropdown`, `MenuItem`, `NumberControl`, `UnitControl`, `CheckboxControl`, `RadioControl`, `ToggleControl`, `Badge`, `EmptyState`, `ConfirmDialog`, `Snackbar`, `Modal`, `DataViews` en de `--wpds-*`-tokens.

Daarnaast zijn Instellingen, Loop en Campagnes lokaal in WordPress bekeken op 1440 px en 390 px, inclusief open blokken en menu's.

## Hoge prioriteit

### 1. Kanalen is op mobiel daadwerkelijk te breed

`resources/css/partials/settings.css:13` zet `#teksttv-channels` op minimaal 620 px. De mobiele regel op regel 104 verliest door lagere CSS-specificity.

Op een viewport van 390 px eindigde de tabel bij pixel 647. Daardoor staan API en Verwijderen buiten beeld. De screenshot lijkt aanvankelijk goed, maar de gebruiker moet horizontaal scrollen om essentiële acties te vinden.

Advies: overschrijf op mobiel expliciet `#teksttv-channels { min-width: 0; }`. De bestaande mobiele kaartweergave kan de vier cellen daarna normaal stapelen.

### 2. Het toevoegen-menu doet alsof het een ARIA-menu is, maar gedraagt zich niet zo

`src/views/loop-page.php:32` gebruikt:

- `aria-haspopup="menu"`
- `role="menu"`
- `role="menuitem"`

In de browser bleef focus na openen op "Blok toevoegen". `ArrowDown` deed niets en de eerste menuoptie kreeg geen focus.

Dat is een incompleet ARIA-menupatroon. Twee geldige oplossingen:

- WPDS `Dropdown` + `MenuGroup` + `MenuItem`, met focus-, pijltoets-, Escape- en mobiele popoverafhandeling.
- Of het simpeler houden als native disclosure: `role="menu"`/`menuitem` verwijderen en de opties als gewone knoppen behandelen.

De eerste optie is het beste als het workbenchgedeelte ooit een React-island wordt. Voor de huidige Alpine-architectuur is de tweede oplossing kleiner en veiliger.

### 3. Weekdagen zijn niet met het toetsenbord bedienbaar

`resources/css/partials/post-meta.css:279` gebruikt `display: none` voor de echte checkboxen. Daarmee verdwijnen ze ook uit de tabvolgorde.

Dit patroon komt via `src/AdminPage.php:551` terug in loopblokken, campagnes en berichtplanning.

Gebruik visueel verborgen maar focusbare checkboxen, inclusief een zichtbare `:focus-visible`-status op de dagknop. In WPDS-termen is dit een compacte `CheckboxControl`-groep, niet alleen een rij gestileerde spans.

### 4. De drie afbeeldingskaarten missen selectiesemantiek

`src/views/post-meta-box.php:124` presenteert Standaard, Eigen en Geen als drie gewone buttons. De selectie bestaat alleen uit `.is-active`; `resources/ts/alpine/postMeta/sidebarCard.ts:3` werkt geen `aria-pressed`, radio-input of radiogroup bij.

Visueel is duidelijk wat geselecteerd is, maar hulptechnologie krijgt die status niet.

Dit hoort semantisch een `RadioControl` of radio-cardgroep te zijn. Als buttons behouden blijven, moeten alle kaarten minimaal consequent `aria-pressed` krijgen.

### 5. "Preview vergroten" is onzichtbaar zonder hover

`resources/css/partials/preview.css:163` geeft de knop `opacity: 0`. Alleen `.teksttv-preview-container:hover` maakt hem zichtbaar.

Gevolgen:

- Op touchapparaten ontbreekt een betrouwbare hover.
- Bij toetsenbordfocus blijft de knop onzichtbaar.
- Een gebruiker kan een onzichtbare knop aantreffen in de tabvolgorde.

Voeg ten minste `:focus-visible` en `:focus-within` toe en toon de knop permanent op touch/mobile. De onderliggende native `<dialog>`-implementatie is verder juist sterk: focus trap, Escape, inert background en focus restoration zijn aanwezig.

### 6. Enkele kleuren halen WCAG AA niet

Voor kleine tekst is minimaal 4,5:1 nodig. Een paar huidige combinaties:

- `#787c82` op wit: 4,20:1.
- `#787c82` op `#f6f7f7`: 3,91:1.
- Audit groen `#00a32a` op `#edfaef`: 3,11:1.
- Audit oranje `#bd8600` op `#fef8ee`: 3,02:1.
- AI-waarschuwing `#dba617` op wit: 2,22:1.

Betrokken locaties zijn onder andere `resources/css/partials/blocks.css:82`, `resources/css/partials/base.css:104`, `resources/css/partials/audit.css:46` en `resources/css/partials/post-meta.css:62`.

Gebruik WPDS foreground/content- en Badge-intenttokens, of sterkere core-kleuren met fallback.

## Visuele en structurele verbeteringen

### Block summary: geen badge van maken

`resources/css/partials/blocks.css:82` maakt de samenvatting nog steeds een omrand chipje. De kleinere radius helpt, maar conceptueel is dit geen statusbadge.

WPDS `CollapsibleCard` heeft hiervoor `HeaderDescription`. Advies:

- Rand en witte achtergrond verwijderen.
- Gewone neutrale samenvattingstekst gebruiken.
- Alleen echte statussen als `Badge` renderen.
- De samenvatting `aria-hidden` maken wanneer die alleen de bloknaam herhaalt, zoals bij campagnes.

Dat maakt de header rustiger en minder "opgemaakt".

### Block shell gebruikt een afwijkende radius

`resources/css/partials/blocks.css:1` gebruikt 7 px, terwijl `.teksttv-card` 4 px gebruikt. Headers hebben opnieuw 7 px. Daardoor ogen blokken nog steeds ronder dan de rest van wp-admin.

Gebruik één semantische radius per oppervlakteniveau:

- Controls: small.
- Menu/popover: medium.
- Card/collapsible card: large.

Tot een WPDS-tokenlaag beschikbaar is, zou 4 px voor zowel card als block het meest aansluiten bij de huidige klassieke wp-adminomgeving.

### Te veel hardcoded designwaarden

De CSS bevat onder andere:

- 21 keer `#2271b1`.
- 19 keer `#c3c4c7`.
- 19 keer wit.
- Radii van 2, 3, 4, 6, 7, 10 px en 50%.
- Veel losse spacingwaarden buiten een herkenbare schaal.

Voorbeelden staan in `resources/css/partials/base.css:28` en vrijwel heel `resources/css/partials/blocks.css`.

Een praktische tussenstap is een kleine lokale tokenbridge:

```css
--teksttv-color-surface: var(--wpds-color-background-surface-neutral, #fff);
--teksttv-color-content: var(--wpds-color-foreground-content-neutral, #1d2327);
--teksttv-radius-control: var(--wpds-border-radius-sm, 4px);
--teksttv-radius-card: var(--wpds-border-radius-lg, 4px);
```

Dat voorkomt dat de plugin onbruikbaar wordt wanneer WPDS-variabelen niet door de klassieke admin worden geladen.

### Visuele koppen zijn te vlak

`resources/css/partials/base.css:40` geeft zowel `h2` als `h3` 14 px. Daardoor wordt de hiërarchie in Instellingen vooral door witruimte gedragen.

Semantisch is de headingstructuur goed, maar visueel mogen kaarttitels en subgroepen iets duidelijker van elkaar verschillen.

### Empty states missen WPDS-opbouw

`src/AdminPage.php:418` rendert alleen een icoon en één zin. WPDS `EmptyState` onderscheidt `Icon`, `Title`, `Description` en `Actions`.

De toevoegactie staat nu buiten de empty state. Bij een lege lijst is het sterker om "Nog geen blokken" als titel, een korte uitleg en "Blok toevoegen" samen te presenteren.

## Interactie en feedback

### Verwijderen heeft geen bevestiging of undo

Blokken, kanalen, groepen en afbeeldingen verdwijnen direct:

- `resources/ts/alpine/blocks/handleBlocksClick.ts:36`
- `resources/ts/alpine/channelsSettings.ts:89`
- `resources/ts/alpine/blocks/workbench.ts:193`

De wijzigingen worden pas bij opslaan definitief en de dirty-form guard helpt, maar er is geen directe herstelactie.

WPDS-richting:

- Snackbar met "Ongedaan maken" voor lokaal verwijderde blokken/rijen.
- `ConfirmDialog` voor definitieve of moeilijk herstelbare verwijderingen.

Undo past hier beter dan overal een modal.

### Bulk open-/dichtklappen heeft kleine touch targets

De browser mat deze link-buttons als ongeveer 111 x 16 px. Ze zijn acties, geen navigatielinks.

De tekststijl mag blijven, maar geef ze een minimaal touchgebied of gebruik compacte tertiary buttons. Op mobiel zijn twee targets van slechts 16 px hoog onnodig lastig.

### Animatie negeert reduced motion

De plugin bevat meerdere transitions en handmatige hoogteanimaties, maar geen `prefers-reduced-motion`.

`resources/ts/modules/dom.ts:39` animeert hoogte met meerdere layout reads en `resources/ts/alpine/blocks/constants.ts:8` animeert sorteren altijd.

Voeg een reduced-motionpad toe en gebruik waar mogelijk WPDS-motiontokens of de motion van `Collapsible`.

### AI-busy state kan semantisch sterker

`resources/ts/alpine/postMeta/aiGeneration.ts:83` vervangt de buttoninhoud en disablet de knop. Visueel werkt dit, maar `aria-busy` ontbreekt en de wisselende status is niet gegarandeerd hoorbaar.

WPDS `Button isBusy` plus een blijvende `role="status"` is hier de juiste referentie.

## Wat al goed is

- Alle toevoegen-knoppen zijn nu gewone native WordPress-buttons zonder verzonnen plus/pijlcombinaties.
- "Link kopiëren" is een native button en gebruikt een nette live-status.
- Opslaan gebruikt de core primary button.
- Getal en `sec` vormen visueel één control; mobiel sluiten beide delen goed aan.
- Aantal en categorie overlappen niet meer.
- Loop en Campagnes hebben op 390 px geen pagina-brede overflow.
- De geteste pagina's hadden geen ontbrekende labels, dubbele IDs of kapotte `aria-controls`.
- Blokken zijn met het toetsenbord omhoog/omlaag te verplaatsen.
- Na toevoegen en verwijderen wordt focus bewust verplaatst.
- Het blokactiemenu sluit met Escape en herstelt focus correct.
- De previewoverlay gebruikt verstandig een native `<dialog>`.
- De headingstructuur van Instellingen, Loop en Campagnes is semantisch logisch.
- De dirty-form guard waarschuwt bij onopgeslagen wijzigingen.

## Aanbevolen WPDS-strategie

De plugin gebruikt PHP + Alpine + Tom Select en heeft geen `@wordpress/components`-runtime; zie `package.json:26`. Een volledige React-conversie alleen om WPDS te gebruiken zou buitenproportioneel zijn.

Aanbevolen volgorde:

1. Zonder React oplossen: mobiele kanalentabel, menu-ARIA, weekdagfocus, afbeeldingsselectiestatus, previewknop, contrast en reduced motion.
2. Visueel consolideren: tokenbridge, één radiusmodel, summary als gewone header description, grotere touch targets en betere empty states.
3. Alleen de complexe loop/campagne-workbench eventueel als React-island bouwen met `CollapsibleCard`, `Dropdown`, `MenuItem`, `NumberControl`, `UnitControl`, `ToggleControl` en `Snackbar`.
4. `DataViews` niet invoeren voor de huidige kleine tabellen. Dat wordt pas zinvol bij zoeken, sorteren, bulkacties en grotere datasets.

## Reikwijdte en verificatie

De conditionele Inhoud & AI- en AI-auditpagina's waren in de lokale configuratie niet beschikbaar omdat AI-support ontbreekt; die zijn daarom statisch gecontroleerd.

De bereikbare Instellingen-, Loop- en Campagneschermen zijn lokaal in een echte Chromium-browser gecontroleerd op 1440 x 1000 px en 390 x 844 px. Daarbij zijn ook open blokken, actiemenu's en het toevoegen-menu beoordeeld.

Voor de audit zijn geen tests toegevoegd. `git diff --check` was schoon.
