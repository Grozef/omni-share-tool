<template>
  <div
    class="media-uploader"
    :class="{ dragging, uploading }"
    @dragover.prevent="dragging = true"
    @dragleave="dragging = false"
    @drop.prevent="onDrop"
    @click="triggerInput"
  >
    <input ref="inputRef" type="file" accept="image/*,video/*" class="file-input" @change="onFileChange" />

    <template v-if="!uploading">
      <div class="upload-icon">
        <ion-icon name="cloud-upload-outline" />
      </div>
      <p class="upload-label">Drop media or <span class="upload-cta">browse</span></p>
      <p class="upload-sub">Images · Videos · Up to 2GB</p>
      <div class="upload-formats">
        <span v-for="f in formats" :key="f" class="format-badge">{{ f }}</span>
      </div>

      <!-- Camera button for Capacitor/mobile -->
      <button class="camera-btn" @click.stop="captureCamera">
        <ion-icon name="camera-outline" />
        Camera
      </button>
    </template>

    <template v-else>
      <div class="upload-progress-wrap">
        <div class="upload-progress-ring">
          <svg viewBox="0 0 44 44">
            <circle cx="22" cy="22" r="18" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="3" />
            <circle
              cx="22" cy="22" r="18" fill="none"
              stroke="#7c6fff" stroke-width="3"
              stroke-dasharray="113"
              :stroke-dashoffset="113 - (113 * uploadProgress / 100)"
              stroke-linecap="round"
              transform="rotate(-90 22 22)"
            />
          </svg>
          <span class="progress-num">{{ uploadProgress }}%</span>
        </div>
        <p class="upload-status">{{ uploadProgress < 100 ? 'Uploading...' : 'Processing...' }}</p>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { IonIcon } from '@ionic/vue'
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera'
import { usePublicationsStore, type MediaFile } from '@/stores/publications'

const emit = defineEmits<{ uploaded: [MediaFile] }>()

const publicationsStore = usePublicationsStore()
const inputRef = ref<HTMLInputElement>()
const dragging = ref(false)
const uploading = ref(false)
const uploadProgress = ref(0)

const formats = ['JPG', 'PNG', 'GIF', 'MP4', 'MOV', 'WEBM']

function triggerInput() {
  if (!uploading.value) inputRef.value?.click()
}

function onDrop(e: DragEvent) {
  dragging.value = false
  const file = e.dataTransfer?.files[0]
  if (file) handleFile(file)
}

function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) handleFile(file)
}

async function captureCamera() {
  try {
    const photo = await Camera.getPhoto({
      resultType: CameraResultType.Uri,
      source: CameraSource.Camera,
      quality: 90,
    })
    if (photo.webPath) {
      const response = await fetch(photo.webPath)
      const blob = await response.blob()
      const file = new File([blob], 'capture.jpg', { type: 'image/jpeg' })
      handleFile(file)
    }
  } catch {
    // User cancelled or not on native platform; ignore
  }
}

async function handleFile(file: File) {
  uploading.value = true
  uploadProgress.value = 0

  try {
    const media = await publicationsStore.uploadMedia(file, undefined, (p) => {
      uploadProgress.value = p
    })
    emit('uploaded', media)
  } finally {
    uploading.value = false
    uploadProgress.value = 0
    if (inputRef.value) inputRef.value.value = ''
  }
}
</script>

<style scoped>
.media-uploader {
  border: 1.5px dashed rgba(255,255,255,0.1);
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px 24px;
  cursor: pointer;
  transition: all 0.25s;
  min-height: 280px;
  position: relative;
  background: rgba(255,255,255,0.01);
}

.media-uploader:hover,
.media-uploader.dragging {
  border-color: rgba(124,111,255,0.5);
  background: rgba(124,111,255,0.04);
}

.media-uploader.uploading { cursor: default; pointer-events: none; }

.file-input {
  display: none;
}

.upload-icon {
  font-size: 40px;
  color: rgba(255,255,255,0.15);
  transition: color 0.2s;
}
.media-uploader:hover .upload-icon { color: rgba(124,111,255,0.5); }

.upload-label {
  font-size: 14px;
  color: rgba(255,255,255,0.5);
  margin: 0;
  font-family: 'DM Mono', monospace;
}
.upload-cta { color: #7c6fff; }

.upload-sub {
  font-size: 11px;
  color: rgba(255,255,255,0.2);
  margin: 0;
  letter-spacing: 0.05em;
  font-family: 'DM Mono', monospace;
}

.upload-formats {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
  justify-content: center;
}

.format-badge {
  font-size: 9px;
  padding: 2px 6px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 3px;
  color: rgba(255,255,255,0.3);
  letter-spacing: 0.05em;
  font-family: 'DM Mono', monospace;
}

.camera-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  color: rgba(255,255,255,0.4);
  font-family: 'DM Mono', monospace;
  font-size: 11px;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 4px;
}
.camera-btn:hover {
  border-color: rgba(255,255,255,0.2);
  color: rgba(255,255,255,0.7);
}

/* Progress ring */
.upload-progress-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.upload-progress-ring {
  position: relative;
  width: 80px;
  height: 80px;
}

.upload-progress-ring svg {
  width: 100%;
  height: 100%;
}

.progress-num {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-family: 'DM Mono', monospace;
  color: #7c6fff;
}

circle {
  transition: stroke-dashoffset 0.3s ease;
}

.upload-status {
  font-size: 12px;
  color: rgba(255,255,255,0.4);
  margin: 0;
  letter-spacing: 0.06em;
  font-family: 'DM Mono', monospace;
}
</style>
