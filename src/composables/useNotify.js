import { ref, readonly } from 'vue'

const notifications = ref( [] )

function add( type, message, duration ) {
  const id = Date.now() + Math.random()
  notifications.value.push( { id, type, message, duration } )
  setTimeout( () => dismiss( id ), duration )
  return id
}

function dismiss( id ) {
  notifications.value = notifications.value.filter( n => n.id !== id )
}

export function useNotify() {
  return {
    notifications: readonly( notifications ),
    success( msg, duration = 4000 ) { return add( 'success', msg, duration ) },
    error(   msg, duration = 6000 ) { return add( 'error',   msg, duration ) },
    info(    msg, duration = 4000 ) { return add( 'info',    msg, duration ) },
    warning( msg, duration = 5000 ) { return add( 'warning', msg, duration ) },
    dismiss,
  }
}
