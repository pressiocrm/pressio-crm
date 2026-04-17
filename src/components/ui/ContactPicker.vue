<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { __ } from '@wordpress/i18n'
import { apiFetch } from '@/api/client.js'

const props = defineProps( {
  modelValue:  { type: Number, default: null },
  placeholder: { type: String, default: () => __( 'Search contacts…', 'pressio-crm' ) },
} )

const emit = defineEmits( [ 'update:modelValue' ] )

const query        = ref( '' )
const showDrop     = ref( false )
const results      = ref( [] )
const selected     = ref( null )
const containerRef = ref( null )
let debounceTimer  = null

function contactLabel( c ) {
  const name = [ c.first_name, c.last_name ].filter( Boolean ).join( ' ' )
  return c.email ? `${name} — ${c.email}` : name
}

async function search() {
  if ( ! query.value.trim() ) {
    results.value = []
    return
  }
  try {
    const res = await apiFetch( 'contacts', { params: { search: query.value.trim(), per_page: 10, page: 1 } } )
    results.value = Array.isArray( res ) ? res : ( res?.items || [] )
  } catch {
    results.value = []
  }
}

function onInput() {
  clearTimeout( debounceTimer )
  debounceTimer = setTimeout( search, 300 )
  showDrop.value = true
  selected.value = null
  emit( 'update:modelValue', null )
}

function select( contact ) {
  selected.value = contact
  query.value    = contactLabel( contact )
  showDrop.value = false
  results.value  = []
  emit( 'update:modelValue', contact.id )
}

function clear() {
  selected.value = null
  query.value    = ''
  results.value  = []
  emit( 'update:modelValue', null )
}

function onClickOutside( e ) {
  if ( containerRef.value && ! containerRef.value.contains( e.target ) ) {
    showDrop.value = false
  }
}

onMounted( async () => {
  document.addEventListener( 'mousedown', onClickOutside )

  // Pre-populate the label if an initial value is provided.
  if ( props.modelValue ) {
    try {
      const res = await apiFetch( `contacts/${props.modelValue}` )
      if ( res?.id ) {
        selected.value = res
        query.value    = contactLabel( res )
      }
    } catch { /* contact may not exist */ }
  }
} )

onBeforeUnmount( () => {
  document.removeEventListener( 'mousedown', onClickOutside )
  clearTimeout( debounceTimer )
} )

watch( () => props.modelValue, ( id ) => {
  if ( ! id ) {
    selected.value = null
    query.value    = ''
    results.value  = []
  }
} )
</script>

<template>
  <div ref="containerRef" class="crm-contact-picker">
    <div class="crm-contact-picker__input-wrap">
      <input
        v-model="query"
        type="text"
        class="crm-input"
        :placeholder="placeholder"
        @input="onInput"
        @focus="showDrop = true"
      />
      <button
        v-if="selected"
        type="button"
        class="crm-contact-picker__clear"
        :aria-label="__( 'Clear', 'pressio-crm' )"
        @click="clear"
      >
        &times;
      </button>
    </div>

    <ul v-if="showDrop && results.length > 0" class="crm-contact-picker__dropdown">
      <li
        v-for="contact in results"
        :key="contact.id"
        class="crm-contact-picker__option"
        @mousedown.prevent="select( contact )"
      >
        {{ contactLabel( contact ) }}
      </li>
    </ul>
  </div>
</template>

<style scoped>
.crm-contact-picker { position: relative; }

.crm-contact-picker__input-wrap { position: relative; }

.crm-contact-picker__clear {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY( -50% );
  background: none;
  border: none;
  cursor: pointer;
  font-size: 16px;
  color: var( --crm-text-secondary );
  padding: 0 2px;
  line-height: 1;
}
.crm-contact-picker__clear:hover { color: var( --crm-text ); }

.crm-contact-picker__dropdown {
  position: absolute;
  top: calc( 100% + 4px );
  left: 0;
  right: 0;
  background: var( --crm-surface );
  border: 1px solid var( --crm-border );
  border-radius: var( --crm-radius );
  box-shadow: var( --crm-shadow-lg );
  list-style: none;
  margin: 0;
  padding: 4px 0;
  z-index: 1000;
  max-height: 240px;
  overflow-y: auto;
}

.crm-contact-picker__option {
  padding: 8px 12px;
  font-size: 13px;
  cursor: pointer;
  color: var( --crm-text );
}
.crm-contact-picker__option:hover { background: var( --crm-bg ); }
</style>
