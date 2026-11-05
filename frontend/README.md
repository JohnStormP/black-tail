# BlackTail Frontend

React console for the BlackTail iGaming HTTP API.

## Stack

- Vite + React + TypeScript
- React Router
- Lucide icons
- Recharts

## Prerequisites

Backend stack up and seeded:

```bash
make up
make install
make demo
```

API base: `http://127.0.0.1:8080` (Vite proxies `/api`, `/health`, `/ready`).

## Run

```bash
cd frontend
npm install
npm run dev
```

App: http://localhost:5173

Demo logins (from `app:demo:seed`):

- `admin@blacktail.demo` / `secret-password` (tenant admin)
- `super@blacktail.demo` / `secret-password` (open/start Crash)

## Scripts

```bash
npm test
npm run build
npm run lint
```

## Notes

- Session cookie auth via Vite proxy (`credentials: 'include'`).
- Live games: Lucky Reels and Crash. Dice / roulette / affiliates are not in the product surface (no backend).
- Workspace settings (accent, labels) are local browser preferences only.
