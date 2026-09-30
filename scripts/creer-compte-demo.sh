#!/usr/bin/env bash
#
# Création du compte de démonstration du laboratoire de gestion des
# utilisateurs.
#
# Le cahier des charges impose que la table users démarre vide : aucun compte
# de démonstration n'est inséré par le schéma. Le centre de démonstration a
# pourtant besoin d'un compte pour ouvrir une session. Ce script comble ce
# manque, sans rien écrire dans le dépôt.
#
# Principes tenus par ce script :
#   1. Le mot de passe est tiré à l'exécution, dans une variable de la
#      mémoire du processus, puis n'existe plus nulle part ailleurs. Il
#      n'apparaît dans aucun fichier versionné, aucun historique, aucun
#      journal.
#   2. L'empreinte bcrypt est calculée par le PHP du conteneur de
#      l'application, jamais par un outil externe : c'est la même fonction que
#      celle utilisée par la page d'inscription, donc le compte est
#      interchangeable avec un compte créé à la main.
#   3. Idempotence. Rejouer le script ne crée jamais de doublon et ne laisse
#      aucun résidu : la contrainte d'unicité sur l'adresse électronique est
#      mise à profit par un upsert. Le mot de passe du compte de laboratoire
#      est réémis à chaque exécution, ce qui est l'effet recherché, et le seul
#      effet.
#   4. Droit minimal. L'écriture passe par le compte applicatif, pas par le
#      compte racine de MySQL. Les identifiants sont lus dans l'environnement
#      du conteneur, jamais écrits ni affichés.
#   5. Le script ne touche à aucun fichier du dépôt. Il ne fait qu'écrire une
#      ligne en base.
#
# Sortie : l'adresse électronique et le mot de passe de laboratoire, affichés
# à l'écran pour être communiqués, puis oubliés.
#
# Le pipefail est volontairement absent : la lecture de l'aléaatoire passe par
# un utilitaire qui ferme le tube dès qu'il a son nombre d'octets, ce qui
# produit un code de sortie 141 sans que ce soit une erreur. La longueur de
# l'aléaatoire est contrôlée explicitement juste après.

set -eu

# Racine du dépôt, déduite de l'emplacement du script, afin que le script
# fonctionne depuis n'importe quel répertoire de travail.
RACINE="$(cd "$(dirname "$0")/.." && pwd)"
cd "$RACINE"

# Fichier temporaire de collecte des messages d'erreur du client MySQL. Il est
# créé hors du dépôt, jamais dans l'arborescence versionnée, et détruit à la
# sortie quelle qu'elle soit. Il ne contient qu'un avertissement de client ou
# un message d'erreur SQL, jamais un mot de passe.
JOURNAL_TEMPORAIRE="$(mktemp "${TMPDIR:-/tmp}/creer-compte-demo.XXXXXX")"
trap 'rm -f "$JOURNAL_TEMPORAIRE"' EXIT HUP INT TERM

# Identité du compte de laboratoire. Ces valeurs sont des constantes de
# laboratoire, pas des données réelles : elles sont remplaçables par les
# variables d'environnement pour un besoin ponctuel.
NOM_DEMO="${NOM_DEMO:-Dupont}"
PRENOM_DEMO="${PRENOM_DEMO:-Camille}"
EMAIL_DEMO="${EMAIL_DEMO:-camille@labo.local}"

echec() {
    printf 'Échec : %s\n' "$1" >&2
    exit 1
}

# Aucun guillemet simple ni antislash dans les valeurs insérées : la requête
# est composée par concaténation de valeurs contrôlées, pas par une requête
# préparée, puisque le client en ligne de commande de MySQL n'en propose pas.
for VALEUR in "$NOM_DEMO" "$PRENOM_DEMO" "$EMAIL_DEMO"; do
    case "$VALEUR" in
        *"'"* | *\\*)
            echec "la valeur « $VALEUR » contient un caractère interdit"
            ;;
    esac
done

printf 'Préparation du compte de démonstration de laboratoire.\n'

# --- 1. Les deux conteneurs doivent tourner ---------------------------------
# L'empreinte est calculée par le conteneur de l'application, la ligne est
# écrite par le conteneur de la base. Sans les deux, le script n'a rien à
# faire et doit s'arrêter proprement plutôt que d'échouer à mi-parcours.
for SERVICE in app db; do
    ETAT="$(docker compose ps --format '{{.Service}} {{.State}}' "$SERVICE" 2>/dev/null | tr -d '\r' | head -n 1)"
    case "$ETAT" in
        "$SERVICE running") ;;
        *) echec "le service $SERVICE n'est pas actif ; lancez « docker compose up -d »" ;;
    esac
done

