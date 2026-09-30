<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

/**
 * Accès à la base de données.
 *
 * Ce fichier suppose que bootstrap.php a été chargé : il fournit env(),
 * journaliser() et page_erreur(). Il n'ouvre jamais une seconde connexion
 * pendant une même requête.
 */

/**
 * Renvoie la connexion PDO de la requête courante.
 *
 * La connexion est ouverte une seule fois puis conservée dans une variable
 * statique : la réutilisation est un gain de temps, et le mode exception
 * garantit qu'aucune erreur n'est renvoyée silencieusement.
 *
 * @throws \RuntimeException si la connexion est impossible.
 */
function base_de_donnees(): PDO
{
    static $connexion = null;

    if ($connexion instanceof PDO) {
        return $connexion;
    }

    // Valeurs de repli pour le journal : elles servent à décrire une
    // connexion avortée avant même que l'environnement soit complet.
    $hote = '(absent)';
    $port = '(absent)';
    $nom = '(absent)';
    $utilisateur = '(absent)';

    try {
        $hote = env('DB_HOST');
        $port = env('DB_PORT', '3306');
        $nom = env('DB_NAME');
        $utilisateur = env('DB_USER');
        $motDePasse = env('DB_PASS');

        $connexion = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $hote, $port, $nom),
            $utilisateur,
            $motDePasse,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );
    } catch (Throwable $exception) {
        journaliser(
            sprintf(
                'Connexion à la base impossible (hôte %s, port %s, base %s, utilisateur %s)',
                $hote,
                $port,
                $nom,
                $utilisateur
            ),
            $exception
        );

        throw new \RuntimeException('La base de données est indisponible.', 0, $exception);
    }

    return $connexion;
}
