import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from '@/router/index.js'
import App from '@/App.vue'
import './style.css'

const app = createApp( App )

app.use( createPinia() )
app.use( router )

app.mount( '#pressio-crm-root' )

// Strip the redundant top-level hash from the URL.
// WP already communicates the current section via ?page=pressio-crm-*.
// Hash routing is only meaningful for sub-routes like #/contacts/42.
// We only strip hashes that are exact top-level routes (no trailing segments).
const TOP_LEVEL_HASHES = new Set( [ '#/', '#/dashboard', '#/contacts', '#/pipeline', '#/tasks', '#/settings', '#/onboarding' ] )

router.isReady().then( () => {
  if ( TOP_LEVEL_HASHES.has( window.location.hash ) ) {
    history.replaceState( null, '', window.location.pathname + window.location.search )
  }
} )
