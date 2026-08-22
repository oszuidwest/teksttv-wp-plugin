# Engineering simplification plan

Status: in uitvoering  
Startdatum: 2026-08-04

## Doel

TekstTV en `teksttv-wp-extensions` agressief vereenvoudigen zonder de functies te verwijderen die daadwerkelijk in productie worden gebruikt. Verwijderde complexiteit moet aantoonbaar minder onderhoud, minder configuratie of een kortere CI-pijplijn opleveren.

Dit document is de canonieke planning voor beide repositories. We houden bewust geen tweede kopie in de extensierepository bij.

## Besluiten en randvoorwaarden

- Campaigns blijven in gebruik. Alleen de limiet op het aantal campagneslides en de bijbehorende rotatie verdwijnen.
- AI-generatie blijft in gebruik. De normale genereeractie blijft werken; technische instellingen en foutafhandeling worden teruggebracht tot wat operationeel nodig is.
- De auditpagina en haar kernstatistieken blijven bestaan.
- Gerichte migratie of opschoning van bestaande configuratie is toegestaan.
- Het gebruikte extensiecontract blijft stabiel:
  - `BlockRegistry::register()`, `all()`, `render()`, `save()` en `build()`;
  - ingebouwde registraties op `init`-prioriteit 5 en add-ons vanaf prioriteit 10;
  - gedeelde datum- en weekdagplanning;
  - behoud van opgeslagen rijen wanneer een add-on tijdelijk niet actief is.
- Veranderingen worden als kleine Conventional Commits aangeleverd.
- Een coveragepercentage is geen doel. Tests moeten een relevante regressie, securitygrens of publiek contract beschermen.

## Nulmeting

Gemeten op `main` op 2026-08-04. Gegenereerde assets, `vendor`, lockfiles en release-inhoud tellen niet mee in de broncoderegels.

| Onderdeel | `teksttv-wp-plugin` | `teksttv-wp-extensions` |
| --- | ---: | ---: |
| Productie-PHP | 5.719 | 501 |
| Frontend TypeScript/CSS/JS | 4.120 | 0 |
| PHPUnit-code | 4.456 | 412 |
| E2E-code | 1.625 | 0 |
| JavaScript-tests | 161 | 0 |
| Overige tests en contracttest | 0 | 135 |
| CI- en buildscripts | 655 | 478 |
| PHPUnit | 299 tests / 708 assertions | 13 tests / 27 assertions |
| Release-ZIP | 464.527 bytes | 6.983 bytes |

Aanvullende hoofdplugin-assets:

- `admin.js`: 246.222 bytes;
- `admin.css`: 42.448 bytes;
- uitgepakte release: circa 2.040 KiB.

Baseline-uitkomst:

- PHPCS, PHPStan, PHPUnit, frontendlint, TypeScript en JavaScript-tests slagen.
- De hoofdplugin bouwt en de package- en ZIP-pariteitscontroles slagen.
- De extensie slaagt voor PHPCS, PHPStan, PHPUnit en packaging.
- Biome meldt alleen dat de schema-URL nog `2.5.5` noemt terwijl de geïnstalleerde CLI `2.5.6` is.
- Lokale E2E-uitvoering vereist een werkende Docker-/`wp-env`-omgeving en valt daarom buiten deze nulmeting.

De meting is reproduceerbaar met de bestaande Composer- en Bun-scripts. Regels worden geteld vanuit `git ls-files`, zodat gegenereerde en genegeerde bestanden niet meetellen.

## Open werk

| Item | Besluit |
| --- | --- |
| PR #121 — editorial quality filters | Niet mergen. De audit blijft, maar krijgt een kleinere, begrensde optimalisatie zonder volledige ID-lijsten of kwadratische tekstvergelijking. |
| PR #122 — AI diagnostics | Niet mergen. Geen eigen loggingplatform of opgeslagen prompt-/contentdiagnostiek toevoegen. |
| PR #125 — custom model IDs | Parkeren totdat een gebruikte provider aantoonbaar een niet-ontdekbaar model-ID nodig heeft. |
| Issue #117 | Vervangen door het beperkte auditwerk uit dit plan. |
| Issue #118 | Sluiten wanneer de minimale foutafhandeling is vastgelegd. |
| Issue #120 | Open laten als concrete providerbehoefte ontbreekt, of sluiten na bevestiging dat discovery voldoende is. |
| Issue #130 | Oplossen door de campaign-limit volledig te verwijderen, niet door campaigns atomair binnen dezelfde limiet te maken. |

PR #128 is inmiddels op `main` gemerged. De nieuwe beheer-UI wordt niet integraal teruggedraaid; onderdelen worden alleen verwijderd wanneer een werkpakket daar een concrete reden en test voor heeft.

## Werkpakketten

### 1. Campaign-limit verwijderen

