# omni-share-tool

Hub de multidiffusion sociale : publier un meme contenu vers plusieurs reseaux depuis une
seule interface.

## Plateformes

Six publishers sont implementes (`backend/app/Services/SocialPublisher/`) :
Facebook, Instagram, TikTok, YouTube, Snapchat, Pinterest.
Les identifiants de chaque plateforme sont declares dans `backend/config/services.php`
(`meta`, `instagram`, `tiktok`, `google`, `youtube`, `snapchat`, `pinterest`).

L'autorisation OAuth est ecrite a la main dans `PlatformController` et le modele
`PlatformAccount` — le projet n'utilise pas Laravel Socialite.

## Structure

| Dossier | Contenu |
|---|---|
| `backend/` | API Laravel : `app/`, `config/`, `database/`, `routes/api.php`, `tests/` |
| `frontend/` | Client Ionic / Vue 3 en TypeScript |
| `docker/` | Configuration nginx |
| `docker-compose.yml` | Orchestration des services |

Autres services metier du backend : `MediaProcessingService`, `VideoGenerationService`.

## Stack

**Backend** : Laravel (PHP), conteneurise via `backend/Dockerfile`.

**Frontend** : Ionic Vue (`@ionic/vue`, `@ionic/vue-router`), Capacitor
(`@capacitor/core`, `@capacitor/camera`, `@capacitor/browser`), Pinia, Vue Router, axios,
Laravel Echo et Pusher pour le temps reel, Vite, TypeScript.

Une seconde cible de build produit un widget embarquable (`vite.widget.config.ts`).

## Etat du depot

Le depot contient le code applicatif (controllers, modeles, services de publication, jobs,
migrations, vues et stores Vue) mais PAS le squelette necessaire a un build : il ne se lance
pas en l'etat.

| Absent | Consequence |
|---|---|
| `backend/composer.json`, `artisan`, `bootstrap/app.php`, `public/index.php`, `config/app.php` | `backend/Dockerfile` lance `composer install` sans `composer.json` : pas d'image, pas d'API |
| `frontend/index.html` | Vite n'a pas de point d'entree (ni `index.html`, ni `build.rollupOptions.input`) |

Pour l'executer, il faut partir d'un squelette Laravel et d'un projet Vite + Vue 3, puis y
recopier ces sources.

## Securite

- Inscription publique fermee par defaut (`ALLOW_REGISTRATION=false`).
- Upload : types acceptes JPG, PNG, GIF, MP4, MOV, WEBM, controles sur le type detecte (y compris
  apres assemblage d'un upload par morceaux) ; seul `index.php` est execute par nginx.
