import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch } from '@/api/client.js'
import { useNotify } from '@/composables/useNotify.js'

export const useTagsStore = defineStore( 'tags', () => {
  const items    = ref( [] )
  const loading  = ref( false )
  const _fetched = ref( false )

  const byId = computed( () => ( id ) => items.value.find( t => t.id === id ) || null )

  async function fetchAll( force = false ) {
    if ( _fetched.value && ! force ) return

    loading.value = true
    try {
      const data = await apiFetch( 'tags' )
      items.value    = Array.isArray( data ) ? data : ( data.items || [] )
      _fetched.value = true
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  async function create( data ) {
    try {
      const tag = await apiFetch( 'tags', { method: 'POST', body: data } )
      items.value.push( tag )
      return tag
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function update( id, data ) {
    try {
      const tag = await apiFetch( `tags/${id}`, { method: 'PATCH', body: data } )
      const idx = items.value.findIndex( t => t.id === id )
      if ( idx > -1 ) items.value.splice( idx, 1, tag )
      return tag
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function remove( id ) {
    try {
      await apiFetch( `tags/${id}`, { method: 'DELETE' } )
      items.value = items.value.filter( t => t.id !== id )
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  return { items, loading, byId, fetchAll, create, update, remove }
} )
