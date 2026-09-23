import { createRouter, createWebHistory } from 'vue-router'
import CatalogView from './views/CatalogView.vue'
import TaskEditorView from './views/TaskEditorView.vue'
import TaskDetailView from './views/TaskDetailView.vue'
import WorkspaceView from './views/WorkspaceView.vue'
import NotFoundView from './views/NotFoundView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/catalog' },
    { path: '/catalog', component: CatalogView, meta: { title: 'Каталог задач' } },
    { path: '/tasks/new', component: TaskEditorView, meta: { title: 'Создать задачу' } },
    { path: '/tasks/:id(\\d+)/edit', component: TaskEditorView, meta: { title: 'Редактор задачи' } },
    { path: '/tasks/:id(\\d+)', component: TaskDetailView, meta: { title: 'Задача' } },
    { path: '/workspace', component: WorkspaceView, meta: { title: 'Мой кабинет' } },
    { path: '/:pathMatch(.*)*', component: NotFoundView, meta: { title: 'Страница не найдена' } },
  ],
  scrollBehavior(to, from, saved) {
    if (saved) return saved
    if (to.path === from.path) return false
    return { top: 0 }
  },
})
router.afterEach((to) => { document.title = (to.meta.title || 'Образовательные задачи') + ' · AlemEdu' })
export default router
