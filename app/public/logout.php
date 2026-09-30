<?php

declare(strict_types=1);

/**
 * Point de sortie de déconnexion.
 *
 * La déconnexion est une action, pas une page : elle n'est acceptée qu'en
 * soumission vérifiée par le jeton CSRF. Un appel direct par une adresse, donc
 * sans jeton, est refusé par un code 405 — sauf si l'appel est déjà une
 * soumission, auquel cas c'est le jeton absent qui parle, en 403.
 *
 * Cette page ne rend aucun contenu : elle aboutit toujours à un changement
 * d'adresse, vers la connexion.
 */

use App\AuthController;
use App\UserController;

use function App\page_erreur;

require_once dirname(__DIR__) . '/src/bootstrap.php';

$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($methode !== 'POST') {
    header('Allow: POST');

    page_erreur(
        405,
        'Méthode non autorisée',
        'La déconnexion passe par le formulaire du tableau de bord, jamais par une adresse saisie à la main.'
    );
}

AuthController::deconnecter();