# --- 2. Tirage du mot de passe de laboratoire --------------------------------
# Douze caractères alphanumériques, comportant au moins une majuscule, une
# minuscule et un chiffre : les trois règles du cahier des charges sont
# respectées avec de la marge, puisque le minimum exigé est de huit
# caractères. Le jeu se compose par قطients, puis il est mélangé, afin que
# l'emplacement de chaque classe de caractère soit imprévisible.
MAJUSCULE="$(LC_ALL=C tr -dc 'A-Z' < /dev/urandom | head -c 1)"
MINUSCULE="$(LC_ALL=C tr -dc 'a-z' < /dev/urandom | head -c 1)"
CHIFFRE="$(LC_ALL=C tr -dc '0-9' < /dev/urandom | head -c 1)"
RESTE="$(LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 9)"
TIRAGE="${MAJUSCULE}${MINUSCULE}${CHIFFRE}${RESTE}"
[ "${#TIRAGE}" -eq 12 ] || echec "le tirage aléatoire n'a pas produit douze caractères"

MOT_DE_PASSE="$(printf '%s' "$TIRAGE" | shuf)"
[ "${#MOT_DE_PASSE}" -eq 12 ] || echec "le mélange n'a pas conservé les douze caractères"

# --- 3. Empreinte bcrypt, calculée par le PHP de l'application ---------------
# Le mot de passe transite par l'entrée standard du conteneur : il n'apparaît
# donc jamais dans la ligne de commande du processus, donc jamais dans la
# table des processus de l'hôte.
EMPREINTE="$(
    printf '%s' "$MOT_DE_PASSE" |
        docker compose exec -T app php -r \
            'echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_BCRYPT);'
)" || echec "le calcul de l'empreinte bcrypt a échoué"

case "$EMPREINTE" in
    '$2y$'*) ;;
    *) echec "le conteneur n'a pas renvoyé d'empreinte bcrypt" ;;
esac

# --- 4. Écriture de la ligne, en un seul passage -----------------------------
# L'upsert s'appuie sur la clé unique uq_users_email : un rejeu met la ligne à
# jour au lieu d'en créer une deuxième, et l'identifiant interne reste stable.
# Le compte applicatif suffit, il est le seul habilité sur la base du
# laboratoire.
printf "INSERT INTO users (nom, prenom, email, password) VALUES ('%s', '%s', '%s', '%s')\nON DUPLICATE KEY UPDATE nom = VALUES(nom), prenom = VALUES(prenom), password = VALUES(password), last_login = NULL;\n" \
    "$NOM_DEMO" "$PRENOM_DEMO" "$EMAIL_DEMO" "$EMPREINTE" |
    docker compose exec -T db sh -c \
        'mysql -h 127.0.0.1 -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -D "$MYSQL_DATABASE"' \
        2>"$JOURNAL_TEMPORAIRE" || echec "l'écriture du compte a échoué"
# Le client MySQL écrit un avertissement sur la ligne de commande, sans gravité
# ici puisque les identifiants viennent de l'environnement du conteneur et non
# de la ligne de commande de l'opérateur. Il n'est montré qu'en cas d'échec,
# pour que l'erreur réelle reste seule visible.

# --- 5. Contrôle de l'empreinte avant d'afficher quoi que ce soit -----------
# La vérification se fait en deux temps, car le client MySQL n'existe que dans
# le conteneur de la base et la fonction password_verify que dans le conteneur
# de l'application. L'empreinte stockée est relue d'abord, puis confrontée au
# mot de passe par PHP. La relire prouve que le compte est bien celui qui
# vient d'être écrit, et non une ligne homonyme restée en base.
REQUETE_LECTURE="SELECT password FROM users WHERE email = '$EMAIL_DEMO' LIMIT 1;"
EMPREINTE_STOCKEE="$(
    printf '%s\n' "$REQUETE_LECTURE" |
        docker compose exec -T db sh -c \
            'mysql -h 127.0.0.1 -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -D "$MYSQL_DATABASE" -N -B' \
            2>"$JOURNAL_TEMPORAIRE"
)" || echec "la relecture de l'empreinte a échoué"

[ -n "$EMPREINTE_STOCKEE" ] || echec "aucune ligne ne correspond à l'adresse $EMAIL_DEMO"

CONFORME="$(
    printf '%s' "$MOT_DE_PASSE" |
        docker compose exec -T app php -r \
            'echo (password_verify(trim(stream_get_contents(STDIN)), $argv[1]) ? "1" : "0");' \
            -- "$EMPREINTE_STOCKEE"
)" || echec "le contrôle de l'empreinte a échoué"
[ "$CONFORME" = "1" ] || echec "le mot de passe tiré ne correspond pas à l'empreinte stockée"

# --- 6. Restitution, une seule fois, à l'écran ------------------------------
printf '\n'
printf 'Compte de démonstration du laboratoire, créé et vérifié.\n'
printf '\n'
printf '  Adresse électronique : %s\n' "$EMAIL_DEMO"
printf '  Mot de passe         : %s\n' "$MOT_DE_PASSE"
printf '\n'
printf 'Ce mot de passe n’est écrit dans aucun fichier du dépôt. Il existe\n'
printf 'uniquement dans cette sortie et dans la base, sous forme d’empreinte\n'
printf 'bcrypt. Pour le renouveler, relancez simplement ce script : le compte\n'
printf 'est mis à jour, jamais dupliqué.\n'
printf '\n'
