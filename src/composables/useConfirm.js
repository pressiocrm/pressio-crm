import { reactive } from 'vue'
import { __ } from '@wordpress/i18n'

const state = reactive( {
  show:         false,
  title:        '',
  message:      '',
  confirmLabel: '',
  cancelLabel:  '',
  danger:       false,
  resolve:      null,
} )

export function useConfirm() {
  function confirm( options = {} ) {
    state.show         = true
    state.title        = options.title        || ''
    state.message      = options.message      || ''
    state.confirmLabel = options.confirmLabel || __( 'Confirm', 'pressio-crm' )
    state.cancelLabel  = options.cancelLabel  || __( 'Cancel',  'pressio-crm' )
    state.danger       = options.danger       || false

    return new Promise( resolve => {
      state.resolve = resolve
    } )
  }

  function onConfirm() {
    state.show = false
    if ( state.resolve ) state.resolve( true )
    state.resolve = null
  }

  function onCancel() {
    state.show = false
    if ( state.resolve ) state.resolve( false )
    state.resolve = null
  }

  return { state, confirm, onConfirm, onCancel }
}
