<template>
  <ion-page class="compose-page">
    <div class="compose-layout">
      <!-- Left Panel: Editor -->
      <aside class="panel panel--editor">
        <header class="panel__header">
          <span class="panel__label">COMPOSE</span>
          <span class="char-counter" :class="{ 'char-counter--warn': charCount > charLimit * 0.9 }">
            {{ charCount }}<em>/{{ charLimit }}</em>
          </span>
        </header>

        <div class="caption-area">
          <textarea
            ref="captionRef"
            v-model="draft.caption"
            class="caption-input"
            placeholder="Write something that stops the scroll..."
            @input="onCaptionInput"
          />
          <div class="hashtag-suggestions" v-if="hashtagSuggestions.length">
            <button
              v-for="tag in hashtagSuggestions"
              :key="tag"
              class="hashtag-chip"
              @click="addHashtag(tag)"
            >#{{ tag }}</button>
          </div>
        </div>

        <div class="hashtag-list" v-if="draft.hashtags.length">
          <span
            v-for="(tag, i) in draft.hashtags"
            :key="i"
            class="hashtag-token"
          >#{{ tag }} <button @click="removeHashtag(i)">×</button></span>
        </div>

        <!-- Schedule -->
        <div class="schedule-row">
          <button class="schedule-toggle" :class="{ active: showSchedule }" @click="showSchedule = !showSchedule">
            <ion-icon name="time-outline" />
            {{ showSchedule ? 'Publish Now' : 'Schedule' }}
          </button>
          <Transition name="slide">
            <input
              v-if="showSchedule"
              v-model="draft.scheduled_at"
              type="datetime-local"
              class="schedule-input"
              :min="minScheduleDate"
            />
          </Transition>
        </div>

        <!-- Publish Button -->
        <button
          class="publish-btn"
          :class="{ 'publish-btn--loading': publishing, 'publish-btn--ready': canPublish }"
          :disabled="!canPublish || publishing"
          @click="publish"
        >
          <span class="publish-btn__inner">
            <ion-icon v-if="!publishing" name="send" />
            <span class="spinner" v-else />
            {{ publishing ? 'Broadcasting...' : 'Publish' }}
          </span>
          <span class="publish-btn__platforms">
            <span
              v-for="p in draft.platforms"
              :key="p"
              class="platform-dot"
              :style="{ background: platformColor(p) }"
            />
          </span>
        </button>
      </aside>

      <!-- Center Panel: Media -->
      <main class="panel panel--media">
        <header class="panel__header">
          <span class="panel__label">MEDIA</span>
          <span class="panel__sub" v-if="media">{{ media.type.toUpperCase() }}</span>
        </header>

        <MediaUploader v-if="!media" @uploaded="onMediaUploaded" />

        <div v-else class="media-preview-area">
          <!-- Image/Video Preview -->
          <div class="media-preview" :class="`media-preview--${activePreviewPlatform}`">
            <div class="platform-frame">
              <img
                v-if="media.type === 'image'"
                :src="media.original_url"
                class="preview-img"
                :style="previewStyle"
              />
              <video
                v-else
                :src="media.original_url"
                class="preview-video"
                autoplay muted loop
                :style="previewStyle"
              />
              <div class="frame-overlay">
                <span class="frame-label">{{ activePreviewPlatform }}</span>
              </div>
            </div>
          </div>

          <!-- Platform preview switcher -->
          <div class="preview-switcher">
            <button
              v-for="p in draft.platforms"
              :key="p"
              class="preview-switch-btn"
              :class="{ active: activePreviewPlatform === p }"
              :style="{ '--color': platformColor(p) }"
              @click="activePreviewPlatform = p"
            >
              <ion-icon :name="`logo-${p}`" />
            </button>
          </div>

          <!-- Crop info badges -->
          <div class="crop-badges">
            <div
              v-for="p in draft.platforms"
              :key="p"
              class="crop-badge"
              :style="{ borderColor: platformColor(p) }"
            >
              <span class="crop-badge__platform" :style="{ color: platformColor(p) }">{{ p }}</span>
              <span class="crop-badge__ratio">{{ platformAspectRatio(p) }}</span>
            </div>
          </div>

          <!-- Convert to Short Video -->
          <div class="video-convert-section" v-if="media.type === 'image'">
            <div class="convert-header">
              <ion-icon name="film-outline" />
              <span>Transform to Short Video</span>
            </div>
            <div class="convert-options">
              <div class="duration-selector">
                <button
                  v-for="d in [15, 30, 60]"
                  :key="d"
                  :class="{ active: convertOptions.duration === d }"
                  @click="convertOptions.duration = d"
                >{{ d }}s</button>
              </div>
              <label class="effect-label">
                <span>Ken Burns</span>
                <div class="toggle" :class="{ on: convertOptions.kenBurns }" @click="convertOptions.kenBurns = !convertOptions.kenBurns">
                  <span class="toggle__knob" />
                </div>
              </label>
            </div>
            <button
              class="convert-btn"
              :class="{ loading: converting }"
              @click="convertToVideo"
              :disabled="converting"
            >
              <span class="spinner-sm" v-if="converting" />
              {{ converting ? 'Generating...' : 'Generate Short Video' }}
            </button>
          </div>

          <button class="remove-media-btn" @click="removeMedia">
            <ion-icon name="trash-outline" /> Remove media
          </button>
        </div>
      </main>

      <!-- Right Panel: Platforms + Status -->
      <aside class="panel panel--platforms">
        <header class="panel__header">
          <span class="panel__label">PLATFORMS</span>
          <span class="panel__sub">{{ draft.platforms.length }} selected</span>
        </header>

        <PlatformSelector
          v-model="draft.platforms"
          :connected="connectedPlatforms"
        />

        <!-- Real-time publish status -->
        <Transition name="fade">
          <PublishStatus
            v-if="publicationId"
            :publication-id="publicationId"
            :platforms="draft.platforms"
          />
        </Transition>
      </aside>
    </div>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { IonPage, IonIcon } from '@ionic/vue'
