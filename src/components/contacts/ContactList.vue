<script setup>
import { computed } from 'vue'
import { __ } from '@wordpress/i18n'

function safeColor( color ) {
  return /^#[0-9a-fA-F]{3,8}$/.test( color ) ? color : '#6366f1'
}
import Spinner from '@/components/ui/Spinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const props = defineProps( {
  contacts:    { type: Array,   required: true },
  loading:     { type: Boolean, default: false },
  selectedIds: { type: Array,   default: () => [] },
} )

const emit = defineEmits( [
  'select', 'deselect', 'select-all', 'deselect-all',
  'edit', 'delete', 'view',
] )

const allSelected = computed( () =>
  props.contacts.length > 0 &&
  props.contacts.every( c => props.selectedIds.includes( c.id ) )
)

function toggleAll() {
  if ( allSelected.value ) {
    emit( 'deselect-all' )
  } else {
    emit( 'select-all' )
  }
}

function toggleRow( id ) {
  if ( props.selectedIds.includes( id ) ) {
    emit( 'deselect', id )
  } else {
    emit( 'select', id )
  }
}

function statusClass( status ) {
  if ( status === 'active' ) return 'crm-badge crm-badge--success'
  return 'crm-badge'
}

function formatDate( dateStr ) {
  if ( ! dateStr ) return ''
  const d = new Date( dateStr )
  return d.toLocaleDateString()
}
</script>

<template>
  <div class="contact-list-wrap">
    <div v-if="loading" class="contact-list-overlay">
      <Spinner size="lg" />
    </div>

    <EmptyState
      v-else-if="contacts.length === 0"
      icon="dashicons-groups"
      :title="__( 'No contacts yet', 'pressio-crm' )"
      :description="__( 'Add your first contact to get started.', 'pressio-crm' )"
    />

    <table v-else class="crm-table">
      <thead>
        <tr>
          <th class="col-check">
            <input
              type="checkbox"
              :checked="allSelected"
              :indeterminate="selectedIds.length > 0 && ! allSelected"
              @change="toggleAll"
            />
          </th>
          <th>{{ __( 'Name', 'pressio-crm' ) }}</th>
          <th>{{ __( 'Email', 'pressio-crm' ) }}</th>
          <th>{{ __( 'Phone', 'pressio-crm' ) }}</th>
          <th>{{ __( 'Company', 'pressio-crm' ) }}</th>
          <th>{{ __( 'Status', 'pressio-crm' ) }}</th>
          <th>{{ __( 'Tags', 'pressio-crm' ) }}</th>
          <th>{{ __( 'Created', 'pressio-crm' ) }}</th>
          <th class="col-actions">{{ __( 'Actions', 'pressio-crm' ) }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="contact in contacts" :key="contact.id">
          <td class="col-check">
            <input
              type="checkbox"
              :checked="selectedIds.includes( contact.id )"
              @change="toggleRow( contact.id )"
            />
          </td>

          <td>
            <button type="button" class="contact-name-link" @click="emit( 'view', contact )">
              {{ contact.first_name }} {{ contact.last_name }}
            </button>
          </td>

          <td>{{ contact.email || '—' }}</td>
          <td>{{ contact.phone || '—' }}</td>
          <td>{{ contact.company || '—' }}</td>

          <td>
            <span :class="statusClass( contact.status )">{{ contact.status || 'active' }}</span>
          </td>

          <td>
            <span class="tag-cell">
              <span
                v-for="tag in ( contact.tags || [] ).slice( 0, 3 )"
                :key="tag.id"
                class="crm-tag-pill"
                :style="tag.color ? `--tag-color: ${safeColor( tag.color )}` : ''"
              >{{ tag.name }}</span>
              <span
                v-if="( contact.tags || [] ).length > 3"
                class="crm-badge"
              >+{{ ( contact.tags || [] ).length - 3 }}</span>
            </span>
          </td>

          <td class="crm-text-muted">{{ formatDate( contact.created_at ) }}</td>

          <td class="col-actions">
            <button
              type="button"
              class="crm-btn crm-btn--ghost crm-btn--sm"
              :title="__( 'Edit', 'pressio-crm' )"
              @click="emit( 'edit', contact )"
            >
              <span class="dashicons dashicons-edit" aria-hidden="true" />
            </button>
            <button
              type="button"
              class="crm-btn crm-btn--ghost crm-btn--sm action-delete"
              :title="__( 'Delete', 'pressio-crm' )"
              @click="emit( 'delete', contact )"
            >
              <span class="dashicons dashicons-trash" aria-hidden="true" />
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.contact-list-wrap {
  position: relative;
  background: var( --crm-surface );
  border: 1px solid var( --crm-border );
  border-radius: var( --crm-radius-lg );
  overflow: hidden;
}

.contact-list-overlay {
  position: absolute;
  inset: 0;
  background: rgba( 255, 255, 255, 0.75 );
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}

.col-check { width: 40px; }
.col-actions { width: 90px; white-space: nowrap; }

.contact-name-link {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  font-weight: 600;
  color: var( --crm-primary );
  font-size: 13px;
  text-align: left;
}
.contact-name-link:hover { text-decoration: underline; }

.tag-cell {
  display: flex;
  flex-wrap: wrap;
  gap: 3px;
  align-items: center;
}

.action-delete:hover { color: var( --crm-danger ); }
</style>
