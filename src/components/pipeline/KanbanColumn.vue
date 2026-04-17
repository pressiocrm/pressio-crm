<script setup>
import { ref, computed, watch } from 'vue'
import { __ } from '@wordpress/i18n'
import { VueDraggable } from 'vue-draggable-plus'
import DealCard from './DealCard.vue'

const props = defineProps( {
  stage: { type: Object, required: true },
  deals: { type: Array,  default: () => [] },
} )

const emit = defineEmits( [ 'add-deal', 'deal-moved', 'edit-deal', 'delete-deal' ] )

// Local copy so VueDraggable can reorder it via v-model during drag.
// We sync back from props whenever the store updates (API response).
const localDeals = ref( [ ...props.deals ] )
watch( () => props.deals, ( val ) => { localDeals.value = [ ...val ] }, { deep: true } )

const currencySymbols = { USD: '$', EUR: '€', GBP: '£', AUD: 'A$', CAD: 'C$' }

const totalValue = computed( () => {
  const sum = props.deals.reduce( ( acc, d ) => acc + Number( d.value || 0 ), 0 )
  if ( sum === 0 ) return null
  const currency = props.deals[ 0 ]?.currency || 'USD'
  const sym      = currencySymbols[ currency ] || currency
  return `${sym}${sum.toLocaleString( undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 } )}`
} )

const columnStyle = computed( () => {
  if ( props.stage.type === 'won' ) return { borderLeft: '3px solid var(--crm-success)' }
  if ( props.stage.type === 'lost' ) return { borderLeft: '3px solid var(--crm-danger)' }
  return {}
} )

// Called after VueDraggable has already updated localDeals to reflect the new order.
// We calculate the midpoint position from the neighbors in the new list and emit upward.
function onDragEnd( event ) {
  const newIdx = event.newIndex
  const deal   = localDeals.value[ newIdx ]
  if ( ! deal ) return

  const prev = localDeals.value[ newIdx - 1 ]
  const next = localDeals.value[ newIdx + 1 ]

  let position
  if ( prev && next ) {
    position = ( Number( prev.position ) + Number( next.position ) ) / 2
  } else if ( prev ) {
    position = Number( prev.position ) + 65536
  } else if ( next ) {
    position = Number( next.position ) / 2
  } else {
    position = 65536
  }

  emit( 'deal-moved', { dealId: deal.id, stageId: props.stage.id, position } )
}
</script>

<template>
  <div class="kanban-col" :style="columnStyle">
    <div class="kanban-col__header">
      <span class="kanban-col__name">{{ stage.name }}</span>
      <span class="crm-badge">{{ deals.length }}</span>
      <span v-if="totalValue" class="kanban-col__value crm-text-muted">{{ totalValue }}</span>
    </div>

    <VueDraggable
      v-model="localDeals"
      group="deals"
      ghost-class="crm-card--ghost"
      :animation="150"
      class="kanban-col__list"
      @update="onDragEnd"
      @add="onDragEnd"
    >
      <DealCard
        v-for="deal in localDeals"
        :key="deal.id"
        :deal="deal"
        @edit="emit( 'edit-deal', $event )"
        @delete="emit( 'delete-deal', $event )"
      />
    </VueDraggable>

    <button
      type="button"
      class="crm-btn crm-btn--ghost crm-btn--sm kanban-col__add"
      @click="emit( 'add-deal', stage.id )"
    >
      <span class="dashicons dashicons-plus-alt" aria-hidden="true" />
      {{ __( 'Add deal', 'pressio-crm' ) }}
    </button>
  </div>
</template>

<style scoped>
.kanban-col {
  min-width: 280px;
  max-width: 280px;
  background: var( --crm-bg );
  border-radius: var( --crm-radius-lg );
  display: flex;
  flex-direction: column;
  gap: 0;
  border: 1px solid var( --crm-border );
  overflow: hidden;
}

.kanban-col__header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  border-bottom: 1px solid var( --crm-border );
  background: var( --crm-surface );
  flex-shrink: 0;
}

.kanban-col__name {
  font-weight: 600;
  font-size: 13px;
  flex: 1;
}

.kanban-col__value {
  font-size: 12px;
}

.kanban-col__list {
  flex: 1;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-height: 80px;
  overflow-y: auto;
  max-height: calc( 100vh - 260px );
}

.kanban-col__add {
  margin: 4px 10px 10px;
  justify-content: flex-start;
  color: var( --crm-text-secondary );
  font-size: 12px;
}
.kanban-col__add:hover { color: var( --crm-primary ); }
</style>
