<template>
  <ion-page class="portfolio-page">
    <div class="portfolio-wrap">
      <header class="page-header">
        <router-link to="/" class="back-btn"><ion-icon name="arrow-back-outline" /></router-link>
        <div>
          <h1 class="page-title">Portfolio</h1>
          <p class="page-sub">Your public content hub</p>
        </div>
      </header>

      <div class="portfolio-layout">
        <!-- Settings Panel -->
        <div class="settings-panel">
          <section class="settings-section">
            <h3 class="section-title">Status</h3>
            <div class="toggle-row">
              <div>
                <span class="toggle-label">Public Portfolio</span>
                <span class="toggle-sub">Allow anyone to view your portfolio</span>
              </div>
              <div class="toggle-switch" :class="{ on: form.is_public }" @click="form.is_public = !form.is_public">
                <span class="toggle-knob" />
              </div>
            </div>
          </section>

          <section class="settings-section">
            <h3 class="section-title">Profile</h3>

            <div class="field">
              <label class="field-label">Title</label>
              <input v-model="form.title" class="field-input" placeholder="Your name or brand" />
            </div>

            <div class="field">
              <label class="field-label">Bio</label>
              <textarea v-model="form.bio" class="field-textarea" placeholder="A short description..." rows="3" />
            </div>

            <div class="field">
              <label class="field-label">Custom Domain</label>
              <input v-model="form.custom_domain" class="field-input" placeholder="https://yourdomain.com" />
            </div>
          </section>

          <section class="settings-section">
            <h3 class="section-title">Theme</h3>
            <div class="theme-options">
              <button
                v-for="t in themes"
                :key="t.value"
                class="theme-btn"
                :class="{ active: form.theme === t.value }"
                @click="form.theme = t.value"
              >
                <span class="theme-preview" :style="t.style" />
                <span>{{ t.label }}</span>
              </button>
            </div>
          </section>

          <section class="settings-section">
            <h3 class="section-title">Platforms in Feed</h3>
            <div class="platform-toggles">
              <div class="platform-row" v-for="p in feedPlatforms" :key="p.key">
                <ion-icon :name="`logo-${p.key}`" :style="{ color: p.color }" />
                <span class="platform-row-label">{{ p.label }}</span>
                <div class="toggle-switch sm" :class="{ on: form[p.field] }" @click="(form as any)[p.field] = !(form as any)[p.field]">
                  <span class="toggle-knob" />
                </div>
              </div>
            </div>
          </section>

          <div class="settings-actions">
            <button class="btn-save" :class="{ loading: saving }" @click="save">
              <span class="spinner-sm" v-if="saving" />
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>

        <!-- Preview + Embed Panel -->
        <div class="preview-panel">
          <section class="embed-section">
            <h3 class="section-title">Embed Widget</h3>
            <p class="embed-desc">Drop your portfolio anywhere — any website, any framework.</p>

            <div class="embed-code-wrap">
              <pre class="embed-code"><code>{{ embedCode }}</code></pre>
              <button class="copy-btn" @click="copyEmbed">
                <ion-icon :name="copied ? 'checkmark-outline' : 'copy-outline'" />
                {{ copied ? 'Copied!' : 'Copy' }}
              </button>
            </div>

            <button class="btn-generate" @click="generateWidget" :disabled="!portfolio?.is_public">
              <ion-icon name="code-slash-outline" />
              Generate Widget Token
            </button>
          </section>

          <section class="portfolio-preview-section" v-if="portfolio?.is_public">
            <h3 class="section-title">Preview</h3>
            <div class="preview-frame" :data-theme="form.theme">
              <div class="preview-header">
                <div class="preview-avatar">{{ form.title?.[0] ?? '?' }}</div>
                <div>
                  <div class="preview-name">{{ form.title }}</div>
                  <div class="preview-bio">{{ form.bio?.slice(0, 60) }}</div>
                </div>
              </div>
              <div class="preview-grid">
                <div v-for="n in 6" :key="n" class="preview-cell">
                  <div class="preview-cell-inner" />
                </div>
              </div>
              <div class="preview-slug">
                portfolio/{{ portfolio.slug }}
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { IonPage, IonIcon } from '@ionic/vue'
import { api } from '@/services/api'

