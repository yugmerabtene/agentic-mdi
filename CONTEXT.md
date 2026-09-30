# Contexte de travail — application de gestion utilisateur

Traduit de `CDC/cdc.md`. Tenu par l'orchestrateur, ne pas modifier à la main.

---

## 1. Finalité

Fournir à un développeur une base d'authentification réutilisable — inscription,
connexion, déconnexion, tableau de bord protégé — qui démarre d'une seule
commande dans un environnement de laboratoire reproductible, sans framework et
sans ressource distante.

## 2. Périmètre

- Inscription avec validation double, côté navigateur et côté serveur, et
  unicité de l'adresse électronique vérifiée en base.
- Connexion par adresse électronique et mot de passe, ouverture de session
  régénérée, redirection vers le tableau de bord.
- Déconnexion détruisant la session et supprimant le cookie.
- Tableau de bord accessible uniquement avec une session valide.
- Pile conteneurisée : Apache avec PHP, MySQL, un fichier de composition
  Docker.
- Script d'initialisation du schéma, et documentation de démarrage.

## 3. Hors périmètre

- Les fonctionnalités secondaires du cahier des charges (section 2.2) : profil,
  changement de mot de passe, réinitialisation par courriel, suppression de
  compte. Elles sont renvoyées à une version ultérieure.
- Les évolutions de la section 10 du cahier des charges : double facteur,
  connexion OAuth, rôles, interface de programmation, journalisation, limitation
  de débit, PHPUnit et Cypress.
- Tout framework PHP ou JavaScript, toute ressource distante (CDN), toute
  connexion à un service externe.
- Le système agentique lui-même (`opencode.json`, `.opencode/`, `AGENTS.md`,
  `suivi.json`), qui reste l'outillage de ce dépôt et non une partie de
  l'application livrée.

## 4. Contraintes imposées

| Contrainte | Origine | Vérification |
| --- | --- | --- |
| PHP 8.2, sans framework | CDC §3.1, §6 | `FROM php:8.2-apache`, aucun paquet de framework |
| JavaScript natif, sans framework ni CDN | CDC §3.1, §6 | Aucune ressource externe dans les pages |
| MySQL 8.0 | CDC §3.1 | `image: mysql:8.0` |
| Base `gestion_users`, table `users` aux sept colonnes imposées | CDC §4.1 | `app/sql/init.sql` |
| Hashage bcrypt par `password_hash` | CDC §4.3 | Aucun mot de passe en clair en base |
| Requêtes préparées | CDC §4.3 | Aucune concaténation de valeur dans une requête |
| Jeton CSRF sur les formulaires sensibles | CDC §4.3 | Présent à l'émission, vérifié à la réception |
| Échappement des sorties par `htmlspecialchars` | CDC §4.3 | Aucune donnée utilisateur rendue brute |
| Erreurs désactivées à l'affichage, journalisées | CDC §4.3 | Aucun message d'exception renvoyé au navigateur |
| Mots de passe : 8 caractères, une majuscule, une minuscule, un chiffre | CDC §4.4 | Test de validation qui échoue sur `password` |
| Volume de données persistant | CDC §4.5, §8 | Contrôle après `docker compose restart` |
| Aucun secret en clair dans le dépôt | Règle permanente du poste | `./check-secrets.sh` renvoie 0 |
| Vocabulaire **LAB** | Règle permanente du poste | Documentation et messages |

## 5. Choix techniques

