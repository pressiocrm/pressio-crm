<script setup>
import { ref, computed } from 'vue'
import { __ } from '@wordpress/i18n'
import { useSettingsStore } from '@/stores/settings.js'
import Spinner from '@/components/ui/Spinner.vue'

const settingsStore = useSettingsStore()

const step       = ref( 1 )
const TOTAL_STEPS = 3
const saving     = ref( false )

// Step 1 data
const companyName = ref( '' )

// Step 2 data — pre-filled stage names the user can edit
// Each entry is { id, name } so v-for has a stable key independent of index.
let stageIdSeq = 0
function makeStage( name ) { return { id: ++stageIdSeq, name } }

const stageNames = ref( [ 'Lead', 'Proposal', 'Won' ].map( makeStage ) )

function addStage() {
  stageNames.value.push( makeStage( '' ) )
}

function removeStage( idx ) {
  stageNames.value.splice( idx, 1 )
}

function prev() {
  if ( step.value > 1 ) step.value--
}

function next() {
  if ( step.value < TOTAL_STEPS ) step.value++
}

async function finish() {
  saving.value = true
  try {
    await settingsStore.save( {
      company_name:        companyName.value,
      onboarding_complete: true,
    } )
    // Full WP page navigation — hash routing alone won't leave the onboarding page.
    window.location.href = `${ window.pressioCrm.adminUrl }?page=pressio-crm`
  } catch {
    saving.value = false
  }
}

const progressPct = computed( () => ( ( step.value - 1 ) / ( TOTAL_STEPS - 1 ) ) * 100 )
</script>

