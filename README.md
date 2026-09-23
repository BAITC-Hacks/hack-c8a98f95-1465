# AlemEdu

Платформа образовательных задач: организации описывают проблему, студенческие команды предлагают решения, заказчик вручную выбирает исполнителей.

## Этап 1 — backend

Реализован API на Laravel 12 / PHP 8.2+ / SQLite. Код находится в `backend/`. Папка `frontend/` подготовлена для следующего участника (Vue 3, Vite, Axios).

```sh
cd backend
composer install
composer setup
composer dev
```

API: http://127.0.0.1:8000/api/health

Windows из корня репозитория: `powershell -NoProfile -ExecutionPolicy Bypass -File .\backend\start.ps1 -Port 18081`.

- [Полная инструкция запуска и проверки](backend/README.md)
- [API-контракт для frontend](backend/docs/API.md)
- [Запросы для REST Client](backend/docs/alemedu.http)
- [Локальная AI-заглушка](backend/docs/AI.md)

## Работа команды

1. `feature/backend-api` — данные, правила, API и демоданные.
2. `feature/vue-frontend` — интерфейс, каталог, редактор и отклики.
3. `feature/integration-demo` — интеграция и демонстрация.

Каждый следующий этап начинается с обновлённого `main`. Backend возвращает JSON с camelCase-полями; успешные ответы имеют оболочку `data`. Регистрацию заменяют демопрофили; рейтинг и охват не блокируют опубликованные задачи.
