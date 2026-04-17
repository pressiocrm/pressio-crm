<script setup>
import { computed } from 'vue'
import { __ } from '@wordpress/i18n'

const props = defineProps( {
  currentPage: { type: Number, required: true },
  totalPages:  { type: Number, required: true },
  total:       { type: Number, required: true },
  perPage:     { type: Number, required: true },
} )

const emit = defineEmits( [ 'change' ] )

const rangeStart = computed( () => ( props.currentPage - 1 ) * props.perPage + 1 )
const rangeEnd   = computed( () => Math.min( props.currentPage * props.perPage, props.total ) )

// Build page number sequence with ellipsis. Always shows at most 7 items.
const pageItems = computed( () => {
  const total    = props.totalPages
  const current  = props.currentPage
  const items    = []

  if ( total <= 7 ) {
    for ( let i = 1; i <= total; i++ ) items.push( i )
    return items
  }

  // Always show first and last. Fill middle with up to 5 slots.
  const delta   = 2
  const left    = current - delta
  const right   = current + delta

  let prev = null
  for ( let i = 1; i <= total; i++ ) {
    if ( i === 1 || i === total || ( i >= left && i <= right ) ) {
      if ( prev !== null && i - prev > 1 ) items.push( '...' )
      items.push( i )
      prev = i
    }
  }

  return items
} )
</script>

<template>
  <div v-if="totalPages > 0" class="crm-pagination">
    <span class="crm-pagination__info">
      {{
        __( 'Showing', 'pressio-crm' )
        + ' ' + rangeStart + '–' + rangeEnd
        + ' ' + __( 'of', 'pressio-crm' )
        + ' ' + total + ' '
        + __( 'results', 'pressio-crm' )
      }}
    </span>

    <div class="crm-pagination__pages">
      <button
        class="crm-pagination__btn"
        type="button"
        :disabled="currentPage <= 1"
        @click="emit( 'change', currentPage - 1 )"
      >
        &lsaquo;
      </button>

      <template v-for="item in pageItems" :key="item">
        <span v-if="item === '...'" class="crm-pagination__ellipsis">&hellip;</span>
        <button
          v-else
          :class="[ 'crm-pagination__btn', item === currentPage ? 'crm-pagination__btn--active' : '' ]"
          type="button"
          :disabled="item === currentPage"
          @click="emit( 'change', item )"
        >
          {{ item }}
        </button>
      </template>

      <button
        class="crm-pagination__btn"
        type="button"
        :disabled="currentPage >= totalPages"
        @click="emit( 'change', currentPage + 1 )"
      >
        &rsaquo;
      </button>
    </div>
  </div>
</template>