- Verwijder het veld “Maximaal aantal slides”.
- Verwijder sanitization van `limit`.
- Verwijder de tijdsafhankelijke rotatie en het gebruik van `time()`.
- Behoud groepen, kanaalfiltering, duur en intro-/outroafbeeldingen.
- Een oude geneste `limit`-waarde wordt onmiddellijk genegeerd en verdwijnt bij de eerstvolgende normale save. Er komt geen eenmalige upgrader voor een verder onschadelijke geneste sleutel.
- Vervang limitspecifieke tests door één regressietest dat alle slides van een geselecteerde campagne in volgorde blijven staan.

Acceptatie:

- Een campagne met meerdere slides wordt nooit door TekstTV opgesplitst of geroteerd.
- Bestaande loopconfiguratie met een oude `limit` blijft zonder fout werken.
- De volledige PHPUnit-, PHPStan- en PHPCS-suite slaagt.

### 2. AI-generatie vereenvoudigen

- Inventariseer welke prompt- en modelinstellingen in productie noodzakelijk zijn.
- Behoud systeem-/titel-/tekstprompt, relevante uitvoerlimieten en providerdiscovery.
- Verwijder instellingen die een redacteur niet zinvol kan beheren, tenzij de gebruikte provider ze vereist.
- Vervang identieke retries door één poging of één herstelpoging met gewijzigde instructie.
- Behoud capability-, feature-, minimum-input- en rate-limitcontroles.
- Voeg geen eigen persistent diagnostiek- of loggingframework toe.
- Normaliseer `teksttv_ai_prompts` bij save en laat vervallen sleutels verdwijnen.

Acceptatie:

- Generatie vanuit Gutenberg en de Classic Editor blijft werken.
- API-fouten worden begrijpelijk teruggegeven zonder gevoelige promptinhoud te loggen.
- Het aantal mogelijke providercalls per gebruikersactie is expliciet en begrensd.

### 3. Audit begrenzen

- Behoud de auditpagina, lijst, statusverdeling en de huidige betekenis van gewijzigd/ongewijzigd.
- Gebruik databaseaggregatie of een begrensde query in plaats van alle post-ID's in PHP te verzamelen.
- Voeg geen Levenshtein-achtige change score of combinatorische tekstvergelijking toe.
- Voeg alleen filters toe die een redacteur direct gebruikt om werk te vinden.

Acceptatie:

- De audit werkt met een groot aantal berichten zonder een onbegrensde ID-array op te bouwen.
- Bestaande permissies, paginering en statuslabels blijven intact.

### 4. Architectuur en tests

- Behoud het bewezen `BlockRegistry`-contract en de add-onpreservering.
- Verwijder abstraheringen alleen wanneer er geen tweede implementatie, publiek contract of relevante testgrens bestaat.
- Consolideer tests die uitsluitend private implementatie of identieke mockinteracties controleren.
- Behoud security-, REST-, scheduling-, campaign-, AI-, audit-, packaging- en upstream-contracttests.
- Vervang CSS-pixelasserties door semantische UI-asserties waar dat dezelfde regressie beschermt.

### 5. Extensierepository

- Behoud de vier Streekomroep-tickers, `Schedule`-grens, één request-scoped schedule en foutisolatie.
- Voeg echte missing-dependencytests toe; de huidige stubs maken die paden onbereikbaar.
- Kort evidente docblock- en callbackboilerplate alleen in wanneer WPCS en het registry-contract dat toelaten.
- Behoud de upstream-contracttest, maar beschrijf nauwkeurig dat het themacontract vooral method availability controleert.

### 6. CI en releases

- Verwijder de harde 90%-coveragegate en het coverage-artifact uit de extensie.
- Draai PHPCS en PHPStan eenmaal; gebruik een PHP-matrix alleen voor runtime-tests.
- Behoud één upstream-contractjob.
- Draai Plugin Check niet opnieuw in dezelfde releaseflow wanneer exact dezelfde commit in CI al is gecontroleerd.
- Houd package-allowlist, ZIP-inhoudscontrole en checksum in stand.
- Vereenvoudig releasebeslissingen tot een taggestuurde flow zonder recovery- en versievergelijkingsframework.

## Beoogde commits

1. `docs(engineering): add simplification baseline and plan`
2. `fix(campaigns): remove rotating slide limit`
3. `refactor(ai): simplify generation configuration`
4. `perf(audit): bound audit statistics work`
5. `test: remove implementation-heavy coverage`
6. `test(extensions): cover missing dependencies`
7. `ci(extensions): remove coverage and duplicate quality jobs`
8. `ci: simplify release workflows`

Werkpakketten mogen verder worden opgesplitst wanneer reviewrisico dat vereist. Productiegedrag, migratie en CI worden niet in één commit vermengd.

## Eindcontrole

- Alle relevante PHP-, frontend- en packagechecks slagen in beide repositories.
- De extensiecontracttest draait tegen de aangepaste hoofdplugin.
- E2E draait in CI of lokaal zodra Docker beschikbaar is.
- Het eindrapport vergelijkt bronregels, artifactgrootte, tests, CI-jobs en doorlooptijd met deze nulmeting.
- Bewust behouden complexiteit wordt benoemd, zodat een volgende “cleanup” haar niet opnieuw als ongebruikte code verwijdert.
