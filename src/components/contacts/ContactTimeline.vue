<script setup>
import { __ } from '@wordpress/i18n'
import Spinner from '@/components/ui/Spinner.vue'

defineProps( {
  contactId:  { type: Number,  required: true },
  activities: { type: Array,   default: () => [] },
  loading:    { type: Boolean, default: false },
} )

const emit = defineEmits( [ 'add-note' ] )

const TYPE_ICONS = {
  contact_created:         'dashicons-plus-alt',
  deal_created:            'dashicons-money-alt',
  stage_change:            'dashicons-arrow-right-alt',
  deal_won:                'dashicons-yes-alt',
  deal_lost:               'dashicons-no-alt',
  task_completed:          'dashicons-yes',
  note_added:              'dashicons-format-aside',
  email_sent:              'dashicons-email',
  contact_created_from_form: 'dashicons-feedback',
}

function iconFor( type ) {
  return TYPE_ICONS[ type ] || 'dashicons-marker'
}

function timeAgo( dateStr ) {
  if ( ! dateStr ) return ''
  const diff = Date.now() - new Date( dateStr ).getTime()
  const mins  = Math.floor( diff / 60000 )
  const hours = Math.floor( diff / 3600000 )
  const days  = Math.floor( diff / 86400000 )

  if ( mins < 1 )   return __( 'just now', 'pressio-crm' )
  if ( mins < 60 )  return `${mins} ${__( 'min ago', 'pressio-crm' )}`
  if ( hours < 24 ) return `${hours} ${__( 'hours ago', 'pressio-crm' )}`
  if ( days < 30 )  return `${days} ${__( 'days ago', 'pressio-crm' )}`
  return new Date( dateStr ).toLocaleDateString()
}
</script>

<template>
  <div class="timeline-wrap">
    <div class="timeline-header">
      <h3 class="timeline-title">{{ __( 'Activity', 'pressio-crm' ) }}</h3>
      <button type="button" class="crm-btn crm-btn--secondary crm-btn--sm" @click="emit( 'add-note' )">
        <span class="dashicons dashicons-plus-alt" aria-hidden="true" />
        {{ __( 'Add Note', 'pressio-crm' ) }}
      </button>
    </div>

    <div v-if="loading" class="crm-flex-center" style="padding: 32px;">
      <Spinner />
    </div>

    <div v-else-if="activities.length === 0" class="timeline-empty">
      <span class="dashicons dashicons-marker" aria-hidden="true" />
      <p>{{ __( 'No activity yet', 'pressio-crm' ) }}</p>
    </div>

    <ol v-else class="timeline-list">
      <li v-for="activity in activities" :key="activity.id" class="timeline-item">
        <div class="timeline-icon">
          <span :class="[ 'dashicons', iconFor( activity.type ) ]" aria-hidden="true" />
        </div>
        <div class="timeline-content">
          <p class="timeline-item__title">{{ activity.title || activity.type }}</p>
          <p v-if="activity.description" class="timeline-item__desc">{{ activity.description }}</p>
        </div>
        <span class="timeline-item__time crm-text-muted">{{ timeAgo( activity.created_at ) }}</span>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.timeline-wrap {
  background: var( --crm-surface );
  border: 1px solid var( --crm-border );
  border-radius: var( --crm-radius-lg );
  padding: 20px;
}

.timeline-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.timeline-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
}

.timeline-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 32px;
  color: var( --crm-text-secondary );
  font-size: 13px;
}
.timeline-empty .dashicons { font-size: 32px; }

.timeline-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.timeline-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var( --crm-border );
}
.timeline-item:last-child { border-bottom: none; }

.timeline-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var( --crm-bg );
  border: 1px solid var( --crm-border );
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var( --crm-primary );
}

.timeline-content { flex: 1; min-width: 0; }

.timeline-item__title {
  margin: 0;
  font-size: 13px;
  font-weight: 500;
  color: var( --crm-text );
}

.timeline-item__desc {
  margin: 2px 0 0;
  font-size: 12px;
  color: var( --crm-text-secondary );
}

.timeline-item__time {
  font-size: 11px;
  white-space: nowrap;
  flex-shrink: 0;
}
</style>
