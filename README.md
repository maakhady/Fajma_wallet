<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development/)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


-----

# Fajma Wallet (Backend)

Fajma Wallet est une application web conçue pour la gestion de portefeuille électronique, avec des fonctionnalités telles que l'authentification des utilisateurs, la gestion des cartes, les transactions, etc. Ce projet contient uniquement le backend de l'application, développé avec le framework Laravel.

## Prérequis

Avant de commencer, assurez-vous d'avoir les outils suivants installés sur votre machine :

  * PHP \>= 8.2
  * Composer (gestionnaire de dépendances PHP)
  * PostgreSQL (ou un autre SGBD de votre choix, la configuration par défaut est pour PostgreSQL)

## Installation

Suivez ces étapes pour configurer le projet en local :

**1. Cloner le dépôt**

```bash
git clone <URL_DU_DEPOT>
cd fajma_wallet
```

**2. Installer les dépendances PHP**

Installez les dépendances du projet à l'aide de Composer.

```bash
composer install
```

**3. Configurer l'environnement**

Copiez le fichier d'exemple `.env.example` pour créer votre propre fichier de configuration `.env`.

```bash
cp .env.example .env
```

Ouvrez le fichier `.env` et configurez les variables d'environnement, notamment la connexion à la base de données (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

**4. Générer la clé de l'application**

Générez une clé de sécurité unique pour votre application Laravel.

```bash
php artisan key:generate
```

**5. Migrations et Seeders de la base de données**

Exécutez les migrations pour créer les tables de la base de données, puis remplissez les tables avec les données initiales à l'aide des seeders.

```bash
php artisan migrate --seed
```

**6. Générer le secret JWT**

Générez la clé secrète pour l'authentification JWT.

```bash
php artisan jwt:secret
```

## Lancement de l'application

Pour lancer l'application, vous devez démarrer le serveur Laravel et l'écouteur de file d'attente (queue).

**1. Démarrer le serveur Laravel**

```bash
php artisan serve
```

Votre API sera accessible à l'adresse `http://localhost:8000`.

-----

## API Backend

### Création d'un administrateur

Pour commencer à utiliser l'application, vous devez créer un premier utilisateur avec le rôle d'administrateur. Il existe une route d'inscription libre à cet effet.

**Route :** `POST http://127.0.0.1:8000/api/auth/register`

**Modèle de la requête :**

```json
{
    "first_name": "Nom",
    "last_name": "Prenom",
    "email": "email de contact",
    "phone": "Numero telephone valide",
    "password": "Mot de passe",
    "password_confirmation": "Confirmation de mot de passe",
    "role": "admin"
}
```

**Réponse en cas de succès :**

À la création de chaque utilisateur, une carte virtuelle Fajma est générée et associée automatiquement.

```json
{
    "message": "Inscription réussie.",
    "user": {
        "first_name": "Nom",
        "last_name": "Prenom",
        "email": "Email generer pour se connecter au dashboard de fajma qui a un domaine fajma.sn",
        "phone": "telephone",
        "role": "admin",
        "verification_code": "10568",
        "contact_email": "email de contact",
        "is_active": true,
        "updated_at": "2025-05-07T16:10:17.000000Z",
        "created_at": "2025-05-07T16:10:17.000000Z",
        "id": 1
    },
    "card": {
        "card_number": "FAJMA316760",
        "type_card": "virtual",
        "status": "activated",
        "balance": "0.00",
        "user_id": 1,
        "expires_at": "2026-05-07T00:00:00.000000Z",
        "updated_at": "2025-05-07T16:10:17.000000Z",
        "created_at": "2025-05-07T16:10:17.000000Z",
        "id": 1
    }
}
```

-----

  * **Vider le cache de configuration :**
    ```bash
    php artisan config:clear
    ```
  * **Vider le cache des routes :**
    ```bash
    php artisan route:clear
    ```
  * **Vider le cache de laravel :**
    ```bash
    php artisan cache:clear
    ```





