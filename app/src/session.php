<?php

declare(strict_types=1);

namespace App;

/**
 * Session durcie, jeton CSRF et messages de formulaire.
 *
 * Ce fichier suppose que bootstrap.php a été chargé : il fournit env(),
 * journaliser() et page_erreur().
 */

/** Durée de vie d'une session, en secondes. La session expire sans renouvellement automatique. */
const DUREE_VIE_SESSION = 1800;

/** Nom du cookie de session, distinct du nom par défaut de PHP. */
const NOM_COOKIE_SESSION = 'gestion_users_session';

/** Clé de session qui porte les messages d'erreur des formulaires. */
const CLE_ERREURS = 'erreurs_formulaire';

/** Clé de session qui porte le jeton CSRF. */
const CLE_JETON_CSRF = 'jeton_csrf';

/**
 * Indique si la requête courante arrive en HTTPS.
 *
 * Le marqueur `Secure` du cookie ne doit être posé que sur une connexion
 * chiffrée : le poser sur une connexion simple empêcherait le cookie de
 * revenir du tout.
 */
function requete_en_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    return ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/**
 * Ouvre la session avec des réglages sûrs, puis régénère l'identifiant.
 *
 * `HttpOnly` retire le cookie à JavaScript, `SameSite=Strict` empêche
 * l'envoi du cookie depuis un site tiers, `Secure` s'ajoute en HTTPS,
 * `use_strict_mode` refuse un identifiant inventé par le client, et
 * `gc_maxlifetime` borne la durée de vie.
 *
 * @throws \RuntimeException si les sessions sont désactivées sur le serveur.
 */
function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (session_status() === PHP_SESSION_DISABLED) {
        journaliser('Les sessions sont désactivées sur ce serveur.');
        throw new \RuntimeException('Les sessions sont indisponibles.');
    }

    // Un réglage de session refusé faute d'être posé à temps rendrait les
    // cookies moins stricts sans bruit apparent : il est donc journalisé, et le
    // serveur refusera l'ajustement par un avertissement qui remonte en
    // exception. Les pages chargent bootstrap.php, qui ouvre la session avant
    // tout affichage ; ce cas signale une page qui écrit trop tôt.
    if (headers_sent()) {
        journaliser('La session est ouverte après le début de la réponse : les réglages de cookie seront refusés.');
    }

    $cookie_present = isset($_COOKIE[NOM_COOKIE_SESSION]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', requete_en_https() ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.gc_maxlifetime', (string) DUREE_VIE_SESSION);

    session_name(NOM_COOKIE_SESSION);
    session_start();

    // Un identifiant reçu sans cookie correspondant n'est pas réutilisé : il
    // est régénéré avant le premier échange de données.
    if (!$cookie_present) {
        session_regenerate_id(true);
    }
}

/**
 * Régénère l'identifiant de session en conservant les données.
 *
 * Cette fonction est appelée à la connexion, pour qu'une session ouverte
 * avant l'authentification ne survive pas à celle-ci.
 */
function regenerer_session(): void
{
    demarrer_session();
    session_regenerate_id(true);
}

/**
 * Renvoie le jeton CSRF de la session, en le créant au premier appel.
 *
 * Le jeton est tiré par random_bytes(), donc non devinable, et vit en
 * session : il n'est jamais reçu d'une autre origine.
 */
function jeton_csrf(): string
{
    demarrer_session();

    $jeton = $_SESSION[CLE_JETON_CSRF] ?? null;

    if (!is_string($jeton) || $jeton === '') {
        $jeton = bin2hex(random_bytes(32));
        $_SESSION[CLE_JETON_CSRF] = $jeton;
    }

    return $jeton;
}

/**
 * Compare le jeton reçu au jeton de session, à temps constant.
 *
 * Une soumission dont le jeton est absent, vide ou différent est refusée.
 */
function jeton_csrf_valide(mixed $soumis): bool
{
    demarrer_session();

    $attendu = $_SESSION[CLE_JETON_CSRF] ?? null;

    if (!is_string($attendu) || $attendu === '' || !is_string($soumis) || $soumis === '') {
        return false;
    }

    return hash_equals($attendu, $soumis);
}

