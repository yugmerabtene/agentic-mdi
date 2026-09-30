# Cahier des Charges

## Application de Gestion Utilisateur

---

## 1. Présentation du Projet

### 1.1 Contexte
Développement d'une application web permettant la gestion complète des utilisateurs (inscription, connexion, déconnexion) avec une architecture moderne conteneurisée.

### 1.2 Objectifs
- Fournir un système d'authentification sécurisé
- Permettre la gestion du cycle de vie utilisateur
- Offrir une architecture déployable et reproductible via Docker
- Utiliser uniquement des technologies vanilla (pas de framework)

### 1.3 Public Cible
- Développeurs souhaitant une base d'authentification réutilisable
- Projets nécessitant une gestion utilisateur simple et légère

---

## 2. Périmètre Fonctionnel

### 2.1 Fonctionnalités Principales

#### F1 - Inscription
- Formulaire avec : nom, prénom, email, mot de passe, confirmation
- Validation côté client (JS) et côté serveur (PHP)
- Vérification unicité de l'email
- Hashage du mot de passe (bcrypt via `password_hash`)
- Enregistrement en base de données

#### F2 - Connexion
- Formulaire email + mot de passe
- Vérification des identifiants
- Création d'une session PHP sécurisée
- Redirection vers le tableau de bord

#### F3 - Déconnexion
- Destruction complète de la session
- Suppression du cookie de session
- Redirection vers la page de connexion

#### F4 - Tableau de bord (protégé)
- Accessible uniquement si connecté
- Affichage des informations utilisateur
- Bouton de déconnexion

### 2.2 Fonctionnalités Secondaires (optionnelles)
- Modification du profil
- Changement de mot de passe
- Réinitialisation par email
- Suppression de compte

---

## 3. Architecture Technique

### 3.1 Stack Technologique

| Couche | Technologie |
|--------|-------------|
| Frontend | HTML5, CSS3, JavaScript (vanilla) |
| Backend | PHP 8.2+ (vanilla, sans framework) |
| Base de données | MySQL 8.0 |
| Serveur web | Apache (avec mod_php) |
| Conteneurisation | Docker + Docker Compose |

### 3.2 Architecture des Conteneurs

```
┌─────────────────────────────────────────┐
│           Docker Network (bridge)       │
│                                         │
│  ┌──────────────┐    ┌──────────────┐   │
│  │   app-web    │───▶│    mysql     │   │
│  │  (Apache+PHP)│    │   (MySQL)    │   │
│  │  Port 8080   │    │  Port 3306   │   │
│  └──────────────┘    └──────────────┘   │
│         │                   │           │
└─────────┼───────────────────┼───────────┘
          │                   │
      Volume              Volume
      (code)            (db_data)
```

### 3.3 Structure des Fichiers

```
projet/
├── docker-compose.yml
├── app/
│   ├── Dockerfile
│   ├── public/
│   │   ├── index.php
│   │   ├── register.php
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── dashboard.php
│   │   ├── css/
│   │   │   └── style.css
│   │   └── js/
│   │       ├── validation.js
│   │       └── app.js
│   ├── src/
│   │   ├── config/
│   │   │   └── database.php
│   │   ├── controllers/
│   │   │   ├── AuthController.php
│   │   │   └── UserController.php
│   │   ├── models/
│   │   │   └── User.php
│   │   └── helpers/
│   │       ├── session.php
│   │       └── validator.php
│   └── sql/
│       └── init.sql
└── README.md
```

---

## 4. Spécifications Détaillées

### 4.1 Base de Données

**Base :** `gestion_users`

**Table : `users`**

| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | INT UNSIGNED | PK, AUTO_INCREMENT |
| nom | VARCHAR(50) | NOT NULL |
| prenom | VARCHAR(50) | NOT NULL |
| email | VARCHAR(255) | UNIQUE, NOT NULL |
| password | VARCHAR(255) | NOT NULL |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP |
| last_login | TIMESTAMP | NULL |

### 4.2 Docker

#### `docker-compose.yml` (schéma)
```yaml
services:
  app:
    build: ./app
    ports: ["8080:80"]
    depends_on: [db]
    environment:
      - DB_HOST=db
      - DB_NAME=gestion_users
      - DB_USER=appuser
      - DB_PASS=apppassword
    volumes:
      - ./app:/var/www/html

  db:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - MYSQL_DATABASE=gestion_users
      - MYSQL_USER=appuser
      - MYSQL_PASSWORD=apppassword
    volumes:
      - db_data:/var/lib/mysql
      - ./app/sql/init.sql:/docker-entrypoint-initdb.d/init.sql
    ports: ["3307:3306"]

volumes:
  db_data:
```