| Sujet | Décision | Justification | Alternative écartée |
| --- | --- | --- | --- |
| Emplacement du code | Racine du dépôt : `docker-compose.yml`, `app/`, `scripts/` | Le CDC nomme une racine générique `projet/` ; ce dépôt est déjà le projet, et `app/` reprend tel quel l'arborescence du CDC §3.3 | Créer un sous-dossier `projet/` |
| Racine web | `DocumentRoot` pointé sur `/var/www/html/public` | Le CDC §4.5 déclare les adresses `/register`, `/login`, `/dashboard`, `/logout`, mais son Dockerfile laisse `DocumentRoot` sur la racine de l'application : les pages seraient alors servies sous `/public/` et `src/config/database.php` serait téléchargeable | Laisser la racine web sur `/var/www/html` |
| Adresses | Réécriture interne vers les fichiers `.php`, via `.htaccess` | Le CDC annonce des adresses sans extension et active `mod_rewrite` dans son Dockerfile, mais ne fournit pas la règle | Exposer `/register.php` |
| Version de PHP | 8.2 | La section 3.1 et le Dockerfile du CDC demandent 8.2 ; la section 6 dit « ≥ 8.0 », ce qui est une contradiction interne du CDC, tranchée en faveur de la version la plus précise | 8.0, qui refuse des fonctions utilisées ici |
| Accès base de données | PDO exclusivement | Le CDC §4.3 impose PDO et les requêtes préparées ; `mysqli` n'est exigé nulle part | Installer et maintenir les deux pilotes |
| Mots de passe de démonstration | Gabarits dans `.env.example`, valeurs de laboratoire générées à l'exécution | `./check-secrets.sh` refuse toute ligne `...PASSWORD=` suivie d'au moins douze caractères alphanumériques : les valeurs littérales du CDC §4.2 seraient bloquées à la version | Reprendre les valeurs du CDC, refusées par le contrôle |
| Amorçage du code | `app/src/bootstrap.php`, chargé par chaque page | Sans framework, il faut un point d'entrée unique qui charge la configuration, la session et l'autolochargeur ; le placer dans `src/` le tient hors de la racine web | Une liste de `require` répétée dans les cinq pages |
| Preuve | `scripts/verifier.sh`, contrôles en langage HTTP et en SQL dans les conteneurs | Le CDC ne donne aucune commande de vérification et renvoie PHPUnit et Cypress aux évolutions futures ; la preuve doit donc être réelle sans framework de test | Déclarer la tâche vérifiée sans commande |
| Contraintes de sécurité | Fichier `app/src/Config` non requis : configuration par fonction simple lisant l'environnement | L'arbre du CDC prévoit un `database.php` ; une fonction de connexion unique suffit et garde un seul point de lecture de l'environnement | Une couche de configuration objet |

## 6. Conventions

- **Arborescence** : `docker-compose.yml` et `app/` à la racine, comme le CDC
  §3.3. `app/public/` est la racine web, `app/src/` la logique applicative,
  `app/sql/` le schéma, `scripts/` les outils de laboratoire.
- **Nommage** : PHP en PascalCase pour les classes (`User`, `AuthController`),
  snake_case pour les fonctions et les méthodes. Les requêtes SQL en
  minuscules. Un seul espace après l'opérateur de concaténation.
- **Langue** : français partout, accents obligatoires, guillemets français.
  Aucun caractère étranger dans un texte français.
- **Erreurs** : toute exception est journalisée avec son contexte, jamais
  renvoyée au navigateur. Un rapport sans mention d'échec est un rapport faux.
- **Tests** : cinq volets, compétence `tests`. Le lint par `php -l` vaut volet
  unitaire tant qu'aucun framework de test n'est autorisé ; les contrôles en
  SQL et en langage HTTP valent volets fonctionnel, non fonctionnel,
  intégration et non régression.
- **Versionnement** : une unité de travail par message, message court en
  français, `suivi.json` mis à jour dans le même message.
- **Documentation** : ce qui est décisionnel vit dans `CONTEXT.md`, ce qui est
  horodaté dans `suivi.json`, ce qui est opératoire dans `README.md`.

### 6.1 Charte graphique de l'interface

Normative pour toute tâche qui touche `app/public/assets/style.css`, le balisage
des pages ou la palette. Elle s'applique à l'application livrée, pas à
l'outillage du dépôt.

