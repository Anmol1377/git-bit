# git-bit — GitHub, Actions, CI/CD & Monorepos

An interactive, animation-heavy session deck that replaces slides — plus a live multiplayer
quiz backed by MySQL, plus the GitHub Actions workflows the talk is actually about, running
in this very repo.

**Live:** https://anmol1377.github.io/git-bit/
**Quiz:** https://anmol1377.github.io/git-bit/quiz/
**Live results:** https://anmol1377.github.io/git-bit/quiz/host.html

The deck is static HTML + CSS + vanilla JS. The quiz adds one PHP file and a MySQL database.

---

## The deck

| | |
|---|---|
| Navigate | `→` `←` `Space`, scroll, swipe, or click the dots |
| Deep link | `index.html#7` opens slide 7 |

14 slides, and the middle ones are demos rather than bullet points:

| # | Slide | What you can click |
|---|---|---|
| 02 | What is GitHub | Stack the layers: Git → hosting → collaboration → automation |
| 03 | Why GitHub | Drag the slider between "without" and "with" |
| 04 | What are Actions | Pick an event, watch it fire through event → workflow → runner → result |
| 05 | What is a workflow | Hover any YAML line for a plain-English explanation |
| 06 | Create one, live | Click chips, the YAML types itself out — copy it and commit it |
| 07 | What is CI/CD | Press "git push", watch the pipeline run with real-looking logs |
| 08–10 | Monorepos | The waste math, a clickable path-filter simulator, and the `shared/` blast radius |
| 12 | This repo | The workflows below |

## The quiz

15 questions, easy → hard, 30s each. `10 + difficulty bonus + speed bonus`.

Every phone in the room writes to **one MySQL database** through `api/quiz.php`, so the
dashboard on the big screen is genuinely live across devices. The grading happens on the
server — the correct answers are stripped out of `?action=questions` and never reach the
browser — and `INSERT IGNORE` on `(player_id, qid)` means the first answer stands: one
attempt per person, no edits, no replay.

```
quiz/
├── index.html    # player
├── host.html     # live leaderboard, feed and per-question breakdown
├── api.js        # fetch wrapper
└── config.js     # ← put your API URL here
api/
├── quiz.php      # the whole backend, one file
├── questions.json# the 15 questions (source of truth)
└── config.sample.php
```

### Setting it up

1. **Upload** `api/quiz.php`, `api/questions.json` and your own `api/config.php` to your
   host (InfinityFree, or any PHP + MySQL host) so they sit at `/api/quiz.php`.

   ```bash
   cp api/config.sample.php api/config.php   # fill in your MySQL details, then upload it
   ```

   `api/config.php` is gitignored — credentials never reach this public repo. The tables
   (`quiz_sessions`, `quiz_players`, `quiz_answers`) are created automatically on first request.

2. **Point the front end at it** — edit `quiz/config.js`:

   ```js
   export const API = 'https://your-site.infinityfreeapp.com/api/quiz.php';
   ```

3. Push. Open the quiz, open `host.html` on the projector, done.

Set `host_key` in `api/config.php` to something only you know — the dashboard asks for it
before starting a new session, so nobody can wipe the board mid-talk.

> Note on free hosts: some of them (InfinityFree included) put an anti-bot interstitial in
> front of requests. If the quiz reports *"Server replied with HTML, not JSON"*, that is what
> happened — open `api/quiz.php?action=state` in a browser once to clear it, or move the API
> to a host without the interstitial.

### Archiving a session

Hit **Export results (JSON)** on the dashboard, drop the file in `results/`, commit.
[`quiz-results.yml`](.github/workflows/quiz-results.yml) validates the question bank, builds
[`results/leaderboard.md`](results/leaderboard.md) and commits it back — a workflow that
writes to its own repo, which is a good thing to show on stage.

```bash
node scripts/check-questions.mjs   # validates api/questions.json
node scripts/leaderboard.mjs       # rebuilds results/leaderboard.md
```

## The workflows

| File | Trigger | Point of it |
|---|---|---|
| `pages.yml` | push to `main` | Deploys this site. `paths-ignore` on `**.md`, `concurrency` so deploys can't race |
| `hello-demo.yml` | `workflow_dispatch` | Manual run button with inputs — the "watch a VM boot" demo |
| `frontend-ci.yml` | push/PR touching `monorepo/frontend/**` | Path filter demo (Node) |
| `api-ci.yml` | push/PR touching `monorepo/api/**` | Path filter demo (Go) |
| `worker-ci.yml` | push/PR touching `monorepo/worker/**` | Path filter demo (Node) |
| `monorepo-smart.yml` | push/PR | The grown-up version: one `changes` job → `if:` guards → a `ci-ok` gate that survives skipped jobs |
| `quiz-results.yml` | push touching `results/*.json` | Builds the leaderboard and commits it back |

### Demo script for the session

```bash
# 1. only api-ci goes green
echo '// tweak' >> monorepo/api/handler.go && git commit -am "api: tweak" && git push

# 2. only frontend-ci goes green
echo '// tweak' >> monorepo/frontend/src/format.js && git commit -am "fe: tweak" && git push

# 3. shared/ — all three wake up. This is slide 10.
echo '// tweak' >> monorepo/shared/version.js && git commit -am "shared: tweak" && git push
```

## The monorepo

Not a stub — these tests really run in CI:

```
monorepo/
├── frontend/   # React-flavoured, node --test
├── api/        # Go, go vet + go test
├── worker/     # Node, node --test
└── shared/     # imported by frontend AND worker — the dependency edge
```

## Running it locally

```bash
python3 -m http.server 8000     # the deck
open http://localhost:8000
```

To run the backend locally too:

```bash
php -S 127.0.0.1:8899 -t .      # then set quiz/config.js to http://127.0.0.1:8899/api/quiz.php
```

## Publishing

Settings → Pages → **Source: GitHub Actions**. Push to `main`; `pages.yml` does the rest.

---

**Anmol** · [linkedin.com/in/anmol18](https://linkedin.com/in/anmol18/) · [github.com/Anmol1377](https://github.com/Anmol1377/)

Thanks for coming — now go open `.github/workflows/` and break something.
