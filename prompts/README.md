# Prompt Log

Every prompt given to the AI assistant while building this project, in order,
exactly as typed (typos included). Each prompt has a screenshot from the
assistant's chat panel in this folder, saved under the filename shown.

**Tool:** Claude Code, used as the VS Code extension.

**Standing instructions:** [`CLAUDE.md`](../CLAUDE.md), written before any code.
It sets the engineering rules the assistant had to follow, such as locking,
money handling and testing.

A database password in prompt 5 is redacted here and blurred in its screenshot.

---

## Session log

| # | Prompt | What it led to | Screenshot |
|---|---|---|---|
| 1 | "analysis carfully claude and do the efficiently.." | The assistant inspected the empty Laravel skeleton and, since no feature was named, asked which scope to build first. | `01-initial-request.png` |
| 2 | *(Selected option)* "Core order flow (Recommended)" | Schema with `CHECK` constraints, `Money`, `CreateOrderAction` with row locking, API endpoints and feature tests. SQLite config replaced with PostgreSQL, as `CLAUDE.md` requires. | `02-scope-choice.png` |
| 3 | "start again" | Work resumed from the files already written rather than being discarded. | `03-start-again.png` |
| 4 | "i installed pgadmin 17/.." | Diagnosed that pgAdmin had been installed without the PostgreSQL **server**, and enabled PHP's `pdo_pgsql` driver. | `04-pgadmin-installed.png` |
| 5 | "i created pgadmin and set the password is `[redacted]` and 1.if i give the source to other what they will do for run the application give as .md file and 2. in this application what are the scrren how will work and for my interview preparation what mallow technonlogy will ask based this mini application give as .md file." | A setup guide and an explanation of how the application works. Also diagnosed that the password given was the pgAdmin master password, not the database password. | `05-setup-and-explanation.png` |
| 6 | "password changed and datsbase craerted.." | Migrations run, full test suite run against PostgreSQL, end-to-end overselling script run. | `06-database-ready.png` |
| 7 | *(Screenshot of error)* "getting conection issue..." | Found two PHP installations. The PostgreSQL driver was enabled in the one serving the app, and the server restarted. | `07-connection-issue.png` |
| 8 | *(Screenshot of welcome page)* "getting this like, why." | Explained that the root URL showed Laravel's default page and the application was an API. | `08-welcome-page.png` |
| 9 | "still like this only getting." | Reproduced a real bug: API validation errors redirected browsers to `/`. API errors are now always JSON, with a regression test. | `09-redirect-bug.png` |
| 10 | *(Assignment brief PDF attached)* "analysis both minitask pdf and claude.md do the corre t.." | Gap analysis against the brief. Added the low-stock endpoint, seeders, the wireframe billing screen, quote and payment handling, a rename of `sku` to `code`, and this README. | `10-brief-analysis.png` |
| 11 | "Please analyze the provided assignment brief. Is the current scope sufficient for the mini-task, or should additional features be added? Also, is an authentication/login system required based on the brief?" | Scope judged sufficient; authentication judged out of scope and documented as an assumption. Recommended one strengthening: a genuine concurrency test. | `11-scope-and-auth.png` |
| 12 | "any additional change if you do means do.. and also provide setup fiel as .md;" | `ConcurrentOrdersTest`, which races two PHP processes for the last unit, and a detailed `SETUP.md`. | `12-concurrency-test-and-setup.png` |
| 13 | "in this, project structure i want and what fils i need to remove before commiting gitup..." | Checked what git would commit, removed the unused Vite front-end scaffold, and confirmed no secrets would be committed. | `13-pre-commit-cleanup.png` |
| 14 | "add /prompts folder add prompt scrrendshots," | This prompt log. | `14-prompt-log.png` |

---

## How the assistant's output was checked

AI output was not accepted on trust. Every change was checked against the full
PHPUnit suite on PostgreSQL. The billing screen was rendered in a headless
browser, and each API endpoint was called against the running server.

That checking caught several problems:

- **The wrong database.** The skeleton was configured for SQLite, which
  `CLAUDE.md` forbids.
- **A redirect bug.** API validation errors redirected browsers instead of
  returning JSON.
- **A test leak.** One test's committed data leaked into later tests.
- **A misleading pass.** On Windows the HTTP concurrency script passed without
  actually running requests concurrently, which led to the two-process test.
