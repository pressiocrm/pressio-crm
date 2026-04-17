<script setup>
import { computed } from 'vue'
import { __ } from '@wordpress/i18n'
import Spinner from '@/components/ui/Spinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const props = defineProps( {
  funnel:  { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
} )

const maxCount = computed( () =>
  props.funnel.reduce( ( m, s ) => Math.max( m, s.deal_count || 0 ), 1 )
)

function barWidth( count ) {
  return Math.round( ( ( count || 0 ) / maxCount.value ) * 100 )
}

const currencySymbols = { USD: '$', EUR: '€', GBP: '£', AUD: 'A$', CAD: 'C$' }

function formatValue( value ) {
  if ( ! value ) return null
  const sym = ( window.pressioCrm?.currency && currencySymbols[ window.pressioCrm.currency ] ) || '$'
  return `${sym}${Number( value ).toLocaleString( undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 } )}`
}

function isTerminal( stage ) {
  return stage.type === 'won' || stage.type === 'lost'
}
</script>

<template>
  <div class="funnel-wrap crm-card">
    <h3 class="funnel-title">{{ __( 'Pipeline Funnel', 'pressio-crm' ) }}</h3>

    <div v-if="loading" class="crm-flex-center" style="padding: 32px;">
      <Spinner />
    </div>

    <EmptyState
      v-else-if="funnel.length === 0"
      icon="dashicons-chart-bar"
      :title="__( 'No pipeline data', 'pressio-crm' )"
    />

    <div v-else class="funnel-rows">
      <div v-for="stage in funnel" :key="stage.id" class="funnel-row">
        <span class="funnel-row__name">{{ stage.name }}</span>

        <div class="funnel-row__track">
          <div
            v-if="! isTerminal( stage )"
            class="funnel-row__bar"
            :style="{
              width: barWidth( stage.deal_count ) + '%',
              background: stage.color || 'var(--crm-primary)',
            }"
          />
          <div
            v-else
            class="funnel-row__bar funnel-row__bar--terminal"
            :style="{
              width: barWidth( stage.deal_count ) + '%',
              borderColor: stage.color || 'var(--crm-text-secondary)',
            }"
          />
        </div>

        <span class="funnel-row__stats crm-text-muted">
          {{ stage.deal_count || 0 }}
          <span v-if="formatValue( stage.total_value )"> · {{ formatValue( stage.total_value ) }}</span>
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.funnel-wrap { padding: 20px; }

.funnel-title {
  margin: 0 0 16px;
  font-size: 15px;
  font-weight: 600;
}

.funnel-rows { display: flex; flex-direction: column; gap: 10px; }

.funnel-row {
  display: grid;
  grid-template-columns: 120px 1fr 100px;
  align-items: center;
  gap: 12px;
}

.funnel-row__name {
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.funnel-row__track {
  height: 18px;
  background: var( --crm-bg );
  border-radius: 99px;
  overflow: hidden;
}

.funnel-row__bar {
  height: 100%;
  border-radius: 99px;
  min-width: 4px;
  transition: width 0.4s ease;
}

.funnel-row__bar--terminal {
  background: transparent !important;
  border: 2px dashed;
}

.funnel-row__stats {
  font-size: 12px;
  text-align: right;
  white-space: nowrap;
}
</style>
