import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch } from '@/api/client.js'
import { useNotify } from '@/composables/useNotify.js'

export const useTasksStore = defineStore( 'tasks', () => {
  const items   = ref( [] )
  const total   = ref( 0 )
  const pages   = ref( 1 )
  const loading = ref( false )
  const filters = ref( {
    status:     'pending',
    page:       1,
    per_page:   20,
    due_before: null,
    due_after:  null,
  } )

  const now = () => new Date().toISOString()

  const overdue = computed( () =>
    items.value.filter( t =>
      t.due_date && t.due_date < now() && t.status !== 'completed'
    )
  )

  async function fetchAll() {
    loading.value = true
    try {
      const res = await apiFetch( 'tasks', { params: filters.value } )
      items.value = res.items
      total.value = res.total
      pages.value = res.pages
    } catch ( e ) {
      useNotify().error( e.message )
    } finally {
      loading.value = false
    }
  }

  async function create( data ) {
    try {
      const task = await apiFetch( 'tasks', { method: 'POST', body: data } )
      items.value.unshift( task )
      total.value++
      return task
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function update( id, data ) {
    try {
      const task = await apiFetch( `tasks/${id}`, { method: 'PATCH', body: data } )
      const idx = items.value.findIndex( t => t.id === id )
      if ( idx > -1 ) items.value.splice( idx, 1, task )
      return task
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function remove( id ) {
    try {
      await apiFetch( `tasks/${id}`, { method: 'DELETE' } )
      items.value = items.value.filter( t => t.id !== id )
      total.value = Math.max( 0, total.value - 1 )
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  async function complete( id ) {
    try {
      const task = await apiFetch( `tasks/${id}/complete`, { method: 'PUT' } )
      const idx = items.value.findIndex( t => t.id === id )
      if ( idx > -1 ) items.value.splice( idx, 1, task )
      return task
    } catch ( e ) {
      useNotify().error( e.message )
      throw e
    }
  }

  return {
    items, total, pages, loading, filters,
    overdue,
    fetchAll, create, update, remove, complete,
  }
} )
