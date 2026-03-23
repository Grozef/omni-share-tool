<template>
  <div class="platform-selector">
    <div
      v-for="p in allPlatforms"
      :key="p"
      class="platform-toggle"
      :class="{
        'platform-toggle--on': isSelected(p),
        'platform-toggle--disabled': !isEnabled(p),
      }"
      :style="{ '--pcolor': PLATFORM_META[p].color }"
      @click="toggle(p)"
      :title="!isEnabled(p) ? `Connect ${PLATFORM_META[p].label} first` : ''"
    >
      <div class="platform-toggle__icon">
        <ion-icon :name="`logo-${p}`" />
      </div>
      <span class="platform-toggle__label">{{ PLATFORM_META[p].label }}</span>
      <div class="platform-toggle__check" v-if="isSelected(p)">
        <ion-icon name="checkmark" />
      </div>
      <div class="platform-toggle__lock" v-else-if="!isEnabled(p)">
        <ion-icon name="lock-closed-outline" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { IonIcon } from '@ionic/vue'
import { PLATFORM_META, type Platform } from '@/stores/platforms'

const props = defineProps<{
  modelValue: Platform[]
  connected: Platform[]
}>()

const emit = defineEmits<{ 'update:modelValue': [Platform[]] }>()

const allPlatforms: Platform[] = ['instagram', 'tiktok', 'youtube', 'facebook', 'snapchat', 'pinterest']

function isSelected(p: Platform) { return props.modelValue.includes(p) }
function isEnabled(p: Platform) { return props.connected.includes(p) }

function toggle(p: Platform) {
  if (!isEnabled(p)) return
  const updated = isSelected(p)
    ? props.modelValue.filter(x => x !== p)
    : [...props.modelValue, p]
  emit('update:modelValue', updated)
}
</script>

<style scoped>
.platform-selector {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow-y: auto;
}

.platform-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s;
  position: relative;
  overflow: hidden;
}

.platform-toggle::before {
  content: '';
  position: absolute;
  inset: 0;
  background: color-mix(in srgb, var(--pcolor) 8%, transparent);
  opacity: 0;
  transition: opacity 0.2s;
}

.platform-toggle--on {
  border-color: color-mix(in srgb, var(--pcolor) 30%, transparent);
  background: color-mix(in srgb, var(--pcolor) 6%, transparent);
}
.platform-toggle--on::before { opacity: 1; }
.platform-toggle:not(.platform-toggle--disabled):not(.platform-toggle--on):hover { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.08); }
.platform-toggle--disabled { opacity: 0.35; cursor: not-allowed; }

.platform-toggle__icon {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  background: color-mix(in srgb, var(--pcolor) 12%, transparent);
  color: var(--pcolor);
  flex-shrink: 0;
  position: relative;
}

.platform-toggle__label {
  flex: 1;
  font-size: 12px;
  letter-spacing: 0.04em;
  color: #e8e8f0;
  position: relative;
}

.platform-toggle__check,
.platform-toggle__lock {
  font-size: 13px;
  position: relative;
}

.platform-toggle__check { color: var(--pcolor); }
.platform-toggle__lock { color: rgba(255,255,255,0.2); font-size: 11px; }
</style>
