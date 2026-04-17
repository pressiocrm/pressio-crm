<script setup>
import { computed } from 'vue'
import { __ } from '@wordpress/i18n'

const props = defineProps( {
  deal: { type: Object, required: true },
} )

const emit = defineEmits( [ 'edit', 'delete' ] )

const currencySymbols = { USD: '$', EUR: '€', GBP: '£', AUD: 'A$', CAD: 'C$' }

function formatValue( value, currency ) {
  if ( ! value ) return null
  const sym = currencySymbols[ currency ] || currency || '$'
  return `${sym}${Number( value ).toLocaleString( undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 } )}`
}

const formattedValue = computed( () => formatValue( props.deal.value, props.deal.currency ) )

const isOverdue = computed( () => {
  if ( ! props.deal.expected_close ) return false
  return new Date( props.deal.expected_close ) < new Date()
} )

function ownerInitial( name ) {
  return name ? name.charAt( 0 ).toUpperCase() : '?'
}

function formatClose( dateStr ) {
  if ( ! dateStr ) return null
  return new Date( dateStr ).toLocaleDateString()
}
</script>

<template>
  <div class="deal-card crm-card" :data-id="deal.id">
    <div class="deal-card__header">
      <p class="deal-card__title">{{ deal.title }}</p>
      <div class="deal-card__actions">
        <button
          type="button"
          class="crm-btn crm-btn--ghost crm-btn--sm icon-btn"
          :title="__( 'Edit', 'pressio-crm' )"
          @click.stop="emit( 'edit', deal )"
        >
          <span class="dashicons dashicons-edit" aria-hidden="true" />
        </button>
        <button
          type="button"
          class="crm-btn crm-btn--ghost crm-btn--sm icon-btn icon-btn--danger"
          :title="__( 'Delete', 'pressio-crm' )"
          @click.stop="emit( 'delete', deal )"
        >
          <span class="dashicons dashicons-trash" aria-hidden="true" />
        </button>
      </div>
    </div>

    <p v-if="formattedValue" class="deal-card__value crm-text-muted">{{ formattedValue }}</p>

    <p v-if="deal.contact_name" class="deal-card__contact crm-text-muted">
      <span class="dashicons dashicons-admin-users" aria-hidden="true" />
      {{ deal.contact_name }}
    </p>

    <p
      v-if="deal.expected_close"
      class="deal-card__date"
      :class="isOverdue ? 'deal-card__date--overdue' : 'crm-text-muted'"
    >
      <span class="dashicons dashicons-calendar-alt" aria-hidden="true" />
      {{ formatClose( deal.expected_close ) }}
    </p>

    <div class="deal-card__footer">
      <span v-if="deal.owner_name" class="deal-card__owner">
        <span class="owner-avatar">{{ ownerInitial( deal.owner_name ) }}</span>
        <span class="crm-text-muted">{{ deal.owner_name }}</span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.deal-card {
  padding: 12px;
  border-radius: 4px;
  cursor: grab;
  user-select: none;
}
.deal-card:active { cursor: grabbing; }

.deal-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 4px;
}

.deal-card__title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var( --crm-text );
  flex: 1;
}

.deal-card__actions {
  display: flex;
  gap: 0;
  opacity: 0;
  transition: opacity 0.15s;
}
.deal-card:hover .deal-card__actions { opacity: 1; }

.icon-btn { padding: 2px 4px; }
.icon-btn--danger:hover { color: var( --crm-danger ); }

.deal-card__value {
  margin: 4px 0 0;
  font-size: 13px;
}

.deal-card__contact,
.deal-card__date {
  margin: 4px 0 0;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 4px;
}
.deal-card__contact .dashicons,
.deal-card__date .dashicons {
  font-size: 13px;
}

.deal-card__date--overdue { color: var( --crm-warning ); }

.deal-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8px;
}

.deal-card__owner {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.owner-avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var( --crm-primary );
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