**Source et limite de la source.** L'inspiration est la capture « CargoWave —
Logistics Supply Chain Landing Page Design », Dribbble, référence 27770747.
Cette capture n'a pas pu être lue depuis cet environnement, la page se
rendant dans un navigateur. La seule base vérifiable est donc la description
publique du projet CargoWave, qui insiste sur la rapidité, la confiance, la
clarté opérationnelle, le professionnalisme d'entreprise, une hiérarchie
visuelle forte, des parcours fluides et une marque haut de gamme. **La palette
ci-dessous est une proposition, pas un relevé de la capture.** Si l'image est
déposée dans le dépôt, il faut reprendre la palette sur cette image et mettre
cette section à jour.

**Les sept intentions, dans cet ordre de priorité.** Vitesse, confiance,
clarté, hiérarchie,Fluidité, retenue, accessibilité. Quand deux règles
s'opposent, celle du haut gagne.

**Palette.** Tout passe par des variables CSS déclarées une seule fois sur
`:root`, jamais par une valeur littérale dans le reste de la feuille. Aucune
couleur n'est écrite ailleurs que dans ce bloc.

| Jeton | Valeur | Emploi |
| --- | --- | --- |
| `--encre` | `#0E1420` | Fond de page, bleu nuit industriel |
| `--surface` | `#161E2E` | Cartes, champs, panneaux |
| `--surface-haute` | `#1E293B` | Élévation : en-têtes de tableau, états survolés |
| `--trait` | `#26324A` | Bordures, séparateurs |
| `--texte` | `#E8EDF5` | Texte courant |
| `--texte-doux` | `#93A1B5` | Texte secondaire, légendes |
| `--accent` | `#FF6B1A` | Accent unique, orange signalétique |
| `--accent-froid` | `#4C8DFF` | Accent secondaire, liens et états actifs |
| `--succes` | `#2FBF71` | Validation réussie |
| `--erreur` | `#F0523E` | Message d'erreur |
| `--alerte` | `#F5B33C` | Avertissement |
| `--focus` | `#FFB27A` | Halo de focus clavier |

**Typographie.** Aucune police distante. Pile système, dans cet ordre :
`system-ui`, `-apple-system`, `Segoe UI`, `Helvetica Neue`, `Arial`, sans-serif.
Base `1rem` égale à `16px`. Échelle en `rem` selon un rapport de 1,25 :
`0.875`, `1`, `1.25`, `1.5`, `1.875`, `2.25`, `3`. Titres en graisse 700,
interlettrage `-0.01em` à partir de `1.5rem`. Chiffres tabulaires
`font-variant-numeric: tabular-nums` pour les dates et les compteurs, afin que
les colonnes s'alignent.

**Rythme.** Unité d'espacement de `0.5rem`. Échelle autorisée : `0.5`, `1`,
`1.5`, `2`, `3`, `4`, `6` rem, et rien d'autre. Largeur de lecture maximale
`42rem` pour un formulaire, `72rem` pour le tableau de bord. Marge latérale
`1rem` par défaut, `2rem` à partir de `48rem`. Mobile d'abord, sans exception.

**Composants.**
- Champ : fond `--surface`, bordure `--trait`, rayon `0.5rem`, hauteur
  `2.75rem`, texte `--texte`. Au focus, bordure `--accent` et halo
  `0 0 0 3px` en `accent` à vingt-cinq pour cent d'opacité.
- Bouton principal : fond `--accent`, texte `--encre`, graisse 600, rayon
  `0.5rem`, hauteur minimale `2.75rem`. Bouton secondaire : fond transparent,
  bordure `--accent`, texte `--accent`.
- Message d'erreur de champ : trois canaux simultanés, couleur `--erreur`,
  filet gauche `3px`, et glyphe d'avertissement en pseudo-élément. La couleur
  seule ne suffit jamais, l'information doit rester lisible sans la couleur.
- Message de session : bandeau à filet gauche `4px`, fond teinté à douze pour
  cent, texte en `--texte`.
- Carte : fond `--surface`, bordure `--trait`, rayon `0.75rem`, ombre portée
  unique `0 1px 2px` en noir à vingt pour cent.

