# API-контракт AlemEdu v1

Базовый адрес: `http://127.0.0.1:8000/api`. Только JSON, UTF-8, camelCase. Идентификаторы — положительные числа, даты — ISO 8601 UTC. Отправляйте `Content-Type: application/json` и `Accept: application/json`.

Успешный ответ — `{"data": объект}` или `{"data": [объекты]}`. Каталог дополнительно содержит `meta.total`. Пагинации в MVP нет.

## Маршруты и права

| Метод | Путь после /api | Доступ | Результат |
|---|---|---|---|
| GET | /health | всем | состояние API |
| GET | /tasks | всем | опубликованные задачи |
| GET | /tasks/{id} | всем для published; владельцу для draft | Task |
| POST | /tasks | customer | создать draft, 201 |
| PUT | /tasks/{id} | владелец | изменить переданные поля |
| POST | /tasks/{id}/publish | владелец | подтвердить и опубликовать |
| POST | /ai/questions | customer | уточняющие вопросы, без сохранения |
| POST | /tasks/{id}/offers | team, только published | создать pending-отклик, 201 |
| GET | /tasks/{id}/offers | владелец | отклики с профилями команд |
| PATCH | /offers/{id}/decision | владелец задачи | selected или rejected |
| GET | /my/tasks | customer | все собственные draft и published |
| GET | /my/offers | team | собственные отклики и решения |
| GET | /teams | всем | демокоманды |
| GET | /demo/profiles | при DEMO_MODE=true | профили для переключателя роли |
| GET | /demo/briefs | при DEMO_MODE=true | пять исходных описаний |

Заголовки действий: `X-Demo-Role: customer` и `X-Demo-Id: 1`. Заказчики 1 и 2, команды 1–5 (`X-Demo-Role: team`). Список берите из `/demo/profiles`. Преподаватель в демонстрации выбирает один из этих двух режимов. Реальной регистрации нет.

## Task: поля запроса и ответа

| Поле | Тип / лимит | Поведение |
|---|---|---|
| title | string, 160 | обязательно при создании, не может быть пустым |
| organization | string, 255 | обязательно при публикации |
| region | string, 100 | обязательно при публикации |
| category | string, 100 | обязательно при публикации |
| scope | institution / kazakhstan | по умолчанию kazakhstan |
| context | string, 10000 | контекст и потребность |
| users | string, 10000 | целевые пользователи |
| materials | string, 10000 | данные и материалы |
| constraints | string, 10000 | ограничения |
| expectedOutcome | string, 10000 | ожидаемый результат |
| successCriteria | string, 10000 | критерии успеха |
| contact | string, 10000 | контакт и формат взаимодействия |
| confirmedFields | string[], максимум 7 | подтверждённые содержательные поля |

Сервер добавляет: `id`, `ownerId`, `score`, `scoreBreakdown`, `readinessLevel`, `readinessLabel`, `status`, `confirmedAt`, `publishedAt`, `createdAt`, `updatedAt`, `offersCount`. Эти сведения нельзя подменять в запросе.

`PUT` в этом API изменяет только переданные поля: остальные сохраняются. Для очистки содержательного поля используйте `""` или `null`; ответ возвращает `""`. Нельзя подтверждать пустые поля; повторяющиеся и неизвестные имена в `confirmedFields` запрещены. Переданный список полностью заменяет предыдущий.

Пример создания ([готовый JSON](examples/task-create.json)):

```json
{
  "title": "Разговорный английский для первокурсников",
  "organization": "Демо университет «Алем»",
  "region": "Алматы",
  "scope": "kazakhstan",
  "category": "Языки",
  "context": "Первокурсникам трудно регулярно практиковать разговорный английский.",
  "confirmedFields": ["context"]
}
```

Ответ: HTTP 201, `data.id` — ID новой задачи, `status: "draft"`, `score: 20`. Без `confirmedFields` рейтинг равен 0 даже при заполненном тексте.

Полный пример ответа задачи находится в [task-response.json](examples/task-response.json). Формат расшифровки:

```json
{
  "context": {
    "label": "Контекст и потребность",
    "points": 20,
    "maxPoints": 20,
    "filled": true,
    "confirmed": true,
    "hint": "Поле заполнено и подтверждено."
  }
}
```

`scoreBreakdown` содержит семь таких записей: context, materials, expectedOutcome, successCriteria, constraints, users, contact.

Для сохранения ответов AI объедините исходную карточку с ответами по `questions[].field`, отправьте `PUT /tasks/{id}`. Устанавливайте `confirmedFields` только после действия пользователя. Повторная отправка тех же значений не снимает подтверждения.

## Публикация

```http
POST /api/tasks/6/publish
Content-Type: application/json
X-Demo-Role: customer
X-Demo-Id: 1

{"confirmed": true}
```

Ответ — Task со статусом `published`. Все заполненные содержательные поля подтверждаются; минимального рейтинга нет. `title`, `organization`, `region`, `category` должны быть заполнены. Повторная публикация разрешена, исходное `publishedAt` сохраняется. Только этот маршрут переводит черновик в published.

При последующей правке карточка остаётся опубликованной; изменённые сведения требуют повторного подтверждения, рейтинг обновляется, `confirmedAt` становится null. Обязательные для публикации название, организация, регион и категория не могут быть очищены у опубликованной задачи.

