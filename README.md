# RizQuiz

Live trivia with prizes. Hosts build a quiz, launch it, and players answer on their phones.

## Pages
| Page | Who | What |
|---|---|---|
| `login.php` | Hosts | Sign in: password + 6-digit code from an authenticator app (always required). |
| `setup.php` | Hosts | Step by step: Event & day → Questions → Prizes → Game settings → **Launch**. Also Players & winners, My account. |
| `live.php` | Hosts | Opens after Launch. One big **next-step button** (Start game → Reveal → Draw prize → Next question → Podium), a clear **Auto prizes ON/OFF** pill, and everything else in the ☰ menu. |
| `master.php` | Master admin | Create, pause, extend and remove host accounts. |
| `<web name>/` | Players | Each host's own folder, e.g. `/rizquiz/dean/`. The QR code points here. Created automatically. |
| `index.php` | Players | Type a game code or host name if you have no QR code. |
| `stage.php?g=<web name>` | Projector | Optional clean big-screen window (the Live page shows the same stage). |

## First run
1. Put this folder in `C:\mamp\htdocs\RizQuiz` and start MAMP.
2. Open `http://localhost/RizQuiz/login.php`. The first visit creates the **master admin**. It is permanently
   linked to (714) 887-7971, and you must type that number to create it. Then set up the authenticator app
   (Google Authenticator, Microsoft Authenticator, Authy, 1Password…) and **save the backup codes**.
3. Questions and scores from earlier versions move into the master account automatically.

## Selling / host accounts
The master admin creates each host in **Accounts** (name, organization, username, web name, optional end date).
RizQuiz copies the sign-in details to your clipboard so you can send them. On first sign-in the host chooses a
password and sets up their authenticator. Every host has private questions, events, players, and their own QR
code and folder. **Pause** blocks sign-in and takes their game offline. **Reset 2-step** helps a host who lost their
phone. If the master loses both the phone and the backup codes, set `MASTER_RESET_2FA` to `true` in `config.php`,
sign in, set up a new authenticator, then set it back to `false`.

## Phones on Wi-Fi (big events)
Everything the phones need (styles, fonts, icons, QR and confetti code) is served by your RizQuiz server.
Nothing is loaded from the internet, so the game works on any Wi-Fi that can reach your server:

- **Best: host RizQuiz on a web server** (your college's server or any PHP host). Then players can use the venue
  Wi-Fi, their own data, or anything else with internet.
- **No internet at all: bring a Wi-Fi router.** Connect the MAMP laptop and the phones to it. In **Game settings**,
  set the server address to the laptop's network address (run `ipconfig` and find the IPv4 address, e.g.
  `http://192.168.1.25/rizquiz/`). Fill in **Event Wi-Fi**, and the lobby shows a "Join Wi-Fi" QR code next to the game QR.
- Venue *guest* Wi-Fi often blocks phones from reaching a laptop on the same network. Use your own router or a
  hosted server instead.
- Short links (is.gd, bit.ly…) need internet on the phones. Turn **Use the short link** off for no-internet events.

## Running the game (Live page)
- The big green button always shows the next step. Its small line says what it will do.
- The **🎁 Auto prizes** pill shows whether a prize is drawn automatically after each question. Click it to switch.
- **Present** (or **F**) goes full screen. A small control bar appears when you move the mouse.
- Presentation clicker or keyboard: **→ / Space / Page Down / Enter** = next step, **W** winner, **L** leaderboard,
  **P** podium, **F** present, **Esc** exit.

**Reset game** (☰ menu → Danger zone, or Players & winners → Reset game) deletes every player of the current
event (all days) with their scores, answers, ideas and prize winners. Questions, settings and branding stay. A big
warning appears first. Pro and up can download an Excel backup right in the warning. Type RESET to confirm.

**Removing one player** (Players & winners → Remove): deletes that player's score, answers, words, ideas and
prizes. Tick **Block this name** for offensive names; look-alike spellings are blocked too. Blocked names are
listed under the players table, where you can allow them again.

