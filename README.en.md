<p align="center"><img src="app/assets/icon.svg" width="88" alt=""></p>

<h1 align="center">Search engine without ads</h1>

<p align="center"><b>Your own search engine. No ads, no tracking, on your own web space.</b><br>
Real Google results (through Bright Data) and Brave Search, with load balancing and failover,<br>
entirely within the free monthly quotas for normal private use.</p>

<p align="center"><a href="README.md">Deutsche Version</a> · <a href="../../releases/latest">Download the latest version</a></p>

<p align="center"><img src="docs/en/ergebnisse.png" alt="Results page with top stories and overview panel" width="820"></p>

> ⚽ **New in 2.4:** live cards like Google for every search, also with “newest first”: today’s match with crests and kick-off, stock prices, opening hours, celebrity ages. [More](#new-in-24-live-cards-like-google)
>
> ⚡ **New in 2.3:** the page appears right away, Google and Brave race each other, **newest first** everywhere with automatic time steps, images in **best quality first** and **search suggestions** while you type. [More](#new-in-23-faster-newest-first-best-images)
>
> 🚀 **Version 2.2, Google results.** Real Google results for web, images, news and videos through Bright Data, 5,000 requests a month free. Plus live info (scores, stock prices, opening hours, AI overview) and Google’s filters. [Everything that is new](#new-in-22-google-results-through-bright-data)

Upload `suchmaschine-install.php`, open it in the browser, create your account, paste one or more API keys. That is the whole installation.

The server runs PHP and SQLite, the browser plain JavaScript and CSS. No frameworks, no dependencies, no build step, no MySQL. Runs on ordinary shared hosting.

## Screenshots

<table>
  <tr>
    <td align="center"><img src="docs/en/start.png" width="400" alt="Home page"><br><sub>Home page</sub></td>
    <td align="center"><img src="docs/en/dunkel.png" width="400" alt="Dark theme"><br><sub>Dark theme</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/en/bilder.png" width="400" alt="Image search"><br><sub>Image search</sub></td>
    <td align="center"><img src="docs/en/bildvorschau.png" width="400" alt="Image preview"><br><sub>Image preview</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/en/news.png" width="400" alt="News"><br><sub>News</sub></td>
    <td align="center"><img src="docs/en/videos.png" width="400" alt="Videos"><br><sub>Videos</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/en/vorschlaege.png" width="400" alt="Suggestions from recent searches"><br><sub>Suggestions from your own recent searches</sub></td>
    <td align="center"><img src="docs/en/verwaltung.png" width="400" alt="Admin settings"><br><sub>Admin: key, name, logo, color</sub></td>
  </tr>
</table>

<p align="center">
  <img src="docs/en/handy-start.png" width="200" alt="Phone: home">
  <img src="docs/en/handy-ergebnisse.png" width="200" alt="Phone: results">
  <img src="docs/en/handy-bilder.png" width="200" alt="Phone: images">
</p>

[Cost](#what-does-it-cost) · [Setting up search services](#setting-up-search-services-getting-api-keys) · [Features](#features) · [New in 1.5](#new-in-15) · [New in 1.4](#new-in-14) · [Reader mode and PDF](#reader-mode-and-pdf) · [New in 1.3](#new-in-13) · [New in 1.2](#new-in-12) · [Accounts and token login](#accounts-and-token-login) · [Keyboard shortcuts](#keyboard-shortcuts) · [Requirements](#requirements) · [Installation](#installation) · [Updating and backup](#updating-and-backup) · [Building it yourself](#building-it-yourself) · [Good to know](#good-to-know) · [Disclaimer](#disclaimer) · [License](#license)

## What does it cost?

Nothing for normal private use. The search engine can use two search APIs, each with its own free monthly quota. One is enough, both together give a big buffer:

| Service | Free per month | Counted in | Default limit | Can do |
|---|---|---|---|---|
| [Bright Data](https://brightdata.com) (Google) | **5,000 requests**, reset on the 1st | requests | 4,950 | web, images, news, videos, info box with opening hours, scores, stock prices, AI overview, all Google filters |
| [Brave Search](https://brave.com/search/api/) | $5 credit, about **1,000 searches** | searches | 950 | web, images, news, videos, info box |

- Each service gets its **own limit** in the admin page. The default is always the free quota **minus 50 as a buffer**. When a limit is reached, the next service takes over automatically.
- **Reset date:** Brave reports when its quota restarts, the search engine takes it over automatically. For Bright Data it is the first of the month (the default).
- **Usage according to dashboard:** enter in the admin page what the dashboard currently shows as used, any time. The counter is set to exactly that value and keeps counting from there.
- **Don’t add a card** or switch off automatic top-up. Bright Data’s plan is “pay as you go” ($1.50 per 1,000 requests beyond the free quota): without a card nothing can be charged, and the limit in the search engine stops earlier anyway.
- A **cache** makes reloading, the back button and paging free: up to 60 minutes for Brave (more is not allowed by Brave's terms), 1 hour by default for Google (scores and stock prices at most 5 minutes).
- **Instant answers** (calculator, currencies, weather …), **bangs** and **tools** cost nothing at all, because they do not ask a search service.

## Setting up search services (getting API keys)

Enter the keys after installation under **Admin → Search services**. Every key is checked with a test search first. Services without a key do not show up anywhere. Keys **never** belong in code or Git, they only live in your database in `data/`.

**Bright Data (Google):** sign up at [brightdata.com](https://brightdata.com) (**don’t add a card**). Under **"Web Access" → "Add API"** choose **"SERP API"**, give it a **name** (e.g. `serp_api1`, cannot be changed later), set **data format "Full JSON"**, leave the **advanced settings off** and tap **"Add API"**. Then enter that name as the **zone** together with the **API key** (looks like `1a2b3c4d-…`) under *Admin → Search services*. Optional for reader mode: add a **"Web Unlocker API"** and enter its name as the **Web Unlocker zone**.

**Brave Search:** sign up at [api-dashboard.search.brave.com](https://api-dashboard.search.brave.com), choose the **"Search"** plan (a card is required against abuse, billing works with credit, no subscription), **keep automatic top-up off**, create a key under **"API Keys"** (starts with `BSA`).

In the admin page you also set the **order** (who is asked first), switch **load balancing** on or off, pick the services for **"All together"** and the **fallback target** for emergencies.

## Features

- **Web, images, news, videos** in tabs.
- **Mixed results** like the big search engines: top stories, videos, "People also ask" and forum discussions between the results, plus an **overview panel** (e.g. from Wikipedia).
- **Page by page or endless scrolling**, per account.
- **Time filter**, **spelling correction** with "Search instead for …", **"Feeling lucky"**.
- **Large image preview** with arrow keys and swiping, plus a **real full-screen mode** (button or key `F`) for TVs and big screens: borderless on black, controls fade out. The image is loaded in **full resolution through your own server**, without loosening the strict security rules and without using a Brave search.
- **File type filter** (PDF, DOCX, XLSX, PPTX, TXT, EPUB, CSV, ODT and more, or type any other extension). Kept while paging and endless scrolling.
- **Help page** with every search operator Brave supports (`site:`, `filetype:`, `intitle:`, `"…"`, `OR`, `NOT`, `-` …), clickable examples and an overview of your own bangs, lenses and domain lists.
- **Suggestions from your own recent searches**, stored only in the browser.
- **Light and dark**, automatic or by button. **German and English.**
- **Phone and desktop**, can be added to the home screen as an app.
- **Default search in your browser** via OpenSearch.
- **Accounts:** whoever signs up first becomes the administrator and can add more accounts, e.g. for the family. Every account has its own settings.
- **Admin page:** search services with keys, reference date, limits and status, name, **own logo**, **color**, defaults for everyone, usage with forecast, per-account quotas, optional searching without an account (with an hourly limit).
- **One-click updates** from GitHub releases, verified by checksum, with one-click rollback. Optionally automatic once a day.
- **Self-test** that the internal folders are really blocked from outside.
- **No ads, no tracking, no third-party scripts.** Strict Content Security Policy, links open without referrer.

## Like ..., but free

Some paid search engine are known for a few ideas that are available here for everyone who self-hosts. Every account has **its own space**: what one person sets up, the others do not see.

- **Block, raise and lower domains.** Unwanted sites disappear, favorites move up with a small star, others drop to the end. Quickest via the ⋮ menu next to every result, or as lists under *Settings → Domains*. Filtering happens after fetching, so the cache stays the same for everyone.
- **Bangs** like `!w Berlin`, `!gh`, `!yt`, `!osm`, `!so`, `!r` jump straight to another site's search. They are handled before Brave is asked and **cost no credit**. `!i`, `!n` and `!v` switch to images, news and videos. **Over 10,800 bangs** from [Kagi’s open list](https://github.com/kagisearch/bangs) (MIT) are built in, plus German extras. Typing `!` (or the `!` button) suggests bangs and shows where they lead. Under *Settings → Bangs* you can search, edit and delete any bang and restore the defaults; admins can do this for everyone. The list updates itself weekly and targets are checked for reachability, without using searches. Works from the browser's address bar too.
- **Lenses** narrow the search to a topic such as forums, programming, wikis or science. Everyone can build **their own lenses** from domains and operators and hide the built-in ones. Pick one on the results page under "Lens".
- **Back up and restore:** under *Settings → Account* every account downloads its settings including lists, bangs and lenses as a file and restores them when needed, even on another installation. The administrator can also back up the installation settings.

## New in 2.5: visible faster, menu for news and videos

- **Instant waiting page:** after Enter the page with search box, filters and “Searching …” appears at once, even on hosts that send responses in one piece (e.g. Plesk with nginx in front of Apache). The search runs in the background, then the page loads the results from the cache.
- **Longer cache:** Google results are kept for 6 hours by default (news and the past 24 hours 1 hour, scores and prices 5 minutes).
- **⋮ menu for news and videos too:** raise, lower, block, search this domain only, read without ads, QR code, copy address.

## New in 2.4: live cards like Google

- **Cards for every search:** matches, stock and index prices, opening hours, info box (e.g. age and birthday of celebrities) and Google’s AI overview above the results, also with “newest first”. The result list doesn’t wait for them, the cards follow a moment later. That costs one more Google request on the first page.
- **Sports card like Google:** today’s match with league, kick-off, crests and score on top, then upcoming matches and recent results with the winner. Kick-off times are always in your time zone.
- **Google from your country:** Bright Data now fetches Google from the country set as region in your search settings instead of anywhere in the world.
- **Smarter race:** if Google returns an empty answer, Google is asked again right away together with Brave. Each account sets its own waiting time before Brave steps in under *Settings → Search*.
- The AI overview drops Google’s closing question, and a mixed-up info box (e.g. “Dell” for “apple stock”) is corrected.

## New in 2.3: faster, newest first, best images

- **Show the page right away:** after Enter the search box and filters appear at once, the results follow as soon as they arrive. Can be turned off under *Admin → Search services*.
- **Race:** if Google hasn’t answered within 3 seconds (6 for images), Brave is asked as well and the faster answer wins. Adjustable (0 = off). Only then does it cost an extra Brave search.
- **Redirects resolved on click:** Google’s redirect links are resolved by your server only when you click, saving half a second to a second per search.
- **Newest first everywhere:** All, News and Videos start with the past 24 hours, sorted by date, then continue by themselves with the past week, month, year and everything older, while scrolling or paging. Empty periods are skipped, nothing shows up twice. Set the starting period under *Settings → Search*; “Relevance” is still available under “Sorting”.
- **Images: best quality first.** With Google in steps: images from 4 megapixels, then from 2 megapixels, then large ones, then all, without duplicates. With Brave sorted by resolution. Switchable to “Relevance”.
- **Search suggestions from Google** while typing, below your own recent searches. Free, uses no requests, goes through your server. Can be turned off.
- **Filters without an OK button:** language, region and custom date range apply right after choosing.
- **Fixed:** with a time filter, Google via Bright Data sometimes returned nothing for web searches. The web-only tab is used then.

## New in 2.2: Google results through Bright Data

- **Real Google results** for web, images, news and videos through [Bright Data](https://brightdata.com)’s SERP API. 5,000 requests a month free, more than 160 searches a day. Google comes first, Brave steps in when Google is used up or down. The order can be changed in the admin page. Pick one with `!bd` or `!brave`, both with `!multi`.
- **Live info above the results:** scores (e.g. “bayern vs dortmund”), stock and index prices (“dax”), **opening hours**, address, phone and rating of businesses in the info box, and **Google’s AI overview** with sources (can be turned off). Scores and prices are cached for at most 5 minutes.
- **Filters like Google:** time with **past hour** and **from/to**, **language and region** per search, **verbatim**, images by **colour** (including single colours), **type** (photo, clip art, line drawing, GIF, face) and **licence** (Creative Commons), size and format now applied by Google, **videos by length**. What Brave can’t do itself (past hour, video length) is filtered afterwards.
- **Related searches** below the results.
- **Private as before:** Google wraps links in its own redirects, your server resolves them. Google’s thumbnails are loaded through your server. Google only ever sees your server, never your visitors.
- **Reader mode:** “Try via Bright Data” instead of Firecrawl (optional, with a Web Unlocker zone).
- **Cleaned up:** Tavily, Exa, parallel.ai and Firecrawl are gone. On update their keys and settings are removed, Google is moved to first place, usage history stays.

## New in 2.0: the big tools upgrade

> **Version 2.0 is a mega upgrade.** 15 small helpers become **47 tools**, many of them real little apps. They all run on your own server or right in the browser, none of them asks a search service, none costs credits. The update installs with one click as usual, your data and short links stay.

<p align="center"><img src="docs/en/werkzeuge.png" alt="Tools with search, favourites and groups" width="820"></p>

<table>
  <tr>
    <td align="center"><img src="docs/en/qr-studio.png" width="400" alt="QR studio"><br><sub>QR studio with design and read test</sub></td>
    <td align="center"><img src="docs/en/passwort.png" width="400" alt="Password generator"><br><sub>Password generator with strength check</sub></td>
  </tr>
  <tr>
    <td align="center"><img src="docs/en/waehrung.png" width="400" alt="Currencies with rate history"><br><sub>Currencies with ECB rate history</sub></td>
    <td align="center"><img src="docs/en/wetter.png" width="400" alt="Weather with warnings"><br><sub>Weather with hours, 7 days and official warnings</sub></td>
  </tr>
</table>

**Overview:** tool search (key `/`), favourites with ☆, “recently used”, shortcuts in the search box such as `!qr Hello`, `!pw`, `!short https://…`, `!json`, `!dns example.org`.

**QR codes and scanner** (building blocks from CodeForge, same author)
- **QR studio:** templates for link, text, Wi-Fi, contact (vCard), phone, SMS, email, event, location, **GiroCode**, **2FA key**, product (GS1) and **dynamic QR codes** (they point to your own short link, the target can be changed later). Colours, gradients, six dot shapes, custom corners, **logo in the centre**, frame with caption. Also Data Matrix, Aztec, PDF417 and all barcodes. Export as PNG (up to 4096 px) and SVG.
- **Read test:** every code is automatically printed small, blurred, tilted and darkened and read back, with a print size recommendation.
- **Scanner:** webcam on the PC, camera on the phone (with torch, zoom, camera switch), photos and screenshots (also with Ctrl+V). QR, Data Matrix, Aztec, PDF417, EAN, UPC, Code 128 and more. Batch and inventory mode with CSV.
- **Search what you scan:** camera button in the search box, scan a barcode, ISBN or text and the search starts right away. In the scanner optionally with “Search right away”.
- **Link X-ray against quishing:** shows the real domain, warns about look-alike addresses (`pаypal.com` with a Cyrillic a), foreign brand names, IP addresses and tricks. The server resolves short links without opening the page.
- **Print QR:** guest Wi-Fi sign, table card, business card, donation sign, label sheets (Avery, Herma, label printers) with serial numbers.
- **Number detective:** check IBAN, EAN, ISBN, ISSN, PZN, IMEI, with check digit and a hint which digits were swapped.

**Security and sharing**
- **Password generator:** random characters, passphrases (large German and English word lists), PINs without 1234 and the like, pronounceable, custom pattern, tokens (hex, Base64url, Base32, UUID). Presets for Wi-Fi, online account, maximum security and fussy sites, 50 at once with CSV, **strength check** for your own passwords with crack time and pattern detection. Everything in the browser only.
- **Short links 2.0** (ideas from [ShrinkURL](https://github.com/burnersen/ShrinkURL)): custom name, expiry date, visit limit (“only once”), password, intermediate page against messenger previews, preview with `+` at the end, remove tracking parameters, change the target later, on/off, **statistics as a daily counter only** (no IP, no cookie), target title, check targets for dead links, CSV, bookmarklet, entry in the phone's share menu.
- **Text snippets:** encrypted in the browser (AES-256-GCM), the key is only in the link after the `#`. Expiry, “gone after reading”, optional password (a wrong password does not use up a view).

**Calculating:** calculator that works as you type (history, scientific, degrees/radians), percent and VAT, rule of three, live units (now also fuel economy, pressure, energy, power, angle, cooking), currencies with several targets and **ECB rate history**, compound interest, savings plan, loan with repayment plan, fuel costs, split the bill, Roman numerals, Morse code with sound and light.

**Time and date:** several timers at once, Pomodoro, interval training, alarm, stopwatch with laps (timers survive a reload), countdown with title, full screen and link, **working days with the holidays of a German state**, weekday, **bridge day planner**, holidays as a **calendar file (.ics)**, printable year calendar, world clock with meeting planner.

**Network and web** (after signing in, with safe fetching modelled on [LinkVault](https://github.com/burnersen/LinkVault)): what sites see about you (headers, browser, WebRTC), is the site down?, headers and redirects with security check, SSL certificate, DNS with SPF and DMARC, WHOIS via RDAP, link checker for up to 200 links, **link preview** (how your link looks in messengers), **clean up bookmarks** (remove duplicates and dead links from a browser export).

**For developers:** JSON (format, validate, CSV), encode (Base64, URL, HTML, hex, binary, Unicode), hash (SHA-256/512, SHA-1, MD5, CRC32, HMAC, also of files), JWT decoder with signature check, UUID v4/v7, ULID, Nano ID, regex tester, cron explainer with the next runs, compare text, colours with WCAG contrast, number systems, subnet calculator (IPv4 and IPv6), chmod, `.htpasswd`, text tools, Unix timestamp.

**Everyday:** Lotto 6 out of 49, Eurojackpot, draw a card, yes or no, **weather with hours, 7 days, UV and official German Weather Service warnings**.

**Removed:** “draw lots and make groups”.

**Privacy stays:** passwords, QR contents, hashes, JWTs, regexes and texts never leave the browser. Outbound requests (network tools, link X-ray) are made only by your server, only to public addresses, without storing anything. The scan engine (zxing-wasm, MIT) lives on your server and is only loaded when you scan.

## New in 1.5

- **Tools reorganised:** a tile overview by group (calculating, time and date, chance, sharing and security, weather and network); every tool opens on **its own page** instead of one long list, with related tools below. Reachable everywhere via the **grid icon at the top right**, also for visitors when searching without an account is allowed.
- **Settings tidied up:** login token and “default search in the browser” are now together under **Settings → Browser**. The administrator edits the look for everyone under **Admin → Appearance**.
- **Getting API keys, step by step,** right next to each search service in the admin page.
- **1.5.1:** currency rates once a day after the ECB plus a “Fetch rates now” button. “My IP address” can also show your IPv4 on an IPv6 connection (and vice versa), ideally via your own IPv4-only subdomain (Admin → Settings), otherwise on click via ipify.org. The appearance preview stays visible while scrolling.
- **1.5.2:** endless scrolling only loads more after you reach the bottom and deliberately pull on.
- **1.5.3:** “Usage according to dashboard” sets a service's counter to exactly the dashboard value and keeps counting from there (for every service that does not report its usage).
- **1.5.4 and 1.5.5:** signed updates tried and switched off again. Updates are checked by checksums; signing stays optional (see [BUILD.md](BUILD.md)).
- Small fixes: the name on the results page is no longer cut off, a more compact currency card, tabs on phones show that they scroll. Without any search service, the administrator is sent to the setup instead of being redirected.
- **For developers:** code split by topic, English texts in `app/lang/en.php`, automated tests, checks on every push, reproducible builds, optional signed updates. Users still get **one single installation file**. How it is built: [BUILD.md](BUILD.md).

## New in 1.4

- **Appearance** (Settings → Appearance, per account, with an installation default): 17 light and dark designs, including muted ones (Paper, Mist, Sage, Slate, Dusk), the colour mixer from “Kaufen” with sliders, colour code, live preview, contrast warning and automatic matching, five built-in fonts served from your own server (Atkinson Hyperlegible, Lexend, Source Sans 3, Inter, Literata, all SIL OFL, nothing from Google), sizes, line spacing, spacing and list width.
- **Endless or paged for all tabs.** **Loading with resistance:** plain scrolling loads nothing; only after reaching the bottom and deliberately pulling on (a clear swipe on phones, several wheel notches) do more results load. **Images:** 200 per request, paging is free afterwards.
- **News:** Brave fetches 50 at once, older news comes from Google with an exact date range. No empty sections.

## Reader mode and PDF

“Read without ads” in the **⋮** menu of every result (accounts only): your server fetches the page and keeps only the content. Recipes are detected from their structured data (ingredients, steps, image), switchable to the whole article. Optional fallback through Bright Data’s Web Unlocker (1 request). **Save as PDF** in the browser with live preview, font, size, line spacing, 1 or 2 columns, margins and selectable images, or **Print**.

**Please use it fairly:** for private reading and archiving only. Accounts only, at most 60 pages per hour, nothing is stored on the server, paywalls are not bypassed, and source, date and “for private use only” appear in the view and on every PDF page. Rights remain with the original sites.

## New in 1.3

- **Newest first** is the default for "All" and news. Every account can change it under *Settings → Search*, the administrator sets the default for everyone. Switch any time with "Sorting" above the results.
- **News from the last 24 hours.** At the end of the list it continues with the last week, then the last month and the last year. If there is no recent news at all, the range widens right away. Range and widening are per account.
- **Reset date from the service:** Brave (with every search) and Firecrawl (own endpoint, at most every 6 hours, free) report when their quota restarts. Firecrawl and Tavily also report their remaining credits. A field covers services that cannot (since 1.5.3 **“Usage according to dashboard”**).
- **New tab everywhere:** "Open links in a new tab" applies to every outgoing link, including weather, overview panel and loaded results.
- **Bangs without flashing:** the redirect page is empty and uses the page colours; a "Continue" button only appears after 2 seconds if the target hangs.

## New in 1.2

*Since 2.2 only Google (through Bright Data) and Brave are left. Tavily, Exa, parallel.ai and Firecrawl are gone, everything else here still applies.*

**Several search services, load balancing, failover**
- **Automatic:** the first service in your order that still has credit and works. Above every result list you see who delivered and why another one was skipped.
- **Load balancing:** if a service is used faster than its quota lasts until the reset, searches go to the next one earlier, so all quotas last evenly through the month.
- **Failures:** if a service returns an error or cannot be reached, the next one steps in right away. The failing service pauses for 10 minutes (one hour for a wrong key). The admin page shows last success, last error and average response time.
- **When nothing works anymore** (all used up or failing), the search redirects to a fallback target such as Startpage, DuckDuckGo, Mojeek or your own address with `{q}`. So the search always works.
- **Pick a service:** `!brave`, `!tv` (Tavily), `!exa`, `!pa` (parallel.ai), `!fc` (Firecrawl), or click one in the **source bar** above the results, with the remaining credits next to it.
- **All together:** `!multi` asks several services at once, merges duplicate addresses and marks every result with badges like `[Brave]` `[Exa]` `[Tavily]`.
- **Quick answer** from Tavily (no extra cost) and a **Wikipedia summary** when a service has no info box of its own.

**Usage, forecast, quotas:** per service used/limit, period, **reset date with days left**, today, **daily average**, **projection until the reset** and a clear verdict whether it lasts. A banner warns administrators from 80 % (configurable). **Per-account quotas** decide which services an account may use and how many credits it gets; "Split fairly" divides the limits evenly.

**Instant answers (free):** `(12.5 + 7) * 3`, `200 + 19%`, `15% of 80` (own parser, no `eval`) · `100 usd in eur` (ECB rates, cached daily) · `10 km in miles`, `20 °c in f`, `1 gib in mb` · `weather london` (German Weather Service via Bright Sky, Open-Meteo or wttr.in) · `my ip` · `week`, `week 12` · `date` · `dice`, `2d20`, `coin`, `random 1-49` · `timer 5 min`, `stopwatch`, `countdown christmas` · `password 32`, `passphrase` · `qr https://…` · `short https://…`.

**Tools** (grid icon at the top right): 47 helpers in one place, by group, with search, favourites and “recently used”, each on a page of its own (see [New in 2.0](#new-in-20-the-big-tools-upgrade)). In the search box: `days between 2026-01-01 and 2026-12-24`, `workdays between 2026-12-01 and 2026-12-31 bavaria`, `age 1940-05-12`, `holidays 2027 bavaria`, `bridge days 2027 nrw`, `lotto`, `!qr Hello`, `!pw`, `!short https://…`.

**Filters and results:** strict sort by date (newest first, undated last), **max. 3 results per domain** (on by default), **"Reset all filters"**, a large **×** in the search box, and in the **⋮ menu**: search only this domain, archive at web.archive.org and archive.today, show as QR code, copy address.

**Images:** filters like Google for orientation (landscape, portrait, square) and size (very large from 1 MP, large from 0.3 MP, medium, small below 0.15 MP), **sort by resolution**, and a full screen that always shows the whole image centred on black, never cropped.

**Complete backup:** under *Admin → Backup* one file with everything (settings, all API keys, accounts with password hashes, login tokens, personal settings, quotas, short links, usage, logo), **optionally encrypted with a password** (AES-256-GCM, key derived with PBKDF2).

## Accounts and token login

- The first visitor creates the administrator account. The Brave key is optional there; keys can be added later under *Admin → Search services*. More accounts are created under *Admin → Accounts*, optionally with a quota.
- **Login token:** under *Settings → Browser* every account creates a long secret token (48 characters) or sets its own (at least 32 characters, at least three kinds of characters). Token, login address and search address can be shown, copied and renewed there, only while signed in.
- `https://your-domain/search/?token=YOUR_TOKEN` (also combined: `?token=…&q=…`) signs you in and sets a session cookie for **365 days** (`HttpOnly`, `Secure` on HTTPS, `SameSite=Lax`). The token is removed from the address right away by a redirect. The session lives on the server (the cookie holds a random value, the database only its hash), so it can be ended anytime: sign out, clear cookies or *Sign out all other devices*.
- **Default search:** add the search address with token (`…/?token=…&q=%s`) to your browser, or in Firefox add it from the account page via the magnifier icon (OpenSearch with token).
- **Security:** wrong tokens are throttled (growing wait after 5 failures), comparison is constant-time, the token is stored encrypted with a key in `data/config.php`. Treat it like a password; web server logs may record addresses containing it.

## Keyboard shortcuts

`↓`/`J` and `↑`/`K` move through the results with a visible frame · `Enter` opens (with `Ctrl`/`⌘` in a new tab) · `←` `→` previous/next page or image · `/` focuses the search box · `F` full screen in the image preview · `?` shows all shortcuts · `Esc` closes overlays, menus, preview and full screen. They only work when no input field is focused.

## Requirements

- Web space with **PHP 8.0 or newer** and the extensions `pdo_sqlite` and `curl`.
- Outgoing connections allowed: to the search APIs (`api.brightdata.com`, `api.search.brave.com`), to `www.google.com` (resolving redirect links) and `*.gstatic.com` (Google’s thumbnails), for instant answers to `api.frankfurter.dev`, `geocoding-api.open-meteo.com`, `api.brightsky.dev`, `api.open-meteo.com`, `wttr.in`, `*.wikipedia.org`, and for updates to `github.com`.
- The `openssl` extension for the encrypted backup and the encrypted login tokens (almost always available).
- **HTTPS.**
- **Apache** (or LiteSpeed) blocks the internal folders by itself via `.htaccess`. **nginx** needs two lines in the configuration, see below.
- At least one search service **API key** (free, see above).

## Installation

1. **Get API keys**, see [Setting up search services](#setting-up-search-services-getting-api-keys). One is enough to start.
2. Download **`suchmaschine-install.php`** from the [latest release](../../releases/latest) and copy it to a folder on your web space.
3. Open it in the browser **right away** and click **"Install now"**. It unpacks the program, deletes itself and redirects.
4. **Create your account** right afterwards and optionally **paste the Brave key**. The key is checked with a test search. This account is the administrator. Add Google through Bright Data (5,000 requests a month free) under *Admin → Search services*.
5. **Test:** in Admin → *Update → Data protection* click "Check now". It must say "All tight".

On **Plesk** hosts (e.g. Netcup), nginx in front of Apache may serve files directly and skip `.htaccess`. Open the domain's **"Apache & nginx Settings"** and turn off **"Serve static files directly by nginx"** / **"Smart static files processing"**.

With your own nginx configuration, add this to the `server` block (adjust the path if installed in a subfolder):

```nginx
location ^~ /data/ { deny all; return 404; }
location ^~ /app/  { deny all; return 404; }
```

## Updating and backup

Admin → *Update* → "Check for updates" → "Update now". The running version is saved first and can be restored with one click. Or upload a new `suchmaschine-install.php` and open it: only program files are replaced, `data/` is never touched. Set `'update_url' => ''` in `data/config.php` to switch off update checks.

Everything that matters is in the folder **`data/`**: accounts, settings, API keys, logo. Download that folder to back up. In the browser: the **complete backup** (optionally encrypted) and the installation settings under *Admin → Backup*, personal settings of every account under *Settings → Account*.

## Building it yourself

The source is split into many files, users still get **one file**. Anyone can build it with nothing but PHP: `php tools/build-release.php` checks the syntax, runs all tests, builds `dist/suchmaschine-install.php`, installs it as a test and writes checksums. Builds are reproducible, so every release can be compared with the source. Details, including signed updates: [BUILD.md](BUILD.md). Contributing: [CONTRIBUTING.md](CONTRIBUTING.md), security issues: [SECURITY.md](SECURITY.md).

## Good to know

- **Privacy:** the search services see the search terms but not who searches, the request comes from your server. Only the number of requests per account is counted, never their content. The search engine itself stores no search terms except briefly in the cache. Recent searches for suggestions stay in each device's browser.
- **Full-resolution images:** for the large preview the server fetches the original image and passes it on. The other site only sees the server, the browser still only loads from your own address. Every image address is signed, internal networks are blocked, nothing is stored. It costs no search, only some traffic, and can be switched off in the admin page.
- **Search operators** are passed to the search service unchanged. Brave understands most of them, the other services mainly quotes and `site:`.
- **Endless scrolling:** every reload is a real Brave search, so 20 results are fetched at a time. Brave delivers at most 10 pages per query.
- **Providers' terms:** results may not be resold, Brave only allows short-term caching. The services used are named at the bottom of every page.

## Security

For 1.2 the search engine was reviewed from an attacker's point of view and hardened: every output escaped and a strict CSP without inline scripts (XSS) · session token plus same-origin check on every form, token login only when opened directly and never silently switching accounts (CSRF) · prepared statements only (SQL injection) · signed image proxy limited to public IPs with pinned DNS, fixed HTTPS targets without redirects for weather, rates and Wikipedia (SSRF) · throttled password and token logins with constant-time comparisons (brute force, timing) · own calculator parser without `eval` · fixed file names and checksummed updates (path traversal) · login tokens encrypted at rest, server-side sessions that can be ended anytime.

## Disclaimer

Free hobby software, provided **as is, without any warranty**. Use at your own risk. Whoever runs it is responsible for the server, its updates and backups, and for complying with the providers' terms. This project is not affiliated with Brave Software, Bright Data or Google.

## License

GNU Affero General Public License v3.0, see [LICENSE](LICENSE).

Built in since 2.0: scan engine [zxing-wasm](https://github.com/Sec-ant/zxing-wasm) (MIT) with zxing-cpp (Apache 2.0), details in `app/assets/vendor/LIZENZEN.txt`. Passphrase word lists: German from [dys2p/wordlists-de](https://github.com/dys2p/wordlists-de) (CC0), English from the [EFF](https://www.eff.org/deeplinks/2016/07/new-wordlists-random-passphrases) word lists (CC BY 3.0 US). Icons in the style of Lucide (ISC).
