<script setup>
import { __ } from '@wordpress/i18n'
import { useNotify } from '@/composables/useNotify.js'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const { notifications, dismiss } = useNotify()

const pressioCrm = window.pressioCrm || {}
const { adminUrl = '', page: currentPage = '' } = pressioCrm

// Top-level navigation uses real WP admin page URLs so that:
//  • the WP admin sidebar highlights the correct submenu item
//  • each section is a proper WP admin page (not just a hash fragment)
//  • browser history / bookmarks work correctly per section
const navLinks = [
  { page: 'pressio-crm',          label: __( 'Dashboard', 'pressio-crm' ) },
  { page: 'pressio-crm-contacts', label: __( 'Contacts',  'pressio-crm' ) },
  { page: 'pressio-crm-pipeline', label: __( 'Pipeline',  'pressio-crm' ) },
  { page: 'pressio-crm-tasks',    label: __( 'Tasks',     'pressio-crm' ) },
  { page: 'pressio-crm-settings', label: __( 'Settings',  'pressio-crm' ) },
]

function pageUrl( slug ) {
  return `${ adminUrl }?page=${ slug }`
}
</script>

<template>
  <div id="pressio-crm-app">

    <!-- Top navigation bar — sits above content, inside WP admin's content column -->
    <nav class="crm-topnav" role="navigation" :aria-label="__( 'Pressio CRM navigation', 'pressio-crm' )">
      <div class="crm-topnav__brand">
        <span class="crm-topnav__logo dashicons dashicons-chart-area" aria-hidden="true" />
        <span class="crm-topnav__name">{{ __( 'Pressio CRM', 'pressio-crm' ) }}</span>
      </div>

      <ul class="crm-topnav__links" role="list">
        <li v-for="link in navLinks" :key="link.page">
          <a
            :href="pageUrl( link.page )"
            :class="[ 'crm-topnav__link', { 'crm-topnav__link--active': currentPage === link.page } ]"
            :aria-current="currentPage === link.page ? 'page' : undefined"
          >
            {{ link.label }}
          </a>
        </li>
      </ul>
    </nav>

    <!-- Main content — router renders the active page here -->
    <main class="crm-main">
      <router-view />
    </main>

    <!-- Toast notification area — always fixed top-right -->
    <div class="crm-toast-area" role="status" aria-live="polite">
      <div
        v-for="n in notifications"
        :key="n.id"
        :class="[ 'crm-toast', `crm-toast--${n.type}` ]"
      >
        <span class="crm-toast__message">{{ n.message }}</span>
        <button
          type="button"
          class="crm-toast__close"
          :aria-label="__( 'Dismiss', 'pressio-crm' )"
          @click="dismiss( n.id )"
        >
          &times;
        </button>
      </div>
    </div>

    <!-- Confirm dialog singleton — driven by useConfirm state, always mounted -->
    <ConfirmDialog />

  </div>
</template>

<style>
/* ── App shell layout ─────────────────────────────────────── */

#pressio-crm-app {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: var( --crm-bg );
}

/* ── Top nav ─────────────────────────────────────────────── */

.crm-topnav {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 0 24px;
  background: var( --crm-surface );
  box-shadow: 0 1px 0 var( --crm-border );
  height: 56px;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  z-index: 100;
}

.crm-topnav__brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 15px;
  color: var( --crm-primary );
  flex-shrink: 0;
  margin-right: 8px;
  text-decoration: none;
}

.crm-topnav__logo {
  font-size: 20px;
  color: var( --crm-primary );
  line-height: 1;
}

.crm-topnav__name {
  color: var( --crm-text );
}

.crm-topnav__links {
  display: flex;
  align-items: center;
  gap: 2px;
  list-style: none;
  margin: 0;
  padding: 0;
  flex: 1;
}

/*
 * Reset WP admin's generic a:hover / a:focus rules (box-shadow, outline,
 * text-decoration) that bleed into our topnav and create unwanted "button"
 * borders on hover.  The high-specificity selector beats wp-admin.css.
 */
#pressio-crm-app .crm-topnav__link,
#pressio-crm-app .crm-topnav__link:visited,
#pressio-crm-app .crm-topnav__link:hover,
#pressio-crm-app .crm-topnav__link:focus,
#pressio-crm-app .crm-topnav__link:active {
  box-shadow: none;
  border: none;
  text-decoration: none;
  outline: none;
}

.crm-topnav__link {
  display: flex;
  align-items: center;
  align-self: center;
  padding: 7px 12px;
  font-size: 13px;
  font-weight: 500;
  color: var( --crm-text-secondary );
  text-decoration: none;
  border-radius: var( --crm-radius );
  transition: color 0.15s, background 0.15s;
}

.crm-topnav__link:hover {
  color: var( --crm-text );
  background: var( --crm-bg );
}

/* Modern pill highlight — no underline */
.crm-topnav__link--active {
  color: var( --crm-primary );
  background: rgba( 34, 113, 177, 0.09 );
  font-weight: 600;
}

.crm-topnav__link:focus-visible {
  outline: 2px solid var( --crm-primary ) !important;
  outline-offset: 2px;
}

/* ── Main content area ───────────────────────────────────── */

.crm-main {
  flex: 1;
  padding: 24px;
  max-width: 1400px;
  width: 100%;
}
</style>