const portfolio = ref<any>(null)
const saving = ref(false)
const copied = ref(false)
const widgetToken = ref<string | null>(null)

const form = reactive({
  title: '',
  bio: '',
  theme: 'minimal',
  custom_domain: '',
  show_instagram: true,
  show_tiktok: true,
  show_youtube: true,
  is_public: false,
})

const themes = [
  { value: 'minimal', label: 'Minimal', style: 'background: #fff; border: 1px solid #eee;' },
  { value: 'dark',    label: 'Dark',    style: 'background: #111;' },
  { value: 'light',   label: 'Light',   style: 'background: #f5f5f5;' },
]

const feedPlatforms = [
  { key: 'instagram', label: 'Instagram', color: '#E4405F', field: 'show_instagram' },
  { key: 'tiktok',    label: 'TikTok',    color: '#000',    field: 'show_tiktok'    },
  { key: 'youtube',   label: 'YouTube',   color: '#FF0000', field: 'show_youtube'   },
]

const embedCode = computed(() => {
  if (!portfolio.value) return '<!-- Save your portfolio first -->'
  return `<social-portfolio slug="${portfolio.value.slug}" theme="${form.theme}" limit="9"></social-portfolio>
<script src="${window.location.origin}/widget/social-portfolio.js"><\/script>`
})

async function save() {
  saving.value = true
  try {
    const { data } = await api.put('/portfolio', { ...form })
    portfolio.value = data
  } finally {
    saving.value = false
  }
}

async function generateWidget() {
  const { data } = await api.post('/portfolio/widget-token')
  widgetToken.value = data.token
}

async function copyEmbed() {
  await navigator.clipboard.writeText(embedCode.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

onMounted(async () => {
  try {
    const { data } = await api.get('/portfolio')
    portfolio.value = data
    Object.assign(form, {
      title: data.title ?? '',
      bio: data.bio ?? '',
      theme: data.theme ?? 'minimal',
      custom_domain: data.custom_domain ?? '',
      show_instagram: data.show_instagram ?? true,
      show_tiktok: data.show_tiktok ?? true,
      show_youtube: data.show_youtube ?? true,
      is_public: data.is_public ?? false,
    })
  } catch { /* portfolio not configured yet */ }
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Cormorant+Garamond:wght@300;600&display=swap');

.portfolio-page {
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
  min-height: 100vh;
}

.portfolio-wrap { max-width: 1100px; margin: 0 auto; padding: 32px; }

.page-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 40px;
}

.back-btn {
  width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--border); border-radius: 8px;
  color: var(--text-muted); text-decoration: none; font-size: 18px;
  transition: all 0.15s;
}
.back-btn:hover { border-color: rgba(255,255,255,0.15); color: var(--text); }

.page-title { font-family: 'Cormorant Garamond', serif; font-size: 28px; font-weight: 600; margin: 0 0 2px; }
.page-sub { font-size: 11px; color: var(--text-muted); margin: 0; }

.portfolio-layout { display: grid; grid-template-columns: 380px 1fr; gap: 24px; }

.settings-panel { display: flex; flex-direction: column; gap: 24px; }

.settings-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-title {
  font-size: 10px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--text-muted);
  font-weight: 400;
  margin: 0 0 4px;
}

.toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.toggle-label { font-size: 13px; display: block; margin-bottom: 2px; }
.toggle-sub { font-size: 10px; color: var(--text-muted); }

.toggle-switch {
  width: 40px; height: 22px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 100px;
  position: relative;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s;
}
.toggle-switch.on { background: var(--accent); }
.toggle-switch.sm { width: 32px; height: 18px; }

.toggle-knob {
  position: absolute;
  top: 2px; left: 2px;
  width: 16px; height: 16px;
  background: white;
  border-radius: 50%;
  transition: left 0.2s;
}
.toggle-switch.on .toggle-knob { left: calc(100% - 18px); }
.toggle-switch.sm .toggle-knob { width: 12px; height: 12px; }
.toggle-switch.sm.on .toggle-knob { left: calc(100% - 14px); }

.field { display: flex; flex-direction: column; gap: 6px; }
.field-label { font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); }