/**
 * Vérifie le jeton d'un formulaire et refuse la soumission par un code 403.
 *
 * Un jeton absent ou différent est refusé avant toute action : ni le
 * navigateur ni le journal ne reçoivent le jeton lui-même.
 */
function exiger_csrf(mixed $soumis = null): void
{
    if ($soumis === null) {
        $soumis = $_POST[CLE_JETON_CSRF] ?? null;
    }

    if (!jeton_csrf_valide($soumis)) {
        journaliser('Jeton CSRF absent ou invalide : soumission refusée.');
        page_erreur(
            403,
            'Requête refusée',
            'Le formulaire a expiré ou son origine n’a pas pu être confirmée. Rechargez la page et réessayez.'
        );
    }
}

/**
 * Dépose des messages d'erreur indexés par nom de champ.
 */
function definir_erreurs(array $erreurs): void
{
    demarrer_session();

    $propres = [];

    foreach ($erreurs as $champ => $message) {
        $propres[(string) $champ] = (string) $message;
    }

    $_SESSION[CLE_ERREURS] = $propres;
}

/**
 * Ajoute un message d'erreur à un champ, sans effacer les autres.
 */
function ajouter_erreur(string $champ, string $message): void
{
    demarrer_session();

    $existantes = $_SESSION[CLE_ERREURS] ?? [];

    if (!is_array($existantes)) {
        $existantes = [];
    }

    $existantes[$champ] = $message;
    $_SESSION[CLE_ERREURS] = $existantes;
}

/**
 * Renvoie les messages d'erreur puis les efface.
 *
 * Un message ne doit pas survivre d'une page à l'autre : il est lié à la
 * redirection qui suit la soumission, pas à la session entière.
 */
function lire_erreurs(): array
{
    demarrer_session();

    $erreurs = $_SESSION[CLE_ERREURS] ?? [];
    unset($_SESSION[CLE_ERREURS]);

    if (!is_array($erreurs)) {
        return [];
    }

    $propres = [];

    foreach ($erreurs as $champ => $message) {
        $propres[(string) $champ] = (string) $message;
    }

    return $propres;
}

/**
 * Dépose l'utilisateur connecté en session, après régénération de l'identifiant.
 */
function ouvrir_session_utilisateur(array $utilisateur): void
{
    regenerer_session();
    $_SESSION['utilisateur'] = [
        'id' => (int) ($utilisateur['id'] ?? 0),
        'nom' => (string) ($utilisateur['nom'] ?? ''),
        'prenom' => (string) ($utilisateur['prenom'] ?? ''),
        'email' => (string) ($utilisateur['email'] ?? ''),
    ];
}

/**
 * Renvoie l'utilisateur connecté, ou une valeur nulle s'il n'y en a pas.
 */
function utilisateur_courant(): ?array
{
    demarrer_session();

    $utilisateur = $_SESSION['utilisateur'] ?? null;

    if (!is_array($utilisateur) || !isset($utilisateur['id']) || (int) $utilisateur['id'] <= 0) {
        return null;
    }

    return [
        'id' => (int) $utilisateur['id'],
        'nom' => (string) ($utilisateur['nom'] ?? ''),
        'prenom' => (string) ($utilisateur['prenom'] ?? ''),
        'email' => (string) ($utilisateur['email'] ?? ''),
    ];
}

/**
 * Détruit la session et supprime le cookie côté client.
 *
 * Le cookie est renvoyé avec une date d'expiration dans le passé et les mêmes
 * attributs que ceux de l'émission initiale : sans cela, le navigateur
 * conserverait une copie orpheline.
 */
function detruire_session(): void
{
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies') && PHP_SAPI !== 'cli') {
        $parametres = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parametres['path'],
            'domain' => $parametres['domain'],
            'secure' => $parametres['secure'],
            'httponly' => $parametres['httponly'],
            'samesite' => $parametres['samesite'] !== '' ? $parametres['samesite'] : 'Strict',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