#### `app/Dockerfile`
```dockerfile
FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2enmod rewrite
COPY . /var/www/html/
```

### 4.3 Sécurité

| Mesure | Implémentation |
|--------|----------------|
| Hashage mots de passe | `password_hash()` avec `PASSWORD_BCRYPT` |
| Requêtes SQL | PDO + requêtes préparées |
| Sessions | `session_regenerate_id()`, cookies `HttpOnly`, `SameSite=Strict` |
| Validation | Côté serveur obligatoire (jamais se fier au JS) |
| XSS | `htmlspecialchars()` sur toutes sorties |
| CSRF | Token dans formulaires sensibles |
| Erreurs | Affichage désactivé en prod, loggué |

### 4.4 Règles de Validation

**Nom / Prénom :**
- 2 à 50 caractères
- Lettres, tirets, apostrophes

**Email :**
- Format valide (`filter_var`)
- Unique en base

**Mot de passe :**
- Minimum 8 caractères
- Au moins 1 majuscule, 1 minuscule, 1 chiffre
- Confirmation identique

### 4.5 Interface Utilisateur

#### Pages
1. **`/register`** - Formulaire d'inscription
2. **`/login`** - Formulaire de connexion
3. **`/dashboard`** - Page protégée
4. **`/logout`** - Endpoint de déconnexion

#### Design
- Responsive (mobile-first)
- CSS vanilla, variables CSS
- Validation JS temps réel
- Messages d'erreur clairs sous chaque champ
- Indicateur de force du mot de passe

---

## 5. Flux Applicatifs

### 5.1 Inscription
```
Utilisateur → /register → POST → Validation serveur
    ↓ (valide)
Hash password → INSERT users → Session → /dashboard
    ↓ (invalide)
Retour formulaire + erreurs
```

### 5.2 Connexion
```
Utilisateur → /login → POST → SELECT user by email
    ↓ (trouvé)
password_verify() → session_regenerate_id() → /dashboard
    ↓ (échec)
Message erreur générique
```

### 5.3 Déconnexion
```
/dashboard → POST /logout → session_destroy()
    → unset cookies → /login
```

---

## 6. Contraintes Techniques

- **Pas de framework** PHP (pas de Laravel, Symfony…)
- **Pas de framework** JS (pas de React, Vue…)
- **Pas de CDN** : tout doit être local
- PHP ≥ 8.0
- Compatible navigateurs modernes (Chrome, Firefox, Safari, Edge)
- Docker Desktop / Docker Engine ≥ 20.10

---

## 7. Livrables

1. Code source complet et commenté
2. `docker-compose.yml` fonctionnel
3. `Dockerfile` pour l'application
4. Script SQL d'initialisation
5. `README.md` avec :
   - Instructions de démarrage (`docker compose up -d`)
   - Variables d'environnement
   - Comptes de test éventuels
6. Documentation API interne (endpoints PHP)

---

## 8. Critères d'Acceptation

- [ ] `docker compose up -d` démarre l'application sans erreur
- [ ] L'inscription crée un utilisateur valide en BDD
- [ ] Le mot de passe est hashé (non lisible en clair en BDD)
- [ ] La connexion avec identifiants corrects ouvre une session
- [ ] La connexion avec identifiants incorrects échoue proprement
- [ ] `/dashboard` est inaccessible sans session valide
- [ ] La déconnexion détruit la session
- [ ] Les erreurs SQL/XSS sont neutralisées
- [ ] Le responsive fonctionne sur mobile
- [ ] Les données persistent après `docker compose restart` (volume)

---

## 9. Planning Prévisionnel

| Phase | Durée estimée |
|-------|---------------|
| Setup Docker + structure | 0.5 j |
| Base de données + init SQL | 0.5 j |
| Backend (auth, sessions, sécurité) | 2 j |
| Frontend (HTML/CSS/JS) | 1.5 j |
| Tests + corrections | 1 j |
| Documentation | 0.5 j |
| **Total** | **6 jours** |

---

## 10. Évolutions Futures

- Authentification à deux facteurs (2FA)
- Connexion OAuth (Google, GitHub)
- Gestion des rôles (admin, user)
- API REST pour clients externes
- Journalisation des connexions
- Rate limiting sur /login
- Tests automatisés (PHPUnit, Cypress)

---