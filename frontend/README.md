# Signal frontend

This folder contains the visual design system and the VS Code terminal launcher:

- `style.css` — shared responsive design system used by every screen.
- `run.ps1` — launches the complete PHP app from the VS Code integrated terminal.

Signal is a server-rendered PHP app: its page templates live in the project root and render data from the backend/database. Keeping those templates together with PHP is what makes registration, profiles, dashboards, project discovery, proposals, and admin actions use the same authenticated backend. This folder is not a separate demo UI or disconnected mock-up.

## Open the frontend locally

Open the project folder in VS Code, open **Terminal → New Terminal**, then run:

```powershell
.\frontend\run.ps1
```

Visit **http://127.0.0.1:8012/**. Leave the terminal open while using the app; press **Ctrl+C** there to stop the server.

If port 8012 is already occupied:

```powershell
.\frontend\run.ps1 -Port 8013
```

The launcher finds PHP on `PATH` or uses `C:\xampp\php\php.exe`. The SQLite database stays outside the web root. To seed demo users and projects, follow the administrator setup in the project-level `README.md`.

Alternatively, run the app using XAMPP Apache at `http://localhost/freelancer_project/`.
