# Signal — independent work, in sync

Signal is a PHP/PDO marketplace for clients and freelancers. Its distinctive feature is **Signal Fit**: a transparent comparison between the skills listed on a freelancer's profile and the skills requested in a project brief. It shows the overlapping skills and any gaps; it is guidance, not an automated hiring decision.

## Run with XAMPP

1. Put this folder at `C:\xampp\htdocs\freelancer_project`.
2. Start Apache in the XAMPP Control Panel.
3. Open [http://localhost/freelancer_project/](http://localhost/freelancer_project/).
4. Create one client account and one freelancer account to try both sides of the marketplace.

## Run from the VS Code terminal

Open this project folder in VS Code, start a PowerShell terminal, and run:

```powershell
.\frontend\run.ps1
```

Then open [http://127.0.0.1:8080/](http://127.0.0.1:8080/). The launcher serves the full, database-connected PHP application; its shared stylesheet is in [frontend/style.css](./frontend/style.css). Keep the terminal open while using the app and press **Ctrl+C** there to stop the server. If port 8080 is busy, run `.\frontend\run.ps1 -Port 8081`. More details are in [frontend/README.md](./frontend/README.md).

PHP 7.4+ with PDO and `pdo_sqlite` is required for the default setup. Signal uses SQLite by default; its database file is created in the system temporary directory at `signal_freelancer/freelancer.db`, outside the public web root. The database tables and new columns are initialized automatically.

To use MySQL instead, install/enable `pdo_mysql`, create an empty database (default name `freelancer_db`) in phpMyAdmin, set `DB_DRIVER=mysql`, configure the environment variables below for Apache, then restart Apache. A selected but misconfigured MySQL connection reports its error; it never silently stores data in a different database.

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_DRIVER` | `sqlite` | Set to `mysql` to use MySQL |
| `DB_HOST` | `127.0.0.1` | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `freelancer_db` | Existing MySQL database name |
| `DB_USER` | `root` | MySQL account |
| `DB_PASSWORD` | empty | MySQL password |
| `DB_SQLITE_PATH` | system temp directory | Optional absolute SQLite file path |

An explicitly selected MySQL connection fails visibly rather than silently switching databases. Keep the SQLite file outside the web root and back it up if you use SQLite for persistent data.

## Marketplace workflows

- Register and sign in as a client or freelancer; passwords are hashed.
- Complete a profile with bio, skills, location, and a portfolio URL.
- Clients publish briefs with a discipline, budget, and requested skills; they can pause and reopen projects.
- Freelancers search open briefs, filter by discipline, see their explainable skill overlap, and send one proposal per project.
- Clients have private project rooms to read proposals, check the public profile and portfolio links, shortlist, pass, or make an offer.
- Making an offer marks that project filled and closes the other pending proposals for it.
- Clients and freelancers have separate workspaces at `client_dashboard.php` and `freelancer_dashboard.php`; `dashboard.php` routes to the correct one after sign-in.
- Clients can search a profile directory at `talent.php`. The administrator control room at `admin_panel.php` manages member suspensions and project moderation, with actions recorded in an audit log.
- Administrators are provisioned only by the CLI seed script, never by public registration.

## Demo members and administrator setup

Run the seed from PowerShell on the machine serving the app. It can be re-run safely and adds sample records without clearing or rewriting existing marketplace data:

```powershell
$env:SIGNAL_ADMIN_EMAIL = "admin@signal.test"
$env:SIGNAL_ADMIN_PASSWORD = "a-unique-password-at-least-16-characters"
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\freelancer_project\seed_demo_data.php"
Remove-Item Env:SIGNAL_ADMIN_EMAIL, Env:SIGNAL_ADMIN_PASSWORD
```

The seed command is CLI-only and prints a confirmation; its administrator password is not written to the project. The seeded sample members all use `SignalDemo2026!`:

| Role | Demo sign-in |
| --- | --- |
| Client | `maya.chen@signal-demo.test` |
| Client | `andre.williams@signal-demo.test` |
| Client | `priya.kapoor@signal-demo.test` |
| Freelancer | `lena.ortiz@signal-demo.test` |
| Freelancer | `noah.williams@signal-demo.test` |
| Freelancer | `amara.okafor@signal-demo.test` |
| Freelancer | `leo.martin@signal-demo.test` |
| Freelancer | `sofia.reyes@signal-demo.test` |
| Freelancer | `james.park@signal-demo.test` |
| Freelancer | `ella.brooks@signal-demo.test` |
| Freelancer | `david.kim@signal-demo.test` |

Demo accounts are for local demonstration only. Change or remove their passwords before using a publicly accessible deployment.

## Data and security

The application uses PDO prepared statements, HTML escaping, password hashing, session ID rotation, role checks, and CSRF tokens on state-changing forms. Suspended accounts are blocked on their next request. Database schema additions are migrated when the app starts. Existing legacy accounts without a password hash must register a new account; passwordless legacy sign-in is not supported.

The project supports real project posting, discovery, proposals, shortlisting, offer acceptance, moderation, and demo data, but it is not a payment or escrow service and does not include private in-app messaging. Agree on contracts and payments directly until payment processing and dispute handling are deliberately designed and implemented.
