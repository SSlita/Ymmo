# 🏠 Ymmo

> ⚠️ **Projet pédagogique** — Ce dépôt est un projet d'école réalisé dans le cadre du Bachelor 2 Informatique à **Ynov Campus** (UF INFRA & DEV). Ymmo est une entreprise **fictive** : le cas client, les données et les fonctionnalités sont utilisés uniquement à des fins d'apprentissage et d'évaluation.

## À propos

Ymmo est un groupe immobilier fictif implanté en France, spécialisé dans la vente et l'achat de biens résidentiels et professionnels. Ce projet consiste à développer sa plateforme web centralisée, permettant aux clients et aux agences de gérer des opérations d'achat et de vente de biens, avec des outils d'analyse de données pour suivre les tendances du marché.

Ce dépôt contient uniquement la **partie web (DEV)** du projet.

## Technologies

| Couche | Technologie |
|--------|-------------|
| Backend | PHP (POO : modèles, interfaces, autoloader), PDO |
| Base de données | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript |
| Analyse de données | Python 3.10+ (pandas, NumPy, scikit-learn, matplotlib, seaborn) |
| Environnement de dev | XAMPP (Apache + MySQL) |

## Structure du projet

```
Ymmo/
├── admin/        # Espace administrateur (gestion des agences)
├── api/          # Export CSV des données
├── assets/       # CSS, JavaScript, images
├── config/       # Configuration et connexion à la base de données
├── dashboard/    # Espace agent (biens, offres, messages, statistiques)
├── includes/     # Authentification, fonctions utilitaires, header/footer
├── models/       # Classes métier (Bien, Offre, Transaction, User)
├── python/       # Scripts d'analyse de données
├── *.php         # Pages publiques (accueil, biens, connexion, offres…)
└── ymmo.sql      # Schéma de la base de données + données de test
```

## Installation

### Prérequis

- [XAMPP](https://www.apachefriends.org/) (Apache + PHP + MySQL)
- Python 3.10+ (uniquement pour le module d'analyse)

### 1. Récupérer le projet

Cloner le dépôt dans le dossier `htdocs` de XAMPP, sous le nom `ymmo` :

```bash
cd C:\xampp\htdocs
git clone https://github.com/SSlita/Ymmo.git ymmo
```

### 2. Créer la base de données

1. Démarrer **Apache** et **MySQL** depuis le panneau XAMPP.
2. Ouvrir phpMyAdmin (`http://localhost/phpmyadmin`).
3. Importer le fichier `ymmo.sql` (il crée la base `ymmo` et insère des données de test).

### 3. Configurer l'application

Vérifier `config/config.php` et adapter si besoin :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ymmo');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_URL', 'http://localhost/ymmo');
```

### 4. Lancer le site

Ouvrir [http://localhost/ymmo](http://localhost/ymmo) dans un navigateur.

Aucun compte n'est fourni par défaut : créer un compte client depuis la page d'inscription. Les rôles `agent` et `admin` s'attribuent via la colonne `role` de la table `users`.

## Module d'analyse de données (Python)

```bash
cd python
pip install -r requirements.txt
cp .env.example .env     # puis renseigner les identifiants MySQL
python analyse.py        # analyse complète
```

Options : `--rapport` (rapport de ventes), `--predictions` (prédictions de prix et de vente), `--zones` (zones attractives). Les résultats (CSV et graphiques) sont générés dans `python/output/`.

## Statut

Projet réalisé à des fins pédagogiques — non destiné à une utilisation en production.
