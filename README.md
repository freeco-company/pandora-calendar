# 潘朵拉月曆 · pandora-calendar

> 月經 / 孕期 / 身體節律追蹤 · AI 個人化建議 · 集團 bodyRhythm 資料中心
>
> Phase 0 demo（M1-M2）— 純 local web demo，雙平台上架排程在 P2（M4-M5）

## Quick Start

```bash
# Backend (Laravel 13 + SQLite for dev)
cd backend
composer install
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000

# Frontend (Vue 3 + Vite + TS + Capacitor)
cd frontend
npm install
npm run dev    # http://localhost:5174

# E2E
cd e2e
npm install && npx playwright install chromium
npx playwright test
```

## Demo 帳號

| Name | Email | Cycle |
|---|---|---|
| 小敏 | `demo-min@pandora-calendar.test` | 28 天 |
| 雨晴 | `demo-yuching@pandora-calendar.test` | 30 天 |
| 阿伶 | `demo-aling@pandora-calendar.test` | 26 天 |

password 一律 `demo1234`，登入頁直接點選即可。

## 文件

- [CLAUDE.md](CLAUDE.md) — 子專案憲法（一年路線圖 / 紅線 / 集團整合 hook）
- [集團 CLAUDE.md](../CLAUDE.md) — 集團硬規則
- [產品定位 docs/products.html](../docs/products.html#p3) — 第 3 個產品

## 技術棧

| 層 | 選型 |
|---|---|
| Backend | Laravel 13 + MariaDB / SQLite (dev) + Sanctum |
| Frontend | Vue 3 + Vite + TS + Tailwind + Capacitor |
| 測試 | Pest 4 (backend) + Playwright (e2e) |
| 上架 | iOS App Store + Google Play（**不上 web**） |

## Status

- ✅ P0 Foundation — backend 8/8 tests · e2e 2/2 tests
- ⏳ P1-P6 — 排程在 meal Launch 後 2 個月起跑
