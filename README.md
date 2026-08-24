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

## Lancer

Copier les fichiers d'exemple d'environnement (`backend/.env.example`,
`frontend/.env.example`) et renseigner les identifiants des plateformes voulues.

```sh
docker compose up -d
```

Le frontend seul :

```sh
cd frontend
npm install
npm run dev
```

| Commande | Effet |
|---|---|
| `npm run build` | Verification `vue-tsc` puis bundle |
| `npm run build:widget` | Build du widget embarquable |

> Le depot ne versionne pas `backend/composer.json` : les dependances PHP ne sont pas figees
> ici, l'installation du backend passe par l'image Docker.
