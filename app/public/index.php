<?php

declare(strict_types=1);

/**
 * Page d'accueil : elle ne sert aucun contenu propre.
 *
 * L'adresse racine mène à la connexion ou au tableau de bord, selon qu'une
 * identité est portée ou non par la session. Le choix est fait ici, dans le
 * script, et non par une ressource de navigateur.
 */

use App\UserController;

use function App\rediriger_vers;

require_once dirname(__DIR__) . '/src/bootstrap.php';

rediriger_vers(UserController::session_ouverte() ? '/dashboard' : '/login');
