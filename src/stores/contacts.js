import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch } from '@/api/client.js'
import { useNotify } from '@/composables/useNotify.js'

export const useContactsStore = defineStore( 'contacts', () => {
  const items   = ref( [] )
  const total   = ref( 0 )
  const pages   = ref( 1 )
  const current       = ref( null )
  const loading       = ref( false )
  const loadingSingle = ref( false )
  const filters = ref( {
    search:   '',
    status:   '',
    tag_id:   '',
    owner_id: '',
    page:     1,
    per_page: 20,
  } )

  const isEmpty = computed( () => ! loading.value && items.value.length === 0 )

  async function fetchAll( overrides = {} ) {
    loading.value = true
    try {
      const params = { ...filters.value, ...overrides }
      const res = await apiFetch( 'contacts', { params } )
      items.value = res.items
      total.value = res.total
      pages.value = res.pages
      return res
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  async function fetchOne( id ) {
    loadingSingle.value = true
    try {
      const contact = await apiFetch( `contacts/${id}` )
      current.value = contact
      return contact
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loadingSingle.value = false
    }
  }

  async function create( data ) {
    try {
      const contact = await apiFetch( 'contacts', { method: 'POST', body: data } )
      items.value.unshift( contact )
      total.value++
      return contact
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function update( id, data ) {
    try {
      const contact = await apiFetch( `contacts/${id}`, { method: 'PATCH', body: data } )
      const idx = items.value.findIndex( c => c.id === id )
      if ( idx > -1 ) items.value.splice( idx, 1, contact )
      if ( current.value && current.value.id === id ) current.value = contact
      return contact
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function remove( id ) {
    try {
      await apiFetch( `contacts/${id}`, { method: 'DELETE' } )
      items.value = items.value.filter( c => c.id !== id )
      total.value = Math.max( 0, total.value - 1 )
      if ( current.value && current.value.id === id ) current.value = null
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function bulkAction( action, ids, payload = {} ) {
    try {
      const result = await apiFetch( 'contacts/bulk', {
        method: 'POST',
        body:   { action, ids, ...payload },
      } )
      return result
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  return {
    items, total, pages, current, loading, loadingSingle, filters,
    isEmpty,
    fetchAll, fetchOne, create, update, remove, bulkAction,
  }
} )
