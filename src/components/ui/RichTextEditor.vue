<script setup>
/**
 * RichTextEditor — Visual/Text tab editor using WordPress's bundled TinyMCE.
 *
 * wp-tinymce script dependency (class-admin-module.php) loads window.tinymce
 * on every CRM admin page. We build the Visual/Text tab switcher ourselves,
 * the same pattern WordPress uses for the Classic Editor.
 *
 * Visual tab  → TinyMCE WYSIWYG iframe
 * Text tab    → raw HTML <textarea>
 */
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { __ } from '@wordpress/i18n'

const props = defineProps( {
  modelValue:  { type: String,  default: '' },
  placeholder: { type: String,  default: '' },
  mergeTags:   { type: Array,   default: null },
  minHeight:   { type: String,  default: '200px' },
} )

const emit = defineEmits( [ 'update:modelValue' ] )

const uid        = `crm-rte-${Math.random().toString( 36 ).slice( 2 )}`
const mode       = ref( 'visual' )   // 'visual' | 'text'
const rawHtml    = ref( '' )
const hasTinyMce = ref( false )
let   editor     = null

onMounted( () => {
  rawHtml.value = props.modelValue || ''

  if ( ! window.tinymce ) {
    // TinyMCE not loaded — stay in text-only mode.
    return
  }

  hasTinyMce.value = true

  window.tinymce.init( {
    selector: `#${uid}`,
    plugins:  'link lists',
    toolbar:  'bold italic underline strikethrough | forecolor | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | removeformat',
    menubar:   false,
    statusbar: false,
    branding:  false,
    min_height: parseInt( props.minHeight, 10 ) || 200,
    content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #1d2327; margin: 8px 12px; }',
    setup( ed ) {
      editor = ed

      ed.on( 'input change keyup', () => {
        const html = ed.getContent()
        rawHtml.value = html
        emit( 'update:modelValue', html )
      } )

      ed.on( 'init', () => {
        ed.setContent( props.modelValue || '' )
      } )
    },
  } )
} )

onBeforeUnmount( () => {
  if ( editor ) {
    editor.destroy()
    editor = null
  }
} )

// Keep editor in sync when parent changes modelValue programmatically.
watch( () => props.modelValue, ( val ) => {
  rawHtml.value = val || ''
  if ( editor && editor.initialized && editor.getContent() !== val ) {
    editor.setContent( val || '' )
  }
} )

function switchMode( newMode ) {
  if ( newMode === mode.value ) return

  if ( newMode === 'text' ) {
    // Flush TinyMCE → raw textarea before hiding the iframe.
    if ( editor && editor.initialized ) {
      rawHtml.value = editor.getContent()
    }
  } else {
    // Push raw textarea content → TinyMCE on next tick (after it's visible).
    nextTick( () => {
      if ( editor && editor.initialized ) {
        editor.setContent( rawHtml.value )
        editor.focus()
      }
    } )
  }

  mode.value = newMode
}

function onRawInput( e ) {
  rawHtml.value = e.target.value
  emit( 'update:modelValue', e.target.value )
}

function insertMergeTag( tag ) {
  if ( mode.value === 'visual' && editor && editor.initialized ) {
    editor.insertContent( tag )
    editor.focus()
  } else {
    // Insert at cursor in the raw textarea.
    const el = document.getElementById( `${uid}-raw` )
    if ( el ) {
      const start = el.selectionStart ?? rawHtml.value.length
      const end   = el.selectionEnd   ?? start
      const before = rawHtml.value.slice( 0, start )
      const after  = rawHtml.value.slice( end )
      rawHtml.value = before + tag + after
      emit( 'update:modelValue', rawHtml.value )
      nextTick( () => {
        el.focus()
        el.setSelectionRange( start + tag.length, start + tag.length )
      } )
    }
  }
}
</script>

