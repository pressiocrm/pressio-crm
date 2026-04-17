<script setup>
import { __ } from '@wordpress/i18n'
import { useRouter } from 'vue-router'
import Spinner from '@/components/ui/Spinner.vue'

defineProps( {
  activities: { type: Array,   default: () => [] },
  loading:    { type: Boolean, default: false },
} )

const router = useRouter()

const TYPE_ICONS = {
  contact_created:           'dashicons-plus-alt',
  deal_created:              'dashicons-money-alt',
  stage_change:              'dashicons-arrow-right-alt',
  deal_won:                  'dashicons-yes-alt',
  deal_lost:                 'dashicons-no-alt',
  task_completed:            'dashicons-yes',
  note_added:                'dashicons-format-aside',
  email_sent:                'dashicons-email',
  contact_created_from_form: 'dashicons-feedback',
}

function iconFor( type ) {
  return TYPE_ICONS[ type ] || 'dashicons-marker'
}

function timeAgo( dateStr ) {
  if ( ! dateStr ) return ''
  const diff  = Date.now() - new Date( dateStr ).getTime()
  const mins  = Math.floor( diff / 60000 )
  const hours = Math.floor( diff / 3600000 )
  const days  = Math.floor( diff / 86400000 )

  if ( mins < 1 )   return __( 'just now', 'pressio-crm' )
  if ( mins < 60 )  return `${mins}m`
  if ( hours < 24 ) return `${hours}h`
  return `${days}d`
}
</script>

<template>
  <div class="recent-wrap crm-card">
    <h3 class="recent-title">{{ __( 'Recent Activity', 'pressio-crm' ) }}</h3>

    <div v-if="loading" class="crm-flex-center" style="padding: 24px;">
      <Spinner />
    </div>

    <div v-else-if="activities.length === 0" class="recent-empty crm-text-muted">
      {{ __( 'No recent activity', 'pressio-crm' ) }}
    </div>

    <ol v-else class="recent-list">
      <li
        v-for="activity in activities.slice( 0, 10 )"
        :key="activity.id"
        class="recent-item"
      >
        <span class="recent-item__icon">
          <span :class="[ 'dashicons', iconFor( activity.type ) ]" aria-hidden="true" />
        </span>
        <div class="recent-item__body">
          <p class="recent-item__title">{{ activity.title || activity.type }}</p>
          <p v-if="activity.contact_name" class="recent-item__contact crm-text-muted">
            <router-link
              v-if="activity.contact_id"
              :to="`/contacts/${activity.contact_id}`"
              class="recent-item__link"
            >
              {{ activity.contact_name }}
            </router-link>
            <span v-else>{{ activity.contact_name }}</span>
          </p>
        </div>
        <span class="recent-item__time crm-text-muted">{{ timeAgo( activity.created_at ) }}</span>
      </li>
    </ol>

    <button
      type="button"
      class="crm-btn crm-btn--ghost crm-btn--sm recent-view-all"
      @click="router.push( '/contacts' )"
    >
      {{ __( 'View all contacts', 'pressio-crm' ) }}
      <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true" />
    </button>
  </div>
</template>

<style scoped>
.recent-wrap { padding: 20px; height: 100%; }

.recent-title {
  margin: 0 0 16px;
  font-size: 15px;
  font-weight: 600;
}

.recent-empty {
  padding: 24px 0;
  text-align: center;
  font-size: 13px;
}

.recent-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.recent-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid var( --crm-border );
}
.recent-item:last-child { border-bottom: none; }

.recent-item__icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var( --crm-bg );
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var( --crm-primary );
}
.recent-item__icon .dashicons {
  font-size: 14px !important;
  width: 14px !important;
  height: 14px !important;
  line-height: 1 !important;
}

.recent-item__body { flex: 1; min-width: 0; }

.recent-item__title {
  margin: 0;
  font-size: 12px;
  font-weight: 500;
  color: var( --crm-text );
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.recent-item__contact {
  margin: 1px 0 0;
  font-size: 11px;
}

.recent-item__link {
  color: var( --crm-primary );
  text-decoration: none;
}
.recent-item__link:hover { text-decoration: underline; }

.recent-item__time {
  font-size: 11px;
  white-space: nowrap;
  flex-shrink: 0;
}

.recent-view-all {
  margin-top: 12px;
  font-size: 12px;
  color: var( --crm-text-secondary );
  padding: 0;
}
.recent-view-all .dashicons {
  font-size: 14px !important;
  width: 14px !important;
  height: 14px !important;
  line-height: 1 !important;
}
</style>