**Interdits graphiques.** Aucune ressource distante : ni police, ni image, ni
icône, ni feuille de style secondaire, ni contenu tiers. Aucun effet de verre
dépoli. Aucune ombre portée au-delà de celle déclarée ci-dessus. Aucune
animation au-delà de `150ms`, limitée à `opacity` et `transform`, et neutralisée
sous `prefers-reduced-motion`. Aucune valeur en pixels dans la feuille : tout
en `rem`. Aucun dégradé décoratif, à l'exception d'un fond de page unique et
très sobre. Thème sombre uniquement dans cette version, sans bascule de thème.

**Accessibilité, seuils chiffrés.** Texte courant au moins `4.5:1` de contraste
sur son fond. Texte de grande taille au moins `3:1`. Cible de bouton au moins
`44rem` de côté, donc `2.75rem` de hauteur pour un bouton pleine largeur. Le
focus clavier doit rester visible sur fond sombre, jamais supprimé.

**Porte graphique.** La conformité à cette section fait partie de la porte 2
de revue, sur l'axe « conformité aux conventions ». Un écart de palette, une
couleur écrite en dur hors du bloc `:root`, une ressource distante ou une
valeur en pixels sont des constats bloquants.

## 7. Jalons

| Jalon | Livrable | Critère de fin |
| --- | --- | --- |
| J5 — Socle conteneur | `docker-compose.yml`, `app/Dockerfile`, `app/docker/apache-vhost.conf`, `.env.example` | `docker compose config` valide, deux services, aucun mot de passe en clair, ports 8080 et 3307 publiés |
| J6 — Base | `app/sql/init.sql` | Base `gestion_users` créée, table `users` conforme aux sept colonnes, montage dans le répertoire d'initialisation du conteneur |
| J7 — Noyau applicatif | `database.php`, `session.php`, `validator.php`, `User.php`, `AuthController.php`, `UserController.php`, `bootstrap.php` | Chaque fichier passe `php -l`, chaque règle de validation possède un test qui échoue quand elle est enfreinte |
| J8 — Pages | Les cinq pages, `.htaccess` | Les quatre adresses du CDC répondent, `/dashboard` sans session renvoie une redirection vers `/login` |
| J9 — Interface | `style.css`, `validation.js`, `app.js` | Aucune ressource externe, validation en temps réel, mise en page mobile avant mise en page large |
| J10 — Preuve | `scripts/verifier.sh`, `README.md` | Le script rejoue les dix critères du CDC §8 et renvoie 0 ; le README documente le démarrage, les variables et les points d'entrée |
| J11 — Charte graphique | `style.css`, balisage des pages | Palette intégralement déclarée sur `:root`, aucune couleur écrite ailleurs, aucun pixel dans la feuille, aucune ressource distante, contraste vérifié, mise en page mobile conservée |

## 8. Critères d'acceptation du projet

1. `docker compose up -d` puis l'appel de `scripts/verifier.sh` sur un dépôt
   propre renvoient 0, sans erreur en sortie.
2. Une inscription valide crée une ligne dans `users`, et la colonne `password`
   contient une empreinte bcrypt, jamais le mot de passe saisi.
3. Une connexion avec des identifiants exacts ouvre une session et mène au
   tableau de bord ; avec des identifiants faux, elle est refusée par un message
   qui ne distingue pas « compte inconnu » de « mot de passe faux ».
4. L'appel de `/dashboard` sans session valide est redirigé vers `/login`.
5. La déconnexion détruit la session : l'appel suivant de `/dashboard` est de
   nouveau redirigé, et le cookie de session a disparu.
6. Les sorties de base ne sont pas injectables : une chaîne de balisage
   enregistrée comme prénom est rendue échappée dans le tableau de bord.
7. Les données persistent après `docker compose restart`, et l'absence de
   réseau externe est vérifiée par l'absence de toute ressource distante dans
   les pages.

## 9. Risques et zones d'ombre

