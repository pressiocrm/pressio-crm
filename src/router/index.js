import { createRouter, createWebHashHistory } from 'vue-router'

/**
 * Map WP admin page slugs → Vue route paths.
 * Each top-level WP submenu page owns one route; the router only handles
 * sub-navigation within that page (e.g. /contacts/:id stays hash-based).
 */
const pageRouteMap = {
  'pressio-crm':          '/dashboard',
  'pressio-crm-contacts': '/contacts',
  'pressio-crm-pipeline': '/pipeline',
  'pressio-crm-tasks':    '/tasks',
  'pressio-crm-settings': '/settings',
}

const currentPage = window.pressioCrm?.page ?? 'pressio-crm'
const initialRoute = pageRouteMap[ currentPage ] ?? '/dashboard'

const routes = [
  // Root redirects to whichever WP page we're currently on.
  { path: '/', redirect: initialRoute },

  // Sub-routes that live inside a top-level WP page (hash-based navigation only).
  { path: '/contacts/:id', component: () => import( '@/pages/ContactSingle.vue' ) },

  // Top-level page components — loaded when the matching WP page is active.
  { path: '/dashboard',  component: () => import( '@/pages/Dashboard.vue' ) },
  { path: '/contacts',   component: () => import( '@/pages/Contacts.vue' ) },
  { path: '/pipeline',   component: () => import( '@/pages/Pipeline.vue' ) },
  { path: '/tasks',      component: () => import( '@/pages/Tasks.vue' ) },
  { path: '/settings',   component: () => import( '@/pages/Settings.vue' ) },
  { path: '/onboarding', component: () => import( '@/pages/Onboarding.vue' ) },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export default createRouter( {
  history: createWebHashHistory(),
  routes,
} )