## Каталог

`GET /tasks?category=...&region=...&scope=institution&q=...&sort=score`

- `category`, `region`: точное совпадение.
- `scope`: institution или kazakhstan.
- `q`: подстрока в названии, контексте, организации или категории; без учёта регистра, включая кириллицу.
- `sort`: score (по умолчанию, баллы по убыванию) или newest (дата публикации по убыванию).
- `preferredOrganization`: необязательное название учреждения. Его задачи с охватом institution идут первыми; далее действует выбранная сортировка. Чужие задачи сохраняются в списке.
- При равенстве сортировка стабильна по ID по убыванию.
- Только published, включая карточки с 0 баллов. Пустой результат: `{"data": [], "meta": {"total": 0}}`.

## Отклики и решения

`POST /tasks/{id}/offers` с заголовками команды:

```json
{
  "idea": "Тренажёр диалогов с выбором учебных ситуаций.",
  "plan": "Уточнить сценарии, собрать прототип, провести проверку.",
  "timeline": "Четыре недели.",
  "prototypeLink": "https://example.invalid/alemedu-demo"
}
```

`idea` и `plan`: обязательные строки до 10000 символов; `timeline`: обязательная строка до 255. `prototypeLink`: необязательная ссылка http/https до 2048 символов, допускается null.

Название команды берётся из выбранного профиля. Передавать `teamId` и `decision` при создании нельзя. Ограничений количества нет, в том числе повторных откликов той же команды.

Пример ответа:

```json
{
  "data": {
    "id": 6,
    "taskId": 6,
    "teamId": 1,
    "team": {
      "id": 1,
      "name": "Steppe Coders",
      "organization": "Демо университет «Алем»",
      "interests": ["Образование", "Веб-приложения"],
      "skills": ["Разработка", "Исследование пользователей"],
      "technologies": ["Vue 3", "PHP", "SQLite"]
    },
    "idea": "Тренажёр диалогов с выбором учебных ситуаций.",
    "plan": "Уточнить сценарии, собрать прототип, провести проверку.",
    "timeline": "Четыре недели.",
    "prototypeLink": "https://example.invalid/alemedu-demo",
    "decision": "pending",
    "decidedAt": null,
    "createdAt": "2026-09-23T10:00:00.000000Z",
    "updatedAt": "2026-09-23T10:00:00.000000Z"
  }
}
```

`GET /tasks/{id}/offers` возвращает массив таких объектов. `PATCH /offers/{id}/decision` принимает `{"decision":"selected"}` или `{"decision":"rejected"}`; возвращает обновлённый Offer. Решение можно изменить. Выбор одного отклика не меняет остальные, допускается несколько выбранных команд. Никакой автоматической оценки или назначения исполнителя нет.

## AI

`POST /ai/questions` принимает `description` (3–10000 символов) и необязательный объект `fields` с семью содержательными полями. Суммарно не более 20000 символов. Возвращает `mode` (`openai` или `mock`), `topic`, `missingFields`, `questions`, `suggestedFields`, `notice`; при OpenAI также `model`. Ключ находится только в backend. Ошибки провайдера возвращают `message` и `code`, без подмены ответа демовопросами.

[Полный пример, алгоритм и обработка ошибок](AI.md). Запрос не создаёт Task и не начисляет баллы.

## Ошибки

| Код | Значение |
|---|---|
| 400 | синтаксически неверный JSON или тело не JSON-объект |
| 401 | нет действующего демопрофиля |
| 403 | неподходящая роль или чужая задача |
| 404 | ресурс не найден, либо черновик недоступен |
| 405 | неподдерживаемый HTTP-метод |
| 415 | тело запроса передано не как application/json |
| 422 | ошибки полей |
| 503 | DEMO_MODE выключен для демонстрационного действия |

```json
{
  "message": "Проверьте введённые данные.",
  "errors": {
    "organization": ["Заполните поле перед публикацией."]
  }
}
```

## Axios / Vue 3

```js
import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api',
  headers: { Accept: 'application/json' }
})

export function selectProfile(profile) {
  api.defaults.headers.common['X-Demo-Role'] = profile.role
  api.defaults.headers.common['X-Demo-Id'] = String(profile.id)
}

const profiles = (await api.get('/demo/profiles')).data.data
selectProfile(profiles.customers[0])
const task = (await api.post('/tasks', {
  title: 'Практика английского',
  context: 'Первокурсникам трудно практиковать разговорный английский.'
})).data.data

// После редактирования карточки и явного подтверждения:
// await api.put('/tasks/' + task.id, editedFields)
// await api.post('/tasks/' + task.id + '/publish', { confirmed: true })

const tasks = (await api.get('/tasks', {
  params: { scope: 'kazakhstan', sort: 'score' }
})).data.data
```

В `frontend/.env`: `VITE_API_URL=http://127.0.0.1:8000/api`. Для сервера на 18081 поменяйте порт. CORS разрешает http://localhost:5173 и http://127.0.0.1:5173; другие адреса добавляйте в backend `CORS_ALLOWED_ORIGINS`. Cookies и CSRF-токены не требуются.