<template>
  <div class="rte-wrap">

    <!-- ── Top bar: merge tags + Visual/Text tabs ───────────────────────── -->
    <div class="rte-topbar">
      <div v-if="mergeTags && mergeTags.length" class="rte-merge-bar">
        <span class="rte-merge-label">{{ __( 'Insert:', 'pressio-crm' ) }}</span>
        <select class="rte-merge-select" @change="e => { insertMergeTag( e.target.value ); e.target.value = '' }">
          <option value="">{{ __( '— variable —', 'pressio-crm' ) }}</option>
          <option v-for="tag in mergeTags" :key="tag.value" :value="tag.value">{{ tag.label }}</option>
        </select>
      </div>

      <div v-if="hasTinyMce" class="rte-tabs">
        <button
          type="button"
          class="rte-tab"
          :class="{ 'rte-tab--active': mode === 'visual' }"
          @click="switchMode( 'visual' )"
        >{{ __( 'Visual', 'pressio-crm' ) }}</button>
        <button
          type="button"
          class="rte-tab"
          :class="{ 'rte-tab--active': mode === 'text' }"
          @click="switchMode( 'text' )"
        >{{ __( 'Text', 'pressio-crm' ) }}</button>
      </div>
    </div>

    <!-- ── Editor area ──────────────────────────────────────────────────── -->
    <div class="rte-body">

      <!--
        TinyMCE mounts onto this textarea.
        Hidden when Text tab is active (TinyMCE's own iframe stays rendered
        so we can read/write its content without re-initialising).
      -->
      <div :style="{ display: mode === 'visual' ? 'block' : 'none' }">
        <textarea
          :id="uid"
          :value="modelValue"
          :placeholder="placeholder"
          class="rte-tinymce-target"
        />
      </div>

      <!-- Raw HTML textarea shown in Text mode -->
      <textarea
        v-if="mode === 'text' || ! hasTinyMce"
        :id="`${uid}-raw`"
        v-model="rawHtml"
        :placeholder="placeholder"
        :style="{ minHeight }"
        class="crm-textarea rte-raw"
        @input="onRawInput"
      />

    </div>
  </div>
</template>

<style scoped>
.rte-wrap {
  border: 1px solid var( --crm-border-strong );
  border-radius: var( --crm-radius );
  overflow: hidden;
  background: #fff;
}

/* ── Top bar ── */
.rte-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 10px;
  background: var( --crm-bg );
  border-bottom: 1px solid var( --crm-border );
}

.rte-merge-bar {
  display: flex;
  align-items: center;
  gap: 8px;
}

.rte-merge-label {
  font-size: 12px;
  color: var( --crm-text-secondary );
  flex-shrink: 0;
}

.rte-merge-select {
  font-size: 12px;
  height: 26px;
  padding: 0 8px;
  border: 1px solid var( --crm-border );
  border-radius: 4px;
  background: var( --crm-surface );
  color: var( --crm-text-secondary );
  cursor: pointer;
  max-width: 200px;
}

/* ── Visual / Text tabs ── */
.rte-tabs {
  display: flex;
  gap: 2px;
  flex-shrink: 0;
}

.rte-tab {
  font-size: 12px;
  padding: 3px 10px;
  border: 1px solid transparent;
  border-radius: 3px;
  background: transparent;
  color: var( --crm-text-secondary );
  cursor: pointer;
  line-height: 1.4;
  transition: background 0.1s, color 0.1s;
}

.rte-tab:hover {
  background: var( --crm-surface );
  color: var( --crm-text );
}

.rte-tab--active {
  background: #fff;
  color: var( --crm-primary );
  border-color: var( --crm-border );
  font-weight: 600;
}

/* ── Editor body ── */
.rte-body {
  min-height: v-bind( minHeight );
}

/* Hide TinyMCE's own outer border since .rte-wrap provides it */
:deep( .tox-tinymce ) {
  border: none !important;
  border-radius: 0 !important;
}

:deep( .tox-toolbar__primary ) {
  background: var( --crm-bg ) !important;
  border-bottom: 1px solid var( --crm-border ) !important;
}

/* The textarea TinyMCE replaces — keep it invisible */
.rte-tinymce-target {
  display: none;
}

/* Raw HTML textarea */
.rte-raw {
  width: 100%;
  min-height: v-bind( minHeight );
  border: none;
  border-radius: 0;
  resize: vertical;
  font-family: monospace;
  font-size: 12px;
  padding: 10px 12px;
  box-sizing: border-box;
}

.rte-raw:focus {
  outline: none;
  box-shadow: none;
}
</style>
