# CI/CD — the tests decide whether a push survives

## The idea

One rule: **a push is only accepted if every test of every affected service passes.**

Enforcement happens in three layers, because Git itself cannot "un-send" a push once it has left
your machine. Each layer catches what the previous one missed:

```
git push
   │
   ├── LAYER 1  .githooks/pre-push  (your machine)
   │     runs the affected services' tests BEFORE anything is sent
   │     fail -> push cancelled, nothing ever reaches GitHub
   │     pass -> the push goes out
   │
   ├── LAYER 2  ci-push.yml  (GitHub, the bot)
   │     re-runs the tests on a clean Ubuntu runner
   │     fail -> the bot rewinds the branch to the last commit that passed
   │             and opens an Issue explaining what broke
   │     pass -> CD prints "ALL TESTS PASSED SUCCESSFULLY"
   │
   └── LAYER 3  Ruleset  (GitHub, main)   <-- NOT YET CREATED, needs repo admin
         once active, `CI Gate` becomes a required status check
         and a red commit can never land on main at all
```

Layer 1 is the only true cancellation. Layer 2 is the safety net for `--no-verify`, for a teammate
without the hook installed, and for "works on my machine". Layer 3 protects the trunk.

## The files

| File | Role |
| --- | --- |
| `.githooks/pre-push` | Layer 1. Runs the affected services' tests and aborts the push on failure. |
| `.githooks/install.sh` | Points this clone at `.githooks/` — run once. |
| `.github/workflows/ci-push.yml` | Layer 2. The whole pipeline, triggered by `push`. |
| `.github/workflows/_reusable-laravel-test.yml` | Tests one service; called once per affected service. |
| `.github/actions/detect-service-changes/action.yml` | Resolves the push range and reports affected services. |
| `.github/actions/setup-php-app/action.yml` | PHP + extensions + cached `composer install`. |
| `.github/scripts/detect-services.sh` | The file-to-service matcher. **Shared** by the action and the hook. |
| `.github/services.json` | Single source of truth: names, paths, PHP versions, shared paths. |

`detect-services.sh` is deliberately shared. If CI and the hook each had their own matcher they
would eventually disagree about which services a change affects, and the hook would start letting
through pushes that CI rejects.


## Which services the pipeline covers

`services.json` is the whitelist. Only what it lists is tested — anything else in `projects/` is
invisible to both the hook and the pipeline.

| Service | Path | Tested |
| --- | --- | --- |
| cms-service | `projects/cms-service/` | yes |
| ecommerce-service | `projects/E-Commerce_Service/` | yes |
| booking-service | `projects/Booking_Service/` | yes |
| notification-service | `projects/notification-service/` | yes |
| log-service | `projects/logging-service/` | yes |
| — | `projects/Auth-Service/` | **excluded on purpose** |

`Auth-Service` is deliberately absent, so a push that only touches `projects/Auth-Service/**`
resolves to zero affected services: no tests run, the gate passes, and the bot stays out of it.
A push that touches Auth *and* another service still tests that other service. To bring Auth back
in later, add its entry to `services.json` — nothing else needs to change.

## Layer 1 — install the hook (once per clone)

```bash
bash .githooks/install.sh
```

That sets `core.hooksPath` to the tracked `.githooks/` directory. From then on:

```
$ git push
──────────────────────────────────────────────────────────────
 pre-push: testing 1 affected service(s) before pushing
──────────────────────────────────────────────────────────────
  > log-service (projects/logging-service/)
  OK log-service passed
──────────────────────────────────────────────────────────────
 ALL TESTS PASSED — proceeding with the push
──────────────────────────────────────────────────────────────
```

and on failure the push never leaves the machine:

```
 PUSH CANCELLED — tests failed in: cms-service
 To push anyway (not recommended): git push --no-verify
```

The hook only tests the services your push actually touches, so it stays fast. When it cannot do
its job it gets out of the way instead of blocking you — no `php` or `jq` in `PATH`, or a service
whose `vendor/` is missing — because Layer 2 is still there. Escape hatches: `git push --no-verify`
or `SKIP_TESTS=1 git push`.

To undo: `git config --unset core.hooksPath`.

## Layer 2 — the bot

`ci-push.yml` runs on every push to every branch:

1. **`detect-changes`** — diffs `github.event.before..github.sha` and maps changed files to
   services. A change under `global_paths` marks **all** services affected.
2. **`build-matrix`** — turns the names into a `strategy.matrix`.
3. **`test-services`** — one parallel job per service, `fail-fast: false`, so a single run shows
   you every failure rather than only the first.
4. **`ci-gate`** — decides accept vs. reject. **This is the job name to require in the ruleset.**
   It is fail-closed: `failure`, `cancelled` and `skipped` all reject.
5. **`cd-announce`** — the CD step; prints the success banner when the gate is green.
6. **`revert-failed-push`** — the bot; cancels the push when the gate is red.

### What the bot does when tests fail

1. Preserves the rejected commit at `refs/rejected-pushes/<branch>/<sha>`. The work is removed from
   the branch, never from the repository.
2. Rewinds the branch to `github.event.before` — the last commit that passed.
3. Opens an Issue naming the branch, the rejected commit, the services tested, a link to the logs,
   and the commands to get the work back.

Two guarantees are worth knowing:

- **It never touches `main`.** The trunk is defended by the ruleset instead, so a bug in this job
  cannot rewrite the history of the main branch.