| Point | Pourquoi c'est un risque | Arbitrage retenu | Urgence |
| --- | --- | --- | --- |
| Le CDC ne fournit aucune commande de vérification, et renvoie PHPUnit et Cypress aux évolutions futures | Sans commande, aucune tâche ne peut produire la preuve exigée par la phase de test | `scripts/verifier.sh` en langage HTTP et SQL, décision consignée en D-012 | Haute — à confirmer |
| Le CDC ne fixe ni l'emplacement du code ni la place de `scripts/` | Le dépôt est un projet existant, pas une racine neuve | Racine du dépôt, `app/` et `scripts/`, décision D-011 | Moyenne — à confirmer |
| Le CDC annonce les adresses `/register` et `/login`, mais son Dockerfile ne déplace pas la racine web | Tel quel, le cahier des charges produit un `/public/` dans les adresses et rend `src/` téléchargeable | `DocumentRoot` sur `public`, décision D-013 | Haute — résolu, à confirmer |
| Contradiction interne du CDC sur la version de PHP (8.2 en §3.1, « ≥ 8.0 » en §6) | Le socle conteneur est figé sur cette valeur | 8.2, la version la plus précise | Basse |
| `scripts/verifier.sh` devra écrire un fichier `.env` de laboratoire | Un fichier `.env` dans l'arborescence peut être versionné par mégarde | `.env` inscrit au `.gitignore` dès J5, `.env.example` seul versionné, contrôle anti-fuite passé | Haute |
| Aucune automatisation au-delà du lint PHP | Une régression du code applicatif n'est vue qu'au moment du script | Accepté pour cette version, conformément au CDC §10 | Moyenne |
| La limitation de débit sur `/login` reste à faire | Une attaque par force brute reste possible | Hors périmètre, renvoyé aux évolutions du CDC §10 | Basse |
| La capture Dribbble qui motive la charte graphique n'est pas lisible depuis l'environnement du projet | La palette de la charte est une proposition, pas un relevé : elle engage le rendu de toute l'interface | Charte §6.1 posée sur la description publique du projet CargoWave, avec obligation de la reprendre si l'image est déposée. Recommandation de l'orchestrateur : déposer la capture dans `CDC/` pour figer la palette | Haute — à confirmer |

## 10. Traçabilité

| Exigence d'origine | Section | Suivi |
| --- | --- | --- |
| Système agentique, quatre agents, modèle unique | Précédente version, J1 à J4 | T-001 à T-011 |
| F1 — Inscription, validation double, unicité, bcrypt | CDC §2.1, §4.3, §4.4 | T-015, T-016, T-017, T-018, T-020 |
| F2 — Connexion, session régénérée, redirection | CDC §2.1, §5.2 | T-015, T-017, T-018, T-020 |
| F3 — Déconnexion, destruction de session, cookie | CDC §2.1, §5.3 | T-015, T-018, T-020 |
| F4 — Tableau de bord protégé | CDC §2.1, §5.1 | T-019, T-020 |
| Stack PHP 8.2, MySQL 8, Apache | CDC §3.1, §3.2, §4.2 | T-013 |
| Structure de fichiers imposée | CDC §3.3 | T-013 à T-021 |
| Schéma `users` aux sept colonnes | CDC §4.1 | T-014 |
| `docker-compose.yml` fonctionnel, `Dockerfile` | CDC §4.2, §7 | T-013 |
| Mesures de sécurité du tableau §4.3 | CDC §4.3 | T-015, T-016, T-017, T-018 |
| Règles de validation nom, prénom, courriel, mot de passe | CDC §4.4 | T-016 |
| Interface responsive, validation en temps réel, sans CDN | CDC §4.5, §6 | T-021 |
| Persistance après redémarrage | CDC §8 | T-013, T-022 |
| README de démarrage et documentation des points d'entrée | CDC §7 | T-022 |
| Critères d'acceptation du CDC §8 | CDC §8 | T-022 |
| Charte graphique de l'interface, source CargoWave | CONTEXT §6.1, J11 | T-023 |
