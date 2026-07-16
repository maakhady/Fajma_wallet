# Fajma Wallet — Backend de paiement mobile (Laravel)

Backend d'un portefeuille électronique développé pour le secteur de la e-santé, intégrant les paiements mobiles **Wave** et **Orange Money** pour la gestion des crédits clients. Il expose une API consommée par le back-office Angular ([fajma-backoffice](https://github.com/maakhady/fajma-backoffice)) et une application mobile.

> Version de travail personnelle développée dans le cadre de mon stage chez Faj'ma ESANTÉ. La version en production est distincte et propriété de l'entreprise ; ce dépôt ne contient aucune donnée réelle ni identifiant.

## Architecture

Architecture MVC Laravel étendue avec une **couche de services** (`app/Services`) pour la logique métier et des **Request Objects** (`app/Http/Requests`) pour la validation des entrées.

### Couche de services

| Service | Rôle |
|---|---|
| `TransactionService` | Création, validation, suivi et mise à jour des transactions (dépôts, paiements, retraits) |
| `WaveService` | Intégration de la passerelle de paiement Wave |
| `OrangeMoneyService` | Intégration de la passerelle de paiement Orange Money |
| `PaymentTypeService` | Gestion des types de passerelles de paiement |
| `PaymentMeanService` | Moyens de paiement des utilisateurs (comptes externes, cartes) |
| `ProviderService` | Gestion des fournisseurs de services |
| `LogService` | Journalisation des actions du système en base de données |

### Flux de paiement

1. La requête utilisateur (`CreateDepositRequest` / `CreatePaymentRequest`) arrive au contrôleur et est déléguée au `TransactionService`
2. Le `TransactionService` initie le paiement via la passerelle appropriée (`WaveService` ou `OrangeMoneyService`)
3. Le `WebhookController` reçoit les notifications de statut en temps réel du fournisseur et met à jour l'état de la transaction en base
4. Les passerelles sont configurables via des requêtes dédiées (`ConfigureWaveRequest`, `ConfigureOrangeMoneyRequest`)

## Modèle de données

Entités principales : `User`, `Transaction`, `Card`, `Provider`, `PaymentType`, `PaymentMean`, `PaymentStatus`, `TransactionType`, `Log`. Une transaction est liée à un utilisateur, une carte, un fournisseur, un type de paiement, un statut et un type de transaction.

## Fonctionnalités complémentaires

- **Authentification par tokens** (login, logout, register avec Request Objects dédiés)
- **Reporting** : exportation des transactions et génération de reçus PDF via des vues Blade
- **Commandes Artisan** : initialisation des statuts de paiement, maintenance des données, et une commande personnalisée `make:service` pour générer la couche de services

## Stack technique

| Couche | Technologies |
|---|---|
| Framework | Laravel (PHP) |
| Base de données | PostgreSQL |
| Authentification | Tokens (API) |
| Intégrations | API Wave, API Orange Money (paiement mobile), webhooks |

## Lancement local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Auteur

**Mame Khady Laye DIAW** — Développeuse Full-Stack, Dakar
[GitHub](https://github.com/maakhady) · [LinkedIn](https://linkedin.com/in/mamekhady)
