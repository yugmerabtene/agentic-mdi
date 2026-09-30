# Image de l'application du laboratoire : Apache avec PHP 8.2, sans framework.
#
# Reproductibilité : l'image de base est épinglée à la fois par son étiquette
# imposée par le contexte de travail et par son empreinte de manifeste. Deux
# constructions depuis le même état du dépôt produisent donc le même socle.
FROM php:8.2-apache@sha256:a5ca3797b19e8ba69905233f79fae6db191d2477f8e6d5194f2c05f01e22e104

# Extension de base de données imposée par le cahier des charges, et module de
# réécriture d'adresses nécessaire aux routes /register, /login, /dashboard.
# apt-get n'est pas utilisé : la seule extension compilée est pdo_mysql, et la
# suppression des listes de paquets évite de laisser une image démesurée.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Hôte Apache : racine web restreinte au répertoire public, règles .htaccess
# prises en compte, code applicatif et schéma explicitement interdits.
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Configuration d'exécution de l'interface : le régime d'erreurs est remplacé
# par un régime journalisé et non affiché, comme l'exige le cahier des charges.
# Les secrets ne sont jamais copiés dans l'image : ils arrivent par
# l'environnement au démarrage du conteneur.
COPY php.ini /usr/local/etc/php/conf.d/zz-laboratoire.ini

# Code de l'application. Le volume monté au démarrage masque ce contenu, ce qui
# rend l'édition immédiate sans reconstruction ; la copie sert quand même à
# garder l'image exploitable seule.
COPY . /var/www/html/

# Le serveur Apache conserve la racine pour lier le port 80, mais tous les
# processus de traitement des requêtes, y compris PHP, s'exécutent déjà sous
# l'utilisateur non privilégié www-data.
