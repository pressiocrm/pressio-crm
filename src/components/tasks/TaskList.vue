<script setup>
import { computed } from 'vue'
import { __ } from '@wordpress/i18n'
import Spinner from '@/components/ui/Spinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const props = defineProps( {
  tasks:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
} )

const emit = defineEmits( [ 'complete', 'edit', 'delete' ] )

const now = new Date()

function startOfDay( d ) {
  const copy = new Date( d )
  copy.setHours( 0, 0, 0, 0 )
  return copy
}

function startOfWeek() {
  const d = startOfDay( now )
  d.setDate( d.getDate() - d.getDay() )
  return d
}

function endOfWeek() {
  const d = startOfWeek()
  d.setDate( d.getDate() + 6 )
  return d
}

const todayStart = startOfDay( now )

const groups = computed( () => {
  const overdue   = []
  const today     = []
  const thisWeek  = []
  const later     = []
  const noDueDate = []

  for ( const task of props.tasks ) {
    if ( task.status === 'completed' ) continue
    if ( ! task.due_date ) {
      noDueDate.push( task )
      continue
    }
    const due = new Date( task.due_date )
    if ( due < todayStart ) {
      overdue.push( task )
    } else if ( due.toDateString() === now.toDateString() ) {
      today.push( task )
    } else if ( due >= startOfWeek() && due <= endOfWeek() ) {
      thisWeek.push( task )
    } else {
      later.push( task )
    }
  }

  const completedTasks = props.tasks.filter( t => t.status === 'completed' )

  return [
    { key: 'overdue',    label: __( 'Overdue', 'pressio-crm' ),         tasks: overdue,     danger: true },
    { key: 'today',      label: __( 'Today', 'pressio-crm' ),            tasks: today,       danger: false },
    { key: 'this-week',  label: __( 'This Week', 'pressio-crm' ),        tasks: thisWeek,    danger: false },
    { key: 'later',      label: __( 'Later', 'pressio-crm' ),            tasks: later,       danger: false },
    { key: 'no-due',     label: __( 'No Due Date', 'pressio-crm' ),      tasks: noDueDate,   danger: false },
    { key: 'completed',  label: __( 'Completed', 'pressio-crm' ),        tasks: completedTasks, danger: false },
  ].filter( g => g.tasks.length > 0 )
} )

const priorityClass = {
  high:   'crm-badge crm-badge--danger',
  medium: 'crm-badge crm-badge--warning',
  low:    'crm-badge',
}

function formatDate( dateStr ) {
  if ( ! dateStr ) return ''
  return new Date( dateStr ).toLocaleDateString()
}
</script>

<template>
  <div class="task-list-wrap">
    <div v-if="loading" class="crm-flex-center" style="padding: 48px;">
      <Spinner size="lg" />
    </div>

    <EmptyState
      v-else-if="tasks.length === 0"
      icon="dashicons-list-view"
      :title="__( 'No tasks yet', 'pressio-crm' )"
      :description="__( 'Tasks you create will appear here.', 'pressio-crm' )"
    />

    <template v-else>
      <div v-for="group in groups" :key="group.key" class="task-group">
        <div class="task-group__header" :class="group.danger ? 'task-group__header--danger' : ''">
          {{ group.label }}
          <span class="crm-badge">{{ group.tasks.length }}</span>
        </div>

        <div
          v-for="task in group.tasks"
          :key="task.id"
          class="task-row"
          :class="task.status === 'completed' ? 'task-row--completed' : ''"
        >
          <button
            type="button"
            class="task-check"
            :class="task.status === 'completed' ? 'task-check--done' : ''"
            :title="__( 'Mark complete', 'pressio-crm' )"
            :disabled="task.status === 'completed'"
            @click="emit( 'complete', task )"
          >
            <span
              :class="[
                'dashicons',
                task.status === 'completed' ? 'dashicons-yes-alt' : 'dashicons-marker',
              ]"
              aria-hidden="true"
            />
          </button>

          <span v-if="task.priority" :class="priorityClass[ task.priority ] || 'crm-badge'">
            {{ task.priority }}
          </span>

          <span class="task-row__title">{{ task.title }}</span>

          <span v-if="task.contact_name" class="task-row__meta crm-text-muted">
            {{ task.contact_name }}
          </span>

          <span v-if="task.deal_title" class="task-row__meta crm-text-muted">
            {{ task.deal_title }}
          </span>

          <span v-if="task.due_date" class="task-row__date crm-text-muted">
            {{ formatDate( task.due_date ) }}
          </span>

          <div class="task-row__actions">
            <button
              type="button"
              class="crm-btn crm-btn--ghost crm-btn--sm"
              :title="__( 'Edit', 'pressio-crm' )"
              @click="emit( 'edit', task )"
            >
              <span class="dashicons dashicons-edit" aria-hidden="true" />
            </button>
            <button
              type="button"
              class="crm-btn crm-btn--ghost crm-btn--sm action-delete"
              :title="__( 'Delete', 'pressio-crm' )"
              @click="emit( 'delete', task )"
            >
              <span class="dashicons dashicons-trash" aria-hidden="true" />
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.task-list-wrap {
  background: var( --crm-surface );
  border: 1px solid var( --crm-border );
  border-radius: var( --crm-radius-lg );
  overflow: hidden;
}

.task-group__header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var( --crm-text-secondary );
  background: var( --crm-bg );
  border-bottom: 1px solid var( --crm-border );
}

.task-group__header--danger {
  color: var( --crm-danger );
  background: #fdf0f0;
  border-color: #f5b8b8;
}

.task-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-bottom: 1px solid var( --crm-border );
}
.task-row:last-child { border-bottom: none; }
.task-row:hover { background: rgba( 34, 113, 177, 0.02 ); }

.task-row--completed .task-row__title {
  text-decoration: line-through;
  color: var( --crm-text-secondary );
}

.task-check {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  color: var( --crm-border );
  font-size: 18px;
  display: flex;
  align-items: center;
  flex-shrink: 0;
  transition: color 0.15s;
}
.task-check:hover { color: var( --crm-success ); }
.task-check--done { color: var( --crm-success ); cursor: default; }
/* !important beats WP admin's .dashicons { width:20px; height:20px } */
.task-check .dashicons {
  font-size: 18px !important;
  width: 18px !important;
  height: 18px !important;
  line-height: 1 !important;
}

.task-row__title {
  flex: 1;
  font-size: 13px;
  font-weight: 500;
}

.task-row__meta,
.task-row__date {
  font-size: 12px;
  white-space: nowrap;
}

.task-row__actions {
  display: flex;
  gap: 0;
  opacity: 0;
  transition: opacity 0.15s;
}
.task-row:hover .task-row__actions { opacity: 1; }

.action-delete:hover { color: var( --crm-danger ); }
</style>