import MediaUploader from '@/components/MediaUploader.vue'
import PlatformSelector from '@/components/PlatformSelector.vue'
import PublishStatus from '@/components/PublishStatus.vue'
import { usePlatformsStore, PLATFORM_META, type Platform } from '@/stores/platforms'
import { usePublicationsStore, type MediaFile } from '@/stores/publications'

const platformsStore = usePlatformsStore()
const publicationsStore = usePublicationsStore()

const captionRef = ref<HTMLTextAreaElement>()
const publishing = ref(false)
const converting = ref(false)
const showSchedule = ref(false)
const publicationId = ref<number | null>(null)
const activePreviewPlatform = ref<Platform>('instagram')

const draft = reactive({
  caption: '',
  hashtags: [] as string[],
  platforms: [] as Platform[],
  media_id: null as number | null,
  scheduled_at: null as string | null,
})

const convertOptions = reactive({ duration: 15, kenBurns: true })
const media = ref<MediaFile | null>(null)

const connectedPlatforms = computed(() => platformsStore.connectedPlatforms)

const hashtagSuggestions = computed(() => {
  const text = draft.caption
  const words = text.split(/\s+/).filter(w => w.startsWith('#')).map(w => w.slice(1))
  // Mock suggestions — in prod this would call an API
  const suggestions = ['reels', 'viral', 'trending', 'fyp', 'contentcreator']
  return suggestions.filter(s => !draft.hashtags.includes(s)).slice(0, 5)
})

const charLimits: Record<Platform, number> = {
  instagram: 2200, tiktok: 2200, facebook: 63206,
  youtube: 5000, snapchat: 250, pinterest: 500,
}

const charLimit = computed(() => {
  if (!draft.platforms.length) return 2200
  return Math.min(...draft.platforms.map(p => charLimits[p]))
})

const charCount = computed(() => draft.caption.length)

const canPublish = computed(() =>
  draft.platforms.length > 0 &&
  (draft.caption.trim().length > 0 || !!media.value) &&
  charCount.value <= charLimit.value
)

const minScheduleDate = computed(() => {
  const d = new Date()
  d.setMinutes(d.getMinutes() + 5)
  return d.toISOString().slice(0, 16)
})

const platformAspects: Record<Platform, string> = {
  instagram: '9:16', tiktok: '9:16', youtube: '9:16',
  snapchat: '9:16', facebook: '16:9', pinterest: '2:3',
}

const previewStyle = computed(() => {
  const ratio = platformAspects[activePreviewPlatform.value] || '9:16'
  const [w, h] = ratio.split(':').map(Number)
  return { aspectRatio: `${w}/${h}`, objectFit: 'cover' }
})

function platformColor(p: Platform) { return PLATFORM_META[p]?.color ?? '#666' }
function platformAspectRatio(p: Platform) { return platformAspects[p] ?? '1:1' }

function onCaptionInput() {
  if (captionRef.value) {
    captionRef.value.style.height = 'auto'
    captionRef.value.style.height = captionRef.value.scrollHeight + 'px'
  }
}