## Question types
| Type | Players… | Scoring |
|---|---|---|
| Multiple choice (default), True/False | tap an answer | faster + correct = more points |
| Type the answer | type text (several accepted answers allowed) | same |
| Closest number | slide/type a number (with a tolerance) | same |
| Put in order | arrange items | same |
| Poll, Rating | vote / rate | points for taking part |
| Word cloud | type up to N words | points for taking part |
| Idea board | share ideas, then vote ⭐ on the best one and 👎 the weak ones | see below |

**Idea board:** phase 1, players submit ideas. Phase 2, players vote. An idea with X thumbs-down (you choose X) is
removed. The idea with the most ⭐ wins. If **Auto** points are set, the best idea earns what a typical timed
question in the bank is worth (its average top score), and everyone who voted earns a quarter of that. You can
also type fixed numbers.

**Prize per question:** each question can be eligible for a prize (and pick its own prize type) or not.

## Questions
- Every JSON file you upload, or set you paste, becomes its own **question bank**. Load any bank, or add one bank's questions to another.
- Click the pencil on a question and the editor opens right below it.
- **Comment shown with the correct answer** (optional) appears on the big screen and on phones at the reveal.
- **Name** (optional, only hosts see it) lets you pull a question up instantly during the game.
- JSON format: `[{"name":"Tiebreaker","text":"…","options":["A","B","C","D"],"correctIndex":0,"timeLimit":15,"note":"Fun fact…"}]`

## Prizes
Random correct answer, random player, fastest correct answer, or Mix. Prizes can run automatically after every
question, or you can click **Winner!** any time. "Can someone win more than one question prize?" only limits
question prizes. The **Grand Podium always shows the top scores**, so people who already won a question prize can
still be on the podium.

## Excel, PowerPoint, music, branding
- **Players & winners → Download Excel**: standings, every answer, prize winners, ideas and word clouds.
- **Questions → PowerPoint**: a slide for each question, plus answer slides and speaker notes. Click **▶ Run this
  question live** on a slide to start that question in RizQuiz.
- **Music**: put audio files (mp3, wav, ogg, m4a) in the RizQuiz folder or a `sounds` folder. Names starting with
  `Question…` or `Podium…` show up in **Game settings → Music**.
- **Branding** (Pro and up): upload a logo and pick colors in Game settings. They show on the big screen, the
  Live page and every phone.

## Plans
| Plan | Players per quiz | Quizzes / month | Extras |
|---|---|---|---|
| Free | 10 | 2 | — |
| Basic | 50 | 3 | — |
| Pro | 1000 | 10 | Branding, PowerPoint, Excel downloads |
| Enterprise | Unlimited | Unlimited | Everything + organization users |

The master sets each account's plan in **Accounts**. A quiz counts once per event day launched in a month.
**Enterprise:** the account holder is the organization master. Under **Organization** they add, pause and reset
their institution's hosts. Each host gets their own quizzes and QR folder, but uses the organization's branding,
which only the organization master can change.

## Sign-ups and billing (master admin)
- **signup.php** is the public plans page. It's linked from the sign-in page and shown to players at the end of
  every game: a "Host your own quiz" card on phones, and a small QR code on the podium. Pro and up can hide it.
- **Free**: the account is created instantly. **Basic / Pro**: the account starts on Free and an invoice with your
  payment instructions is emailed. On **Billing → Mark paid**, the plan switches on for one year, then falls back
  to Free unless renewed. Hosts can upgrade or renew from the plan card on their Setup page.
- **Enterprise**: a meeting request form, plus your booking link (Calendly, Microsoft Bookings…) if you add one.
- **Billing → Settings**: business name, prices, how to pay, booking link, alert email, and **email (SMTP) settings**
  with a test button. MAMP can't send email by itself, so add an SMTP account (Gmail app password, Microsoft 365,
  or your web host's). Every invoice also has its own private link, so nothing gets stuck if an email fails.

## Changing the look
The page styles are pre-built into `assets/vendor/tailwind.css`. If you edit page markup and add new Tailwind
classes, rebuild it from this folder:
`npx tailwindcss@3.4.17 -c tools/tailwind.config.js -i tools/tailwind.in.css -o assets/vendor/tailwind.css --minify`

## Data
Everything is stored in `data/rizquiz.sqlite`. Back up this one file to keep accounts, questions and scores.
