<script setup>
import { ref, watch, onBeforeUnmount, nextTick } from 'vue'
import { __ } from '@wordpress/i18n'

const props = defineProps( {
  show:  { type: Boolean, required: true },
  title: { type: String,  default: '' },
  size:  { type: String,  default: 'md' },
} )

const emit = defineEmits( [ 'close' ] )

const modalRef       = ref( null )
const titleId        = `crm-modal-title-${Math.random().toString( 36 ).slice( 2 )}`
let   previousFocus  = null

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join( ', ' )

function getFocusable() {
  return modalRef.value ? Array.from( modalRef.value.querySelectorAll( FOCUSABLE ) ) : []
}

function trapFocus( e ) {
  const focusable = getFocusable()
  if ( focusable.length === 0 ) return
  const first = focusable[ 0 ]
  const last  = focusable[ focusable.length - 1 ]
  if ( e.key === 'Tab' ) {
    if ( e.shiftKey ) {
      if ( document.activeElement === first ) { e.preventDefault(); last.focus() }
    } else {
      if ( document.activeElement === last )  { e.preventDefault(); first.focus() }
    }
  }
  if ( e.key === 'Escape' ) emit( 'close' )
}

watch( () => props.show, async ( val ) => {
  if ( val ) {
    previousFocus = document.activeElement
    document.addEventListener( 'keydown', trapFocus )
    await nextTick()
    const focusable = getFocusable()
    if ( focusable.length > 0 ) focusable[ 0 ].focus()
  } else {
    document.removeEventListener( 'keydown', trapFocus )
    if ( previousFocus ) previousFocus.focus()
  }
} )

onBeforeUnmount( () => {
  document.removeEventListener( 'keydown', trapFocus )
} )
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="crm-modal-overlay" @mousedown.self="emit( 'close' )">
      <div
        ref="modalRef"
        :class="[ 'crm-modal', `crm-modal--${size}` ]"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="title ? titleId : undefined"
      >

        <div class="crm-modal__header">
          <h2 :id="titleId" class="crm-modal__title">{{ title }}</h2>
          <button class="crm-modal__close" type="button" :aria-label="__( 'Close', 'pressio-crm' )" @click="emit( 'close' )">
            &times;
          </button>
        </div>

        <div class="crm-modal__body">
          <slot />
        </div>

        <div v-if="$slots.footer" class="crm-modal__footer">
          <slot name="footer" />
        </div>

      </div>
    </div>
  </Teleport>
</template>