function addHashtag(tag: string) {
  if (!draft.hashtags.includes(tag)) draft.hashtags.push(tag)
}

function removeHashtag(i: number) { draft.hashtags.splice(i, 1) }

function onMediaUploaded(uploadedMedia: MediaFile) {
  media.value = uploadedMedia
  draft.media_id = uploadedMedia.id
  if (draft.platforms.length) activePreviewPlatform.value = draft.platforms[0]
}

function removeMedia() {
  media.value = null
  draft.media_id = null
}

async function convertToVideo() {
  if (!media.value) return
  converting.value = true
  try {
    await publicationsStore.convertMediaToVideo(media.value.id, {
      duration: convertOptions.duration,
      platform: draft.platforms[0] || 'instagram',
    })
    // Poll for updated media
    setTimeout(async () => {
      if (media.value) {
        const updated = await publicationsStore.getMediaPreview(media.value.id)
        media.value = updated
      }
      converting.value = false
    }, 3000)
  } catch {
    converting.value = false
  }
}

async function publish() {
  if (!canPublish.value) return
  publishing.value = true
  try {
    const pub = await publicationsStore.create({
      caption: draft.caption,
      hashtags: draft.hashtags,
      platforms: draft.platforms,
      media_id: draft.media_id,
      scheduled_at: draft.scheduled_at,
    })
    publicationId.value = pub.id
  } finally {
    publishing.value = false
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;0,500;1,300&family=Cormorant+Garamond:wght@300;400;600&display=swap');

.compose-page {
  --bg: #080810;
  --surface: #0f0f1a;
  --surface-2: #161624;
  --border: rgba(255,255,255,0.07);
  --text: #e8e8f0;
  --text-muted: #5a5a72;
  --accent: #7c6fff;
  background: var(--bg);
  font-family: 'DM Mono', monospace;
  color: var(--text);
  height: 100vh;
  overflow: hidden;
}

.compose-layout {
  display: grid;
  grid-template-columns: 320px 1fr 280px;
  height: 100%;
  gap: 0;
}

.panel {
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.panel--media { border-right: 1px solid var(--border); }
.panel--platforms { border-right: none; }

.panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.panel__label {
  font-size: 10px;
  letter-spacing: 0.2em;
  color: var(--text-muted);
  font-weight: 500;
}

.panel__sub {
  font-size: 11px;
  color: var(--accent);
  letter-spacing: 0.1em;
}

/* Editor Panel */
.panel--editor {
  background: var(--surface);
  padding-bottom: 24px;
}

.caption-area {
  flex: 1;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-y: auto;
}

.caption-input {
  background: transparent;
  border: none;
  outline: none;
  color: var(--text);
  font-family: 'Cormorant Garamond', serif;
  font-size: 17px;
  line-height: 1.65;
  resize: none;
  width: 100%;
  min-height: 160px;
  caret-color: var(--accent);
}

.caption-input::placeholder { color: var(--text-muted); font-style: italic; }

.char-counter {
  font-size: 11px;
  color: var(--text-muted);
}
.char-counter em { font-style: normal; opacity: 0.4; }
.char-counter--warn { color: #ff6b6b; }

.hashtag-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.hashtag-chip {
  padding: 3px 10px;
  background: rgba(124, 111, 255, 0.1);
  border: 1px solid rgba(124, 111, 255, 0.25);
  border-radius: 100px;
  color: var(--accent);
  font-size: 11px;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
}
.hashtag-chip:hover { background: rgba(124, 111, 255, 0.2); }

.hashtag-list {
  padding: 0 24px 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.hashtag-token {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px 3px 10px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 4px;
  font-size: 11px;
  color: var(--text-muted);
}

.hashtag-token button {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  padding: 0;
  opacity: 0.5;
  transition: opacity 0.15s;
}
.hashtag-token button:hover { opacity: 1; }

.schedule-row {
  padding: 12px 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-top: 1px solid var(--border);
  flex-shrink: 0;
}

.schedule-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: 1px solid var(--border);
  color: var(--text-muted);
  font-family: inherit;
  font-size: 11px;
  letter-spacing: 0.05em;
  padding: 7px 14px;
  border-radius: 6px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}
.schedule-toggle.active, .schedule-toggle:hover {
  border-color: var(--accent);
  color: var(--accent);
}

.schedule-input {
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text);
  font-family: inherit;
  font-size: 11px;
  padding: 7px 10px;
  border-radius: 6px;
  flex: 1;
  min-width: 0;
}

.publish-btn {
  margin: 12px 24px 0;
  padding: 0;
  background: linear-gradient(135deg, #7c6fff, #5e4fff);
  border: none;
  border-radius: 10px;
  cursor: pointer;
  overflow: hidden;
  transition: all 0.25s;
  flex-shrink: 0;
}
.publish-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.publish-btn--ready { box-shadow: 0 0 24px rgba(124, 111, 255, 0.35); }
.publish-btn--loading { background: linear-gradient(135deg, #5a5a72, #444455); }

.publish-btn__inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 20px;
  color: white;
  font-family: 'DM Mono', monospace;
  font-size: 13px;
  letter-spacing: 0.08em;
}

.publish-btn__platforms {
  display: flex;
  justify-content: center;
  gap: 4px;
  padding-bottom: 10px;
}

.platform-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  opacity: 0.7;
}

/* Media Panel */
.panel--media {
  background: var(--bg);
  overflow-y: auto;
}

.media-preview-area {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.media-preview {
  position: relative;
  background: var(--surface);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 280px;
}

.platform-frame {
  position: relative;
  width: 100%;
}

.preview-img, .preview-video {
  width: 100%;
  height: 280px;
  object-fit: cover;
  display: block;
  border-radius: 12px;
}

.frame-overlay {
  position: absolute;
  bottom: 10px;
  left: 10px;
  background: rgba(0,0,0,0.6);
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 10px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  backdrop-filter: blur(6px);
}

.preview-switcher {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.preview-switch-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  transition: all 0.2s;
}
.preview-switch-btn.active {
  border-color: var(--color);
  color: var(--color);
  box-shadow: 0 0 12px color-mix(in srgb, var(--color) 30%, transparent);
}

.crop-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.crop-badge {
  padding: 4px 10px;
  background: var(--surface);
  border: 1px solid;
  border-radius: 6px;
  display: flex;
  gap: 6px;
  align-items: center;
}

.crop-badge__platform { font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; }
.crop-badge__ratio { font-size: 10px; color: var(--text-muted); }

/* Video Convert */
.video-convert-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.convert-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  letter-spacing: 0.08em;
  color: var(--text-muted);
  text-transform: uppercase;
}

.convert-options { display: flex; align-items: center; justify-content: space-between; }

.duration-selector {
  display: flex;
  gap: 4px;
  background: var(--surface-2);
  border-radius: 8px;
  padding: 3px;
}

.duration-selector button {
  padding: 5px 12px;
  background: none;
  border: none;
  border-radius: 6px;
  color: var(--text-muted);
  font-family: inherit;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}
.duration-selector button.active { background: var(--accent); color: white; }

.effect-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: var(--text-muted);
  cursor: pointer;
}

.toggle {
  width: 32px;
  height: 18px;
  background: var(--surface-2);
  border-radius: 100px;
  position: relative;
  border: 1px solid var(--border);
  transition: background 0.2s;
}
.toggle.on { background: var(--accent); }
.toggle__knob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 12px;
  height: 12px;
  background: white;
  border-radius: 50%;
  transition: left 0.2s;
}
.toggle.on .toggle__knob { left: 16px; }

.convert-btn {
  padding: 10px;
  background: linear-gradient(135deg, rgba(124,111,255,0.15), rgba(94,79,255,0.1));
  border: 1px solid rgba(124,111,255,0.3);
  border-radius: 8px;
  color: var(--accent);
  font-family: inherit;
  font-size: 12px;
  letter-spacing: 0.06em;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.convert-btn:hover { border-color: var(--accent); background: rgba(124,111,255,0.2); }
.convert-btn.loading { opacity: 0.6; }

.remove-media-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: 1px solid rgba(255,100,100,0.2);
  color: rgba(255,100,100,0.5);
  font-family: inherit;
  font-size: 11px;
  padding: 8px 14px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  align-self: flex-start;
}
.remove-media-btn:hover { border-color: #ff6464; color: #ff6464; }

/* Spinners */
.spinner, .spinner-sm {
  display: inline-block;
  border-radius: 50%;
  border: 2px solid transparent;
  border-top-color: currentColor;
  animation: spin 0.7s linear infinite;
}
.spinner { width: 14px; height: 14px; }
.spinner-sm { width: 12px; height: 12px; }

@keyframes spin { to { transform: rotate(360deg); } }

/* Transitions */
.slide-enter-active, .slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateX(-8px); }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Scrollbars */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
</style>