- **It cannot clobber a teammate.** The rewind uses
  `--force-with-lease=refs/heads/<branch>:<failing-sha>`, so it only lands if the branch tip is
  *still* the failing commit. If somebody pushed in the meantime the rewind is refused and the
  Issue says so. The refspec intentionally carries no leading `+`, because that means `--force`
  and would override the lease.

The bot is also skipped for tag pushes, branch deletions, and brand-new branches, where there is no
previous commit to return to. In those cases the gate simply stays red.

### Getting rolled-back work back

```bash
git fetch origin refs/rejected-pushes/<branch>/<sha>
git checkout -b fix/<branch> FETCH_HEAD
```

Your local clone still has the commit too, so nothing is lost either way.

## Layer 3 — the ruleset for `main`  ⚠️ NOT YET IN PLACE

**Status: this is the one piece still missing, and it needs repository-admin rights.**

Layers 1 and 2 are live and verified. Layer 3 is not, which means today a push whose tests fail
**still lands on `main`**: `CI Gate` turns red and the CD banner is withheld, but nothing removes
the commit, because the bot deliberately never touches `main`.

A repository **admin** (the repo owner) has to create the ruleset — a collaborator with `push`
rights gets `404 Not Found` from the rulesets API.

**Settings → Rules → Rulesets → New branch ruleset**

- **Name:** `main - require CI Gate`
- **Enforcement status:** Active
- **Target branches:** Add target → Include by pattern → `main`
- **Require status checks to pass** — on, then Add checks → type **`CI Gate`** exactly
  (it is the job name in `ci-push.yml`; any other spelling silently never matches)
- **Block force pushes** — on
- **Restrict deletions** — on

With this in place a commit whose `CI Gate` is red cannot land on `main`. Note the consequence:
direct pushes to `main` are refused, because a brand-new commit has no passing check yet. That is
the intended workflow — push to a feature branch, where the pipeline and the bot both run, then
open a PR into `main` and merge it once `CI Gate` is green.

Do **not** add `main` to the bot's scope as a substitute. Letting a bot force-push the trunk while
also asking GitHub to block force pushes are contradictory requirements, and a bug in that job
would rewrite the history of `main`.

### How to confirm it works once created

Push a commit to `main` that breaks a test on purpose. GitHub must refuse the push with a message
naming the required status check. Then delete the commit locally.

## Verification record

Everything below was exercised against this repository on GitHub, not only locally.

| Behaviour | Evidence |
| --- | --- |
| Tests run on push, only for affected services | Detection resolved 5, 4, 3 and 1 service across runs |
| A shared path (`.github/**`) fans out to every service | First run tested all 5 |
| Brand-new branch (`before` = all zeros) falls back to `HEAD~1` | Fix branch, first push |
| Incremental detection on an existing branch | Fix branch, second push: exactly 3 services |
| `CI Gate` rejects a failing push | Red on the first `main` run and on the fix branch |
| `CI Gate` accepts when every suite passes | Green on `main` |
| CD prints `ALL TESTS PASSED SUCCESSFULLY` | Ran on the fix branch and on `main` |
| CD is withheld when the gate is red | Skipped in every failing run |
| Bot rewinds a failing push on a feature branch | Branch tip returned from the failing commit to its parent |
| Bot preserves the rejected commit | `refs/rejected-pushes/<branch>/<sha>` created |
| Bot opens an explanatory Issue | Issue filed with branch, commit, services and log link |
| Bot never touches `main` | Skipped on every `main` run |
| `pre-push` hook aborts a real `git push` | `git push` exited 1; the branch never appeared on the remote |
| `Auth-Service` is excluded | Never appeared in any test matrix |

## What the CD job prints

```
============================================================
   ALL TESTS PASSED SUCCESSFULLY - PUSH ACCEPTED
============================================================
   Commit  : <sha>
   Branch  : <branch>
   Author  : <actor>
   Services: <count> -> ["cms-service", ...]
============================================================
   Deployment stage is clear to proceed.
============================================================
```

The same banner goes to the run's **Summary** tab. On a push that touches no service code — a
README-only change — the gate passes and this job is skipped, because there are no test results to
announce.

## The test environment

Tests run **without MySQL or Redis containers**, on purpose. Every service's `phpunit.xml` already
pins the test environment:

```xml
<env name="DB_CONNECTION" value="sqlite" />
<env name="DB_DATABASE"   value=":memory:" />
<env name="CACHE_STORE"   value="array" />
<env name="QUEUE_CONNECTION" value="sync" />
<env name="SESSION_DRIVER"   value="array" />
<env name="MAIL_MAILER"      value="array" />
```

So the reusable workflow only copies `.env.example` to `.env`, runs `key:generate`, and calls
`php artisan test`. It deliberately does **not** export `DB_HOST`/`DB_CONNECTION`: real environment
variables take precedence over `phpunit.xml`, which would make CI silently test a different
database than developers do locally.

`php artisan test` with no `--testsuite` flag runs every suite in `phpunit.xml`. This matters for
`Booking_Service`, which has a third suite (`Integration`) that a hardcoded `Unit` + `Feature` pair
would silently skip.

## Adding a new service

Append one entry to `.github/services.json`. Nothing else — the pipeline and the hook both read it.

```json
{
  "name": "my-service",
  "paths": ["projects/My-Service/"],
  "php_version": "8.3",
  "docker_context": "My-Service",
  "healthcheck_path": "/health"
}
```

## Running the full suite by hand

**Actions → CI - Tests on Push → Run workflow → force-all: `true`** tests all six services
regardless of what changed.
