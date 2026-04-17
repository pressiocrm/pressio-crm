<script setup>
import { ref, computed, onMounted } from 'vue'
import { __ } from '@wordpress/i18n'
import { useTagsStore } from '@/stores/tags.js'

const props = defineProps( {
  modelValue:    { type: Array,  default: () => [] },
  availableTags: { type: Array,  default: null },
} )

const emit = defineEmits( [ 'update:modelValue' ] )

const tagsStore  = useTagsStore()
const search     = ref( '' )
const showDrop   = ref( false )
const inputRef   = ref( null )

const tagPool = computed( () =>
  props.availableTags !== null ? props.availableTags : tagsStore.items
)

const selectedTags = computed( () =>
  tagPool.value.filter( t => props.modelValue.includes( t.id ) )
)

const filteredTags = computed( () => {
  const q = search.value.toLowerCase().trim()
  return tagPool.value.filter( t =>
    ! props.modelValue.includes( t.id ) &&
    ( ! q || t.name.toLowerCase().includes( q ) )
  )
} )

function addTag( tag ) {
  emit( 'update:modelValue', [ ...props.modelValue, tag.id ] )
  search.value = ''
}

function removeTag( id ) {
  emit( 'update:modelValue', props.modelValue.filter( i => i !== id ) )
}

function onFocus() {
  showDrop.value = true
}

function onBlur() {
  // Short delay so a click in the dropdown registers before we hide it.
  setTimeout( () => { showDrop.value = false }, 180 )
}

function safeColor( color ) {
  return /^#[0-9a-fA-F]{3,8}$/.test( color ) ? color : '#6366f1'
}

onMounted( () => {
  if ( props.availableTags === null ) {
    tagsStore.fetchAll()
  }
} )
</script>

<template>
  <div class="crm-tag-picker">
    <div class="crm-tag-picker__selected">
      <span
        v-for="tag in selectedTags"
        :key="tag.id"
        class="crm-tag-pill"
        :style="tag.color ? `--tag-color: ${safeColor( tag.color )}` : ''"
      >
        {{ tag.name }}
        <button type="button" class="crm-tag-picker__remove" @click="removeTag( tag.id )">&times;</button>
      </span>
    </div>

    <div class="crm-tag-picker__input-wrap">
      <input
        ref="inputRef"
        v-model="search"
        type="text"
        class="crm-input"
        :placeholder="__( 'Search tags…', 'pressio-crm' )"
        @focus="onFocus"
        @blur="onBlur"
      />
      <ul v-if="showDrop && filteredTags.length > 0" class="crm-tag-picker__dropdown">
        <li
          v-for="tag in filteredTags"
          :key="tag.id"
          class="crm-tag-picker__option"
          @mousedown.prevent="addTag( tag )"
        >
          <span
            class="crm-tag-pill"
            :style="tag.color ? `--tag-color: ${safeColor( tag.color )}` : ''"
          >{{ tag.name }}</span>
        </li>
      </ul>
      <p v-else-if="showDrop && search && filteredTags.length === 0" class="crm-tag-picker__empty">
        {{ __( 'No tags found', 'pressio-crm' ) }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.crm-tag-picker { position: relative; display: flex; flex-direction: column; gap: 6px; }

.crm-tag-picker__selected { display: flex; flex-wrap: wrap; gap: 4px; }

.crm-tag-picker__remove {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  color: inherit;
  padding: 0;
  opacity: 0.7;
}
.crm-tag-picker__remove:hover { opacity: 1; }

.crm-tag-picker__input-wrap { position: relative; }

.crm-tag-picker__dropdown {
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
  max-height: 220px;
  overflow-y: auto;
}

.crm-tag-picker__option {
  padding: 6px 12px;
  cursor: pointer;
}
.crm-tag-picker__option:hover { background: var( --crm-bg ); }

.crm-tag-picker__empty {
  position: absolute;
  top: calc( 100% + 4px );
  left: 0;
  right: 0;
  background: var( --crm-surface );
  border: 1px solid var( --crm-border );
  border-radius: var( --crm-radius );
  padding: 10px 12px;
  font-size: 13px;
  color: var( --crm-text-secondary );
  margin: 0;
}
</style>
