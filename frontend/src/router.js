import { createRouter, createWebHistory } from 'vue-router'
import CatalogView from './views/CatalogView.vue'
import TaskEditorView from './views/TaskEditorView.vue'
import TaskDetailView from './views/TaskDetailView.vue'
import WorkspaceView from './views/WorkspaceView.vue'
import TaskOffersView from './views/TaskOffersView.vue'
import NotFoundView from './views/NotFoundView.vue'
import HomeView from './views/HomeView.vue'
import GuideView from './views/GuideView.vue'

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    { path: '/', component: HomeView, meta: { title: 'Главная' } },
    { path: '/guide', component: GuideView, meta: { title: 'Как это работает' } },
    { path: '/catalog', component: CatalogView, meta: { title: 'Каталог задач' } },
    { path: '/tasks/new', component: TaskEditorView, meta: { title: 'Создать задачу' } },
    { path: '/tasks/:id(\\d+)/edit', component: TaskEditorView, meta: { title: 'Редактор задачи' } },
    { path: '/tasks/:id(\\d+)', component: TaskDetailView, meta: { title: 'Задача' } },
    { path: '/workspace', component: WorkspaceView, meta: { title: 'Мой кабинет' } },
    { path: '/tasks/:id(\\d+)/offers', component: TaskOffersView, meta: { title: 'Отклики на задачу' } },
    { path: '/:pathMatch(.*)*', component: NotFoundView, meta: { title: 'Страница не найдена' } },
  ],
  scrollBehavior(to, from, saved) {
    if (saved) return saved
    if (to.hash) return { el: to.hash, top: 100 }
    if (to.path === from.path) return false
    return { top: 0 }
  },
})
router.afterEach((to) => { document.title = (to.meta.title || 'Образовательные задачи') + ' · AlemEdu' })
export default router
