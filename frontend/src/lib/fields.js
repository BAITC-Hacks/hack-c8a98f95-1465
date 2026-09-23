export const contentFields = [
  { key: 'context', label: 'Контекст и потребность', placeholder: 'Какую проблему нужно решить и почему это важно?' },
  { key: 'users', label: 'Пользователи', placeholder: 'Кто будет пользоваться решением?' },
  { key: 'materials', label: 'Данные и материалы', placeholder: 'Что уже есть у команды? Если материалов нет, укажите это.' },
  { key: 'constraints', label: 'Ограничения', placeholder: 'Сроки, бюджет, технологии и доступ к данным.' },
  { key: 'expectedOutcome', label: 'Ожидаемый результат', placeholder: 'Что команда должна передать по итогам работы?' },
  { key: 'successCriteria', label: 'Критерии успеха', placeholder: 'Как проверить, что задача решена?' },
  { key: 'contact', label: 'Связь с заказчиком', placeholder: 'Как связаться с вами и обсуждать работу?' },
]

export function scopeLabel(scope) {
  return { institution: 'Внутри учреждения', kazakhstan: 'Весь Казахстан' }[scope] || scope || 'Не указан'
}
