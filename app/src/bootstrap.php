<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Point d'entrée unique de l'application.
 *
 * Ce fichier charge les couches procédurales, installe l'autoloadeur des
 * classes, et met en place la journalisation et l'affichage générique des
 * erreurs. Aucune page ne doit faire autre chose que charger ce fichier : la
 * liste des « require » n'existe qu'ici.
 *
 * Les pages web s'exécutent avec ce fichier ; les scripts de laboratoire
 * l'exécutent en ligne de commande. Dans les deux cas, une exception ne doit
 * jamais atteindre le navigateur.
 */

/**
 * Lit une variable d'environnement.
 *
 * Les identifiants de connexion ne sont jamais écrits en clair dans le code :
 * ils arrivent du conteneur par l'environnement, et cette fonction est le
 * seul point de lecture autorisé.
 *
 * @throws \RuntimeException si la variable est absente et qu'aucun défaut n'est fourni.
 */
function env(string $cle, ?string $defaut = null): string
{
    $valeur = getenv($cle);

    if ($valeur === false || $valeur === '') {
        if ($defaut !== null) {
            return $defaut;
        }

        throw new \RuntimeException(sprintf("Variable d'environnement absente : %s", $cle));
    }

    return $valeur;
}

/**
 * Écrit une ligne dans la sortie d'erreur du serveur.
 *
 * La sortie d'erreur est collectée par le serveur web et par Docker ; elle
 * n'est jamais renvoyée au navigateur. Un secret ne doit jamais être passé
 * ici : seul un contexte technique non sensible est journalisé.
 */
function journaliser(string $message, ?Throwable $exception = null): void
{
    $ligne = sprintf('[%s] %s', gmdate('c'), $message);

    if ($exception instanceof Throwable) {
        $ligne .= sprintf(
            ' | %s : %s (%s ligne %d)',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
    }

    error_log($ligne);
}

/**
 * Échappe une valeur destinée au navigateur.
 *
 * Les sorties de base sont échappées à la dernière étape, jamais à la saisie.
 * ENT_SUBSTITUTE évite qu'une suite d'octets invalide renvoie une chaîne vide.
 */
function echapper(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Envoie le navigateur vers une autre adresse de l'application.
 *
 * C'est la seule porte de sortie d'un parcours, et elle vit ici parce que ce
 * fichier est le point d'entrée commun : les contrôleurs, les pages et les
 * scripts de laboratoire en ont tous besoin, et aucun d'eux ne doit dépendre
 * d'un contrôleur pour cela. La réponse ne contient rien, ce qui interdit au
 * navigateur de conserver la page traitée et de la lui rendre par la touche de
 * retour. Un chemin relatif est refusé et ramené à la racine, pour qu'aucune
 * adresse extérieure ne puisse être atteinte par ce point d'entrée.
 */
function rediriger_vers(string $chemin): never
{
    $cible = str_starts_with($chemin, '/') ? $chemin : '/';

    if (!headers_sent()) {
        http_response_code(302);
        header(sprintf('Location: %s', $cible));
        header('Cache-Control: no-store');
    }

    exit;
}

/**
 * Renvoie au navigateur une page d'erreur neutre, puis termine le script.
 *
 * La page ne contient ni message de serveur, ni identifiant, ni trace de
 * pile : le détail est dans le journal, pas dans la réponse.
 */
function page_erreur(int $code, string $titre, string $message): never
{
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }

    printf(
        "<!DOCTYPE html>\n<html lang=\"fr\">\n<head>\n<meta charset=\"utf-8\">\n"
        . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . "<title>%s</title>\n</head>\n<body>\n<h1>%s</h1>\n<p>%s</p>\n</body>\n</html>\n",
        echapper($titre),
        echapper($titre),
        echapper($message)
    );

    exit;
}

/**
 * Affiche une page d'erreur interne générique et journalise l'exception.
 */
function erreur_interne(Throwable $exception): never
{
    journaliser('Exception non rattrapée', $exception);
    page_erreur(
        500,
        'Erreur interne',
        'Une erreur technique est survenue. L’administrateur du laboratoire a été informé.'
    );
}

// Chargement des couches procédurales. Ces trois fichiers s'appuient sur les
// fonctions env() et journaliser() définies ci-dessus, qui sont déjà
// disponibles à ce point du fichier.
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/database.php';

// Autoloadeur de l'espace de noms du projet : App\User est servi par
// src/User.php. Le nom de classe suffit donc à trouver le fichier, sans
// nouvelle liste de « require » dans les pages.
spl_autoload_register(static function (string $classe): void {
    $prefixe = 'App\\';

    if (!str_starts_with($classe, $prefixe)) {
        return;
    }

    $relatif = str_replace('\\', DIRECTORY_SEPARATOR, substr($classe, strlen($prefixe)));
    $fichier = __DIR__ . DIRECTORY_SEPARATOR . $relatif . '.php';

    if (is_file($fichier)) {
        require_once $fichier;
    }
});

// Toute exception non rattrapée finit dans le journal, et le navigateur ne
// reçoit que la page neutre.
set_exception_handler(static function (Throwable $exception): void {
    erreur_interne($exception);
});

// Les avertissements et les erreurs sont transformés en exception, donc
// journalisés avec leur contexte, au lieu de disparaître dans la sortie
// d'erreur. Les remarques et les dépréciations sont laissées au traitement
// natif, qui les journalise sans interrompre la page.
set_error_handler(static function (int $niveau, string $message, string $fichier, int $ligne): bool {
    if ((error_reporting() & $niveau) === 0) {
        return false;
    }

    $niveaux_retenus = E_WARNING | E_USER_WARNING | E_RECOVERABLE_ERROR | E_ERROR | E_USER_ERROR | E_PARSE;

    if (($niveau & $niveaux_retenus) === 0) {
        return false;
    }

    throw new \ErrorException($message, 0, $niveau, $fichier, $ligne);
});

// Ouverture de la session ici, et nulle part ailleurs. Le réglage des
// cookies de session est refusé dès que la réponse a commencé à être écrite :
// la session s'ouvre donc avant tout affichage, ce qui garantit que HttpOnly,
// SameSite et la durée de vie sont bien posés.
demarrer_session();

