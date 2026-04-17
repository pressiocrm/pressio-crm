import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch } from '@/api/client.js'
import { useNotify } from '@/composables/useNotify.js'

export const useDealsStore = defineStore( 'deals', () => {
  const items   = ref( [] )
  const loading = ref( false )
  const filters = ref( {
    pipeline_id: null,
  } )

  const byStage = computed( () => ( stageId ) =>
    [ ...items.value ]
      .filter( d => d.stage_id === stageId )
      .sort( ( a, b ) => a.position - b.position )
  )

  async function fetchAll() {
    loading.value = true
    try {
      const data = await apiFetch( 'deals', { params: filters.value } )
      items.value = Array.isArray( data ) ? data : ( data.items || [] )
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  async function create( data ) {
    try {
      const deal = await apiFetch( 'deals', { method: 'POST', body: data } )
      items.value.unshift( deal )
      return deal
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function update( id, data ) {
    try {
      const deal = await apiFetch( `deals/${id}`, { method: 'PATCH', body: data } )
      const idx = items.value.findIndex( d => d.id === id )
      if ( idx > -1 ) items.value.splice( idx, 1, deal )
      return deal
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function remove( id ) {
    try {
      await apiFetch( `deals/${id}`, { method: 'DELETE' } )
      items.value = items.value.filter( d => d.id !== id )
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function move( dealId, stageId, position ) {
    const idx = items.value.findIndex( d => d.id === dealId )
    // Save the original state so we can restore it without a refetch on failure.
    const original = idx > -1 ? { ...items.value[ idx ] } : null

    // Optimistic update — apply locally before the server confirms.
    if ( idx > -1 ) {
      items.value[ idx ] = { ...items.value[ idx ], stage_id: stageId, position }
    }

    try {
      const deal = await apiFetch( `deals/${dealId}/move`, {
        method: 'PUT',
        body:   { stage_id: stageId, position },
      } )
      const updatedIdx = items.value.findIndex( d => d.id === dealId )
      if ( updatedIdx > -1 ) items.value.splice( updatedIdx, 1, deal )
      return deal
    } catch ( e ) {
      // Restore the original deal in-place instead of re-fetching the full list
      // (a refetch with status:'open' filter would drop won/lost deals from the board).
      if ( original !== null ) {
        const revertIdx = items.value.findIndex( d => d.id === dealId )
        if ( revertIdx > -1 ) items.value.splice( revertIdx, 1, original )
      }
      useNotify().error( e.message )
      throw e
    }
  }

  return {
    items, loading, filters,
    byStage,
    fetchAll, create, update, remove, move,
  }
} )