.field-input, .field-textarea {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-family: inherit;
  font-size: 13px;
  padding: 10px 12px;
  outline: none;
  resize: none;
  transition: border-color 0.15s;
}
.field-input:focus, .field-textarea:focus { border-color: rgba(124,111,255,0.4); }

.theme-options { display: flex; gap: 8px; }
.theme-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 10px 8px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  font-family: inherit;
  font-size: 10px;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  transition: all 0.15s;
}
.theme-btn.active { border-color: var(--accent); color: var(--accent); }
.theme-preview { width: 32px; height: 20px; border-radius: 4px; display: block; }

.platform-toggles { display: flex; flex-direction: column; gap: 10px; }
.platform-row { display: flex; align-items: center; gap: 10px; }
.platform-row-label { flex: 1; font-size: 12px; }

.settings-actions { display: flex; justify-content: flex-end; }

.btn-save {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 11px 24px;
  background: var(--accent);
  border: none;
  border-radius: 9px;
  color: white;
  font-family: inherit;
  font-size: 12px;
  letter-spacing: 0.08em;
  cursor: pointer;
  transition: opacity 0.2s;
}
.btn-save:hover { opacity: 0.85; }
.btn-save.loading { opacity: 0.5; }

/* Preview Panel */
.preview-panel { display: flex; flex-direction: column; gap: 24px; }

.embed-section, .portfolio-preview-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.embed-desc { font-size: 12px; color: var(--text-muted); margin: 0; line-height: 1.5; }

.embed-code-wrap {
  position: relative;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
}

.embed-code {
  margin: 0;
  padding: 14px 50px 14px 14px;
  font-family: inherit;
  font-size: 11px;
  line-height: 1.6;
  color: rgba(255,255,255,0.6);
  white-space: pre-wrap;
  word-break: break-all;
}

.copy-btn {
  position: absolute;
  top: 8px; right: 8px;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--text-muted);
  font-family: inherit;
  font-size: 10px;
  cursor: pointer;
  transition: all 0.15s;
}
.copy-btn:hover { border-color: rgba(255,255,255,0.15); color: var(--text); }

.btn-generate {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: rgba(124,111,255,0.1);
  border: 1px solid rgba(124,111,255,0.25);
  border-radius: 8px;
  color: var(--accent);
  font-family: inherit;
  font-size: 11px;
  letter-spacing: 0.06em;
  cursor: pointer;
  transition: all 0.2s;
  align-self: flex-start;
}
.btn-generate:hover { background: rgba(124,111,255,0.18); }
.btn-generate:disabled { opacity: 0.3; cursor: not-allowed; }

/* Portfolio Preview Frame */
.preview-frame {
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--border);
}

.preview-frame[data-theme="dark"]  { background: #111; }
.preview-frame[data-theme="light"] { background: #f5f5f5; }
.preview-frame[data-theme="minimal"] { background: #fff; }

.preview-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid rgba(0,0,0,0.08);
}

.preview-avatar {
  width: 40px; height: 40px;
  border-radius: 50%;
  background: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: white;
  flex-shrink: 0;
}

.preview-name { font-size: 13px; font-weight: 600; color: #111; margin-bottom: 2px; }
.preview-bio  { font-size: 11px; color: #777; }

.preview-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2px;
  padding: 2px;
}

.preview-cell { aspect-ratio: 1; }
.preview-cell-inner {
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.06);
  border-radius: 2px;
  animation: shimmer 2s infinite alternate;
}
.preview-cell:nth-child(even) .preview-cell-inner { animation-delay: 0.5s; }
.preview-cell:nth-child(3n) .preview-cell-inner { animation-delay: 1s; }

@keyframes shimmer {
  from { opacity: 0.5; }
  to   { opacity: 1; }
}

.preview-slug {
  padding: 10px 16px;
  font-size: 10px;
  color: #999;
  border-top: 1px solid rgba(0,0,0,0.05);
  font-family: monospace;
}

.spinner-sm {
  display: inline-block; width: 12px; height: 12px;
  border: 2px solid transparent; border-top-color: white;
  border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