<template>
  <div class="onboarding-wrap crm-flex-center">
    <div class="onboarding-card crm-card">

      <!-- Step dots -->
      <div class="step-dots" :aria-label="`${__( 'Step', 'pressio-crm' )} ${step} ${__( 'of', 'pressio-crm' )} ${TOTAL_STEPS}`">
        <span
          v-for="n in TOTAL_STEPS"
          :key="n"
          class="step-dot"
          :class="n <= step ? 'step-dot--active' : ''"
        />
      </div>

      <!-- Step 1: Welcome -->
      <div v-if="step === 1" class="onboarding-step">
        <div class="onboarding-icon" aria-hidden="true">
          <span class="dashicons dashicons-chart-area" />
        </div>
        <h1 class="onboarding-step__title">
          {{ __( 'Welcome to Pressio CRM', 'pressio-crm' ) }}
        </h1>
        <p class="onboarding-step__desc crm-text-muted">
          {{ __( 'Your pipeline-first CRM for WordPress. Let\'s get you set up in 2 quick steps.', 'pressio-crm' ) }}
        </p>

        <div class="crm-form-group onboarding-form-group">
          <label class="crm-label" for="ob-company">
            {{ __( 'Your Company Name', 'pressio-crm' ) }}
          </label>
          <input
            id="ob-company"
            v-model="companyName"
            type="text"
            class="crm-input"
            :placeholder="__( 'e.g. Acme Corp', 'pressio-crm' )"
          />
        </div>

        <div class="onboarding-actions">
          <button type="button" class="crm-btn crm-btn--primary" @click="next">
            {{ __( 'Next', 'pressio-crm' ) }}
            <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true" />
          </button>
        </div>
      </div>

      <!-- Step 2: Pipeline stages -->
      <div v-else-if="step === 2" class="onboarding-step">
        <h1 class="onboarding-step__title">
          {{ __( 'Set up your pipeline stages', 'pressio-crm' ) }}
        </h1>
        <p class="onboarding-step__desc crm-text-muted">
          {{ __( 'These are the stages deals move through. You can change them anytime in Settings.', 'pressio-crm' ) }}
        </p>

        <div class="stage-list">
          <div
            v-for="( stage, idx ) in stageNames"
            :key="stage.id"
            class="stage-row"
          >
            <span class="stage-num crm-text-muted">{{ idx + 1 }}</span>
            <input
              v-model="stageNames[ idx ].name"
              type="text"
              class="crm-input"
              :placeholder="`${__( 'Stage name', 'pressio-crm' )} ${idx + 1}`"
            />
            <button
              v-if="stageNames.length > 1"
              type="button"
              class="crm-btn crm-btn--ghost crm-btn--sm"
              :title="__( 'Remove', 'pressio-crm' )"
              @click="removeStage( idx )"
            >
              <span class="dashicons dashicons-minus" aria-hidden="true" />
            </button>
          </div>
        </div>

        <button
          type="button"
          class="crm-btn crm-btn--ghost crm-btn--sm add-stage-btn"
          @click="addStage"
        >
          <span class="dashicons dashicons-plus-alt" aria-hidden="true" />
          {{ __( 'Add stage', 'pressio-crm' ) }}
        </button>

        <div class="onboarding-actions">
          <button type="button" class="crm-btn crm-btn--ghost" @click="prev">
            {{ __( 'Back', 'pressio-crm' ) }}
          </button>
          <button type="button" class="crm-btn crm-btn--primary" @click="next">
            {{ __( 'Next', 'pressio-crm' ) }}
            <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true" />
          </button>
        </div>
      </div>

      <!-- Step 3: Ready -->
      <div v-else-if="step === 3" class="onboarding-step onboarding-step--center">
        <div class="onboarding-icon onboarding-icon--success" aria-hidden="true">
          <span class="dashicons dashicons-yes-alt" />
        </div>
        <h1 class="onboarding-step__title">
          {{ __( 'You\'re all set!', 'pressio-crm' ) }}
        </h1>
        <p class="onboarding-step__desc crm-text-muted">
          {{ __( 'Pressio CRM is ready. Start by adding your first contact or creating a deal.', 'pressio-crm' ) }}
        </p>

        <div class="onboarding-actions onboarding-actions--center">
          <button type="button" class="crm-btn crm-btn--ghost" @click="prev">
            {{ __( 'Back', 'pressio-crm' ) }}
          </button>
          <button
            type="button"
            class="crm-btn crm-btn--primary"
            :disabled="saving"
            @click="finish"
          >
            <Spinner v-if="saving" size="sm" />
            {{ __( 'Go to Dashboard', 'pressio-crm' ) }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.onboarding-wrap {
  min-height: 70vh;
  padding: 40px 20px;
  align-items: flex-start;
  padding-top: 80px;
}

.onboarding-card {
  width: 100%;
  max-width: 520px;
  padding: 40px;
}

.step-dots {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 32px;
}

.step-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var( --crm-border );
  transition: background 0.2s;
}

.step-dot--active { background: var( --crm-primary ); }

.onboarding-step {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.onboarding-step--center { align-items: center; text-align: center; }

/*
 * WP admin sets .dashicons { width:20px; height:20px } which makes the layout
 * box tiny even when font-size is large — the glyph visually overflows into
 * the next flex item.  Fix: wrap in a sized <div> container and force the inner
 * span dimensions with !important to beat WP's specificity.
 */
.onboarding-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  margin-bottom: 16px;
}

.onboarding-icon .dashicons {
  font-size: 56px !important;
  width: 56px !important;
  height: 56px !important;
  line-height: 1 !important;
  color: var( --crm-primary );
}

.onboarding-icon--success .dashicons {
  color: var( --crm-success );
}

.onboarding-step__title {
  margin: 0 0 8px;
  font-size: 22px;
  font-weight: 700;
  line-height: 1.2;
}

.onboarding-step__desc {
  margin: 0 0 24px;
  font-size: 14px;
  line-height: 1.5;
}

.onboarding-form-group { margin-bottom: 24px; }

.onboarding-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
}

.onboarding-actions--center { justify-content: center; }

.stage-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 8px;
}

.stage-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.stage-num {
  width: 20px;
  text-align: center;
  font-size: 13px;
  flex-shrink: 0;
}

.add-stage-btn {
  margin-bottom: 16px;
  font-size: 13px;
  color: var( --crm-text-secondary );
  padding: 4px 0;
}
</style>
