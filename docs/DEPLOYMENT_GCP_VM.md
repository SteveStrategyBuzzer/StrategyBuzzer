# Guide de Déploiement StrategyBuzzer sur Google Cloud VM

> **ATTENTION** : Ce document est un guide de référence uniquement.  
> Il ne contient aucun secret réel et ne doit pas être exécuté automatiquement.  
> Tous les exemples de configuration doivent être copiés et adaptés manuellement.

---

## Table des matières

1. [Prérequis](#1-prérequis)
2. [Configuration de la VM](#2-configuration-de-la-vm)
3. [Installation des dépendances système](#3-installation-des-dépendances-système)
4. [Clonage du projet](#4-clonage-du-projet)
5. [Configuration Laravel (Backend PHP)](#5-configuration-laravel-backend-php)
6. [Configuration du Game Server (Node.js)](#6-configuration-du-game-server-nodejs)
7. [Configuration des services systemd](#7-configuration-des-services-systemd)
8. [Configuration Nginx](#8-configuration-nginx)
9. [Certificat SSL (Let's Encrypt)](#9-certificat-ssl-lets-encrypt)
10. [Variables d'environnement requises](#10-variables-denvironnement-requises)
11. [Vérifications post-déploiement](#11-vérifications-post-déploiement)
12. [Maintenance et mises à jour](#12-maintenance-et-mises-à-jour)

---

## 1. Prérequis

### VM Google Cloud recommandée
- **Type de machine** : e2-medium (2 vCPU, 4 Go RAM) minimum
- **Système d'exploitation** : Ubuntu 22.04 LTS
- **Stockage** : 20 Go SSD minimum
- **Région** : Choisir selon votre audience cible

### Ports à ouvrir (Firewall)
- `80` (HTTP)
- `443` (HTTPS)
- `22` (SSH)

### Services externes requis
- Base de données PostgreSQL (Cloud SQL ou externe)
- Projet Firebase configuré avec Firestore et Authentication
- Compte Stripe (clés API)
- Clé API OpenAI et/ou Gemini

---

## 2. Configuration de la VM

### Création via Google Cloud Console

1. Accéder à Compute Engine > Instances VM
2. Cliquer sur "Créer une instance"
3. Configurer selon les prérequis ci-dessus
4. Activer "Autoriser le trafic HTTP/HTTPS"
5. Créer et noter l'adresse IP externe

### Connexion SSH

```bash
# Via gcloud CLI
gcloud compute ssh NOM_INSTANCE --zone=ZONE

# Ou via SSH direct
ssh -i ~/.ssh/votre_cle utilisateur@IP_EXTERNE
```

---

## 3. Installation des dépendances système

### Mise à jour du système

```bash
sudo apt update && sudo apt upgrade -y
```

### PHP 8.2 et extensions

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-pdo php8.2-pgsql \
    php8.2-redis php8.2-curl php8.2-xml php8.2-mbstring php8.2-zip \
    php8.2-intl php8.2-bcmath php8.2-gd
```

### Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Nginx

```bash
sudo apt install -y nginx
```

### Outils supplémentaires

```bash
sudo apt install -y git unzip supervisor
```

---

## 4. Clonage du projet

### Préparer le répertoire

```bash
sudo mkdir -p /var/www
sudo chown $USER:$USER /var/www
cd /var/www
```

### Cloner depuis GitHub

```bash
git clone https://github.com/VOTRE_ORGANISATION/strategybuzzer.git
cd strategybuzzer
```

### Structure du projet

```
/var/www/strategybuzzer/
├── app/                    # Code Laravel (Controllers, Services, etc.)
├── apps/
│   └── game-server/        # Game Server Node.js (Socket.IO)
├── config/                 # Configuration Laravel
├── database/               # Migrations et seeders
├── public/                 # Point d'entrée web (index.php, assets)
├── resources/              # Vues Blade, JS, CSS
├── routes/                 # Routes Laravel
├── storage/                # Logs, cache, sessions
├── .env                    # Variables d'environnement (à créer)
└── composer.json           # Dépendances PHP
```

---

## 5. Configuration Laravel (Backend PHP)

### Installation des dépendances

```bash
cd /var/www/strategybuzzer
composer install --no-dev --optimize-autoloader
```

### Permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### Création du fichier .env

```bash
cp .env.example .env
nano .env
```

> **NOTE** : Remplir manuellement avec vos propres valeurs.  
> Voir la section [Variables d'environnement requises](#10-variables-denvironnement-requises).

### Génération de la clé d'application

```bash
php artisan key:generate
```

### Exécution des migrations

```bash
php artisan migrate --force
```

### Optimisation pour la production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 6. Configuration du Game Server (Node.js)

### Installation des dépendances

```bash
cd /var/www/strategybuzzer/apps/game-server
npm install
```

### Compilation TypeScript

```bash
npm run build
```

### Vérification

Le serveur compilé sera dans `apps/game-server/dist/index.js`

---

## 7. Configuration des services systemd

> **NOTE** : Les fichiers ci-dessous sont des exemples à créer manuellement.  
> Ne pas copier-coller aveuglément - adapter selon votre configuration.

### Service Queue Worker Laravel

Créer le fichier `/etc/systemd/system/strategybuzzer-queue.service` :

```ini
[Unit]
Description=StrategyBuzzer Laravel Queue Worker
After=network.target redis-server.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/strategybuzzer
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=120
Restart=always
RestartSec=5
StandardOutput=append:/var/log/strategybuzzer/queue.log
StandardError=append:/var/log/strategybuzzer/queue-error.log

[Install]
WantedBy=multi-user.target
```

### Service Game Server Node.js

Créer le fichier `/etc/systemd/system/strategybuzzer-game.service` :

```ini
[Unit]
Description=StrategyBuzzer Game Server (Socket.IO)
After=network.target redis-server.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/strategybuzzer/apps/game-server
ExecStart=/usr/bin/node dist/index.js
Restart=always
RestartSec=5
Environment=NODE_ENV=production
Environment=PORT=3001
Environment=REDIS_URL=redis://127.0.0.1:6379
# NOTE: Remplacer par votre vrai secret JWT
Environment=JWT_SECRET=VOTRE_JWT_SECRET_ICI
StandardOutput=append:/var/log/strategybuzzer/game-server.log
StandardError=append:/var/log/strategybuzzer/game-server-error.log

[Install]
WantedBy=multi-user.target
```

### Créer le répertoire de logs

```bash
sudo mkdir -p /var/log/strategybuzzer
sudo chown www-data:www-data /var/log/strategybuzzer
```

### Activer et démarrer les services

```bash
sudo systemctl daemon-reload
sudo systemctl enable strategybuzzer-queue strategybuzzer-game
sudo systemctl start strategybuzzer-queue strategybuzzer-game
```

### Vérifier le statut

```bash
sudo systemctl status strategybuzzer-queue
sudo systemctl status strategybuzzer-game
```

---

## 8. Configuration Nginx

> **NOTE** : Exemple de configuration à adapter selon votre domaine.

### Créer la configuration du site

Créer le fichier `/etc/nginx/sites-available/strategybuzzer` :

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name VOTRE_DOMAINE.com www.VOTRE_DOMAINE.com;
    
    root /var/www/strategybuzzer/public;
    index index.php index.html;

    # Logs
    access_log /var/log/nginx/strategybuzzer-access.log;
    error_log /var/log/nginx/strategybuzzer-error.log;

    # Taille max des uploads
    client_max_body_size 50M;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Socket.IO - Proxy vers Game Server
    location /socket.io/ {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # Assets statiques - cache long
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Sécurité - bloquer les fichiers sensibles
    location ~ /\.(?!well-known) {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }
}
```

### Activer le site

```bash
sudo ln -s /etc/nginx/sites-available/strategybuzzer /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default  # Optionnel
sudo nginx -t
sudo systemctl restart nginx
```

---

## 9. Certificat SSL (Let's Encrypt)

### Installation de Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
```

### Obtenir le certificat

```bash
sudo certbot --nginx -d VOTRE_DOMAINE.com -d www.VOTRE_DOMAINE.com
```

### Renouvellement automatique

Le renouvellement est configuré automatiquement via systemd timer.  
Vérifier avec :

```bash
sudo systemctl status certbot.timer
```

---

## 10. Variables d'environnement requises

> **IMPORTANT** : Ne jamais commiter le fichier `.env` dans Git.  
> Les valeurs ci-dessous sont des placeholders - remplacer par vos vraies valeurs.

### Fichier .env complet (template)

```env
# Application
APP_NAME=StrategyBuzzer
APP_ENV=production
APP_KEY=  # Généré avec php artisan key:generate
APP_DEBUG=false
APP_URL=https://VOTRE_DOMAINE.com

# Base de données PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=VOTRE_HOST_POSTGRESQL
DB_PORT=5432
DB_DATABASE=VOTRE_NOM_DE_BASE
DB_USERNAME=VOTRE_UTILISATEUR
DB_PASSWORD=VOTRE_MOT_DE_PASSE

# Ou utiliser DATABASE_URL (format connection string)
# DATABASE_URL=postgresql://user:password@host:5432/database

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Session et Cache
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_DOMAIN=.VOTRE_DOMAINE.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_DRIVER=redis
QUEUE_CONNECTION=database

# Game Server
GAME_SERVER_URL=https://VOTRE_DOMAINE.com
GAME_SERVER_JWT_SECRET=VOTRE_SECRET_JWT_UNIQUE_ET_SECURISE

# Question API
QUESTION_API_URL=http://localhost:3000

# Firebase
FIREBASE_PROJECT_ID=VOTRE_PROJECT_ID_FIREBASE
FIREBASE_CREDENTIALS={"type":"service_account","project_id":"..."}
# NOTE: FIREBASE_CREDENTIALS doit contenir le JSON complet du service account

# Stripe
STRIPE_KEY=pk_live_VOTRE_CLE_PUBLIQUE
STRIPE_SECRET_KEY=sk_live_VOTRE_CLE_SECRETE
STRIPE_WEBHOOK_SECRET=whsec_VOTRE_SECRET_WEBHOOK

# OpenAI
OPENAI_API_KEY=sk-VOTRE_CLE_API_OPENAI

# Google Gemini
GEMINI_API_KEY=VOTRE_CLE_API_GEMINI

# Mail (optionnel)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=VOTRE_EMAIL
MAIL_PASSWORD=VOTRE_MOT_DE_PASSE
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@VOTRE_DOMAINE.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 11. Vérifications post-déploiement

### Checklist de validation

- [ ] Le site est accessible via HTTPS
- [ ] La page de connexion fonctionne
- [ ] Les requêtes API répondent correctement
- [ ] Socket.IO se connecte (vérifier la console du navigateur)
- [ ] Les jobs de queue sont traités
- [ ] Redis fonctionne (sessions, cache)
- [ ] Les logs ne montrent pas d'erreurs critiques

### Commandes de diagnostic

```bash
# Statut des services
sudo systemctl status strategybuzzer-queue
sudo systemctl status strategybuzzer-game
sudo systemctl status php8.2-fpm
sudo systemctl status nginx
sudo systemctl status redis-server

# Logs Laravel
tail -f /var/www/strategybuzzer/storage/logs/laravel.log

# Logs Game Server
tail -f /var/log/strategybuzzer/game-server.log

# Logs Nginx
tail -f /var/log/nginx/strategybuzzer-error.log

# Test Redis
redis-cli ping  # Doit répondre PONG

# Test base de données
cd /var/www/strategybuzzer
php artisan tinker --execute="DB::connection()->getPdo();"
```

---

## 12. Maintenance et mises à jour

### Déploiement d'une nouvelle version

```bash
cd /var/www/strategybuzzer

# Récupérer les dernières modifications
git pull origin main

# Mettre à jour les dépendances PHP
composer install --no-dev --optimize-autoloader

# Mettre à jour les dépendances Node.js
cd apps/game-server
npm install
npm run build
cd ../..

# Migrations (si nécessaire)
php artisan migrate --force

# Vider les caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Reconstruire les caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Redémarrer les services
sudo systemctl restart strategybuzzer-queue
sudo systemctl restart strategybuzzer-game
sudo systemctl restart php8.2-fpm
```

### Sauvegardes recommandées

- **Base de données** : Backup quotidien via Cloud SQL ou pg_dump
- **Fichiers uploadés** : Sync vers Cloud Storage
- **Configuration** : Sauvegarder `.env` de manière sécurisée

### Monitoring recommandé

- Google Cloud Monitoring pour les métriques VM
- Sentry ou Bugsnag pour les erreurs applicatives
- UptimeRobot ou similaire pour la disponibilité

---

## Ressources additionnelles

- [Documentation Laravel](https://laravel.com/docs/10.x)
- [Documentation Socket.IO](https://socket.io/docs/v4/)
- [Firebase Admin SDK PHP](https://firebase-php.readthedocs.io/)
- [Google Cloud Compute Engine](https://cloud.google.com/compute/docs)

---

*Document créé le : Février 2026*  
*Dernière mise à jour : Février 2026*  
*Version du projet : StrategyBuzzer v1.0*
