<script setup>
import { ref, computed, onMounted } from 'vue'
import { __ } from '@wordpress/i18n'
import { useTasksStore } from '@/stores/tasks.js'
import { useNotify } from '@/composables/useNotify.js'
import { useConfirm } from '@/composables/useConfirm.js'
import TaskList from '@/components/tasks/TaskList.vue'
import TaskForm from '@/components/tasks/TaskForm.vue'
import Modal from '@/components/ui/Modal.vue'
import Pagination from '@/components/ui/Pagination.vue'

const tasksStore = useTasksStore()
const notify     = useNotify()
const { confirm } = useConfirm()

const showForm    = ref( false )
const editingTask = ref( null )
const savingTask  = ref( false )
const activeTab   = ref( 'all' )

onMounted( () => tasksStore.fetchAll() )

const TABS = [
  { key: 'all',     label: () => __( 'All', 'pressio-crm' ) },
  { key: 'today',   label: () => __( 'Today', 'pressio-crm' ) },
  { key: 'overdue', label: () => __( 'Overdue', 'pressio-crm' ) },
]

function todayBounds() {
  const start = new Date()
  start.setHours( 0, 0, 0, 0 )
  const end = new Date()
  end.setHours( 23, 59, 59, 999 )
  return {
    after:  start.toISOString().slice( 0, 19 ).replace( 'T', ' ' ),
    before: end.toISOString().slice( 0, 19 ).replace( 'T', ' ' ),
  }
}

function setTab( key ) {
  activeTab.value = key
  tasksStore.filters.status     = 'pending'
  tasksStore.filters.due_before = null
  tasksStore.filters.due_after  = null
  tasksStore.filters.page       = 1

  if ( key === 'today' ) {
    const { after, before } = todayBounds()
    tasksStore.filters.due_after  = after
    tasksStore.filters.due_before = before
  } else if ( key === 'overdue' ) {
    tasksStore.filters.due_before = todayBounds().after  // anything before today start
  }

  tasksStore.fetchAll()
}

// Server already filters by date range — visibleTasks is just the store items.
const visibleTasks = computed( () => tasksStore.items )

async function onComplete( task ) {
  try {
    await tasksStore.complete( task.id )
    notify.success( __( 'Task completed', 'pressio-crm' ) )
  } catch {}
}

function onEdit( task ) {
  editingTask.value = task
  showForm.value    = true
}

async function onDelete( task ) {
  const ok = await confirm( {
    title:        __( 'Delete task?', 'pressio-crm' ),
    message:      `${__( 'This will permanently delete', 'pressio-crm' )} "${task.title}".`,
    confirmLabel: __( 'Delete', 'pressio-crm' ),
    danger:       true,
  } )
  if ( ! ok ) return
  try {
    await tasksStore.remove( task.id )
    notify.success( __( 'Task deleted', 'pressio-crm' ) )
  } catch {}
}

async function onSubmit( data ) {
  savingTask.value = true
  try {
    if ( editingTask.value ) {
      await tasksStore.update( editingTask.value.id, data )
      notify.success( __( 'Task updated', 'pressio-crm' ) )
    } else {
      await tasksStore.create( data )
      notify.success( __( 'Task created', 'pressio-crm' ) )
    }
    showForm.value    = false
    editingTask.value = null
  } catch {} finally {
    savingTask.value = false
  }
}

function closeForm() {
  showForm.value    = false
  editingTask.value = null
}

const modalTitle = computed( () =>
  editingTask.value ? __( 'Edit Task', 'pressio-crm' ) : __( 'Add Task', 'pressio-crm' )
)

function onPageChange( page ) {
  tasksStore.filters.page = page
  tasksStore.fetchAll()
}
</script>

<template>
  <div>
    <div class="crm-page-header">
      <div class="header-left">
        <h1 class="crm-page-header__title">{{ __( 'Tasks', 'pressio-crm' ) }}</h1>
        <div class="tab-group">
          <button
            v-for="tab in TABS"
            :key="tab.key"
            type="button"
            :class="[ 'tab-btn', activeTab === tab.key ? 'tab-btn--active' : '' ]"
            @click="setTab( tab.key )"
          >
            {{ tab.label() }}
          </button>
        </div>
      </div>
      <div class="crm-page-header__actions">
        <button
          type="button"
          class="crm-btn crm-btn--primary"
          @click="showForm = true; editingTask = null"
        >
          <span class="dashicons dashicons-plus-alt" aria-hidden="true" />
          {{ __( 'Add Task', 'pressio-crm' ) }}
        </button>
      </div>
    </div>

    <TaskList
      :tasks="visibleTasks"
      :loading="tasksStore.loading"
      @complete="onComplete"
      @edit="onEdit"
      @delete="onDelete"
    />

    <Pagination
      :current-page="tasksStore.filters.page"
      :total-pages="tasksStore.pages"
      :total="tasksStore.total"
      :per-page="tasksStore.filters.per_page"
      @change="onPageChange"
    />

    <Modal
      :show="showForm"
      :title="modalTitle"
      size="md"
      @close="closeForm"
    >
      <TaskForm
        :task="editingTask"
        :loading="savingTask"
        @submit="onSubmit"
        @cancel="closeForm"
      />
    </Modal>
  </div>
</template>

<style scoped>
.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.tab-group {
  display: flex;
  gap: 2px;
  background: var( --crm-bg );
  border: 1px solid var( --crm-border );
  border-radius: var( --crm-radius );
  padding: 2px;
}

.tab-btn {
  padding: 4px 14px;
  font-size: 13px;
  font-weight: 500;
  border: none;
  border-radius: calc( var( --crm-radius ) - 1px );
  background: transparent;
  color: var( --crm-text-secondary );
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
}

.tab-btn:hover { background: var( --crm-surface ); color: var( --crm-text ); }

.tab-btn--active {
  background: var( --crm-surface );
  color: var( --crm-primary );
  font-weight: 600;
  box-shadow: 0 1px 3px rgba( 0,0,0,0.08 );
}
</style>
