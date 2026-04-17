<script setup>
import { __ } from '@wordpress/i18n'

defineProps( {
  stats:   { type: Object,  default: null },
  loading: { type: Boolean, default: false },
} )

const currencySymbols = { USD: '$', EUR: '€', GBP: '£', AUD: 'A$', CAD: 'C$' }

function formatValue( value ) {
  if ( value === null || value === undefined ) return '—'
  const sym = ( window.pressioCrm?.currency && currencySymbols[ window.pressioCrm.currency ] ) || '$'
  return `${sym}${Number( value ).toLocaleString( undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 } )}`
}
</script>

<template>
  <div class="stats-grid">
    <!-- Total Contacts -->
    <div class="stat-card crm-card">
      <template v-if="loading">
        <div class="skeleton skeleton--icon" />
        <div class="skeleton skeleton--value" />
        <div class="skeleton skeleton--label" />
      </template>
      <template v-else>
        <span class="stat-card__icon stat-card__icon--blue">
          <span class="dashicons dashicons-groups" aria-hidden="true" />
        </span>
        <p class="stat-card__value">{{ stats?.total_contacts ?? '—' }}</p>
        <p class="stat-card__label">{{ __( 'Total Contacts', 'pressio-crm' ) }}</p>
      </template>
    </div>

    <!-- Open Deals -->
    <div class="stat-card crm-card">
      <template v-if="loading">
        <div class="skeleton skeleton--icon" />
        <div class="skeleton skeleton--value" />
        <div class="skeleton skeleton--label" />
      </template>
      <template v-else>
        <span class="stat-card__icon stat-card__icon--amber">
          <span class="dashicons dashicons-money-alt" aria-hidden="true" />
        </span>
        <p class="stat-card__value">{{ stats?.open_deals ?? '—' }}</p>
        <p class="stat-card__label">
          {{ __( 'Open Deals', 'pressio-crm' ) }}
          <span v-if="stats?.open_deals_value" class="stat-card__sub">
            {{ formatValue( stats.open_deals_value ) }}
          </span>
        </p>
      </template>
    </div>

    <!-- Tasks Due Today -->
    <div class="stat-card crm-card">
      <template v-if="loading">
        <div class="skeleton skeleton--icon" />
        <div class="skeleton skeleton--value" />
        <div class="skeleton skeleton--label" />
      </template>
      <template v-else>
        <span class="stat-card__icon stat-card__icon--violet">
          <span class="dashicons dashicons-calendar" aria-hidden="true" />
        </span>
        <p class="stat-card__value">{{ stats?.tasks_due_today ?? '—' }}</p>
        <p class="stat-card__label">{{ __( 'Tasks Due Today', 'pressio-crm' ) }}</p>
      </template>
    </div>

    <!-- Won This Month -->
    <div class="stat-card crm-card">
      <template v-if="loading">
        <div class="skeleton skeleton--icon" />
        <div class="skeleton skeleton--value" />
        <div class="skeleton skeleton--label" />
      </template>
      <template v-else>
        <span class="stat-card__icon stat-card__icon--green">
          <span class="dashicons dashicons-yes-alt" aria-hidden="true" />
        </span>
        <p class="stat-card__value">{{ stats?.won_this_month ?? '—' }}</p>
        <p class="stat-card__label">
          {{ __( 'Won This Month', 'pressio-crm' ) }}
          <span v-if="stats?.won_this_month_value" class="stat-card__sub stat-card__sub--success">
            {{ formatValue( stats.won_this_month_value ) }}
          </span>
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat( 2, 1fr );
  gap: 16px;
  margin-bottom: 24px;
}

@media ( min-width: 900px ) {
  .stats-grid { grid-template-columns: repeat( 4, 1fr ); }
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 24px;
}

.stat-card__icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 8px;
}

/* !important beats WP admin's .dashicons { width:20px; height:20px } */
.stat-card__icon .dashicons {
  font-size: 22px !important;
  width: 22px !important;
  height: 22px !important;
  line-height: 1 !important;
}

/* Per-card accent colours */
.stat-card__icon--blue   { background: rgba( 34, 113, 177, 0.1 );  color: #2271b1; }
.stat-card__icon--amber  { background: rgba( 217, 132,  10, 0.1 );  color: #d9840a; }
.stat-card__icon--violet { background: rgba( 99,  102, 241, 0.1 );  color: #6366f1; }
.stat-card__icon--green  { background: rgba(  0, 163,  42, 0.1 );  color: var( --crm-success ); }

.stat-card__value {
  margin: 0;
  font-size: 32px;
  font-weight: 700;
  color: var( --crm-text );
  line-height: 1.1;
}

.stat-card__label {
  margin: 0;
  font-size: 13px;
  color: var( --crm-text-secondary );
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-card__sub {
  font-size: 12px;
  color: var( --crm-text-secondary );
}

.stat-card__sub--success { color: var( --crm-success ); font-weight: 600; }

/* Skeleton loading placeholders */
.skeleton {
  border-radius: var( --crm-radius );
  background: linear-gradient( 90deg, var( --crm-bg ) 25%, #e0e0e1 50%, var( --crm-bg ) 75% );
  background-size: 200% 100%;
  animation: skeleton-wave 1.4s ease infinite;
}

@keyframes skeleton-wave {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.skeleton--icon  { width: 44px; height: 44px; border-radius: 10px; }
.skeleton--value { width: 60px; height: 28px; margin-top: 8px; }
.skeleton--label { width: 100px; height: 14px; }
</style>
