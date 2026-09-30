<?php

declare(strict_types=1);

/**
 * Tableau de bord protégé.
 *
 * La page ne rend rien avant que le contrôleur utilisateur n'ait confirmé
 * l'existence d'une session et l'existence du compte en base. Un appel sans
 * session reçoit un code 302 vers la connexion : il ne reçoit ni page, ni
 * lien, ni indice sur la présence d'un compte.
 *
 * L'identité affichée vient de la base et non de la session. La colonne
 * password n'est jamais rendue : le contrôleur ne fournit que les champs
 * d'affichage, déjà échappés.
 */

use App\AuthController;
use App\User;
use App\UserController;

use function App\echapper;

require_once dirname(__DIR__) . '/src/bootstrap.php';

$ligne = UserController::exiger_utilisateur();

// pour_affichage() est l'unique porte de sortie du modèle vers le navigateur :
// la colonne password en est absente et chaque valeur est déjà échappée. Une
// seconde passe d'échappement ici rendrait le texte illisible — une adresse
// contenant « & » deviendrait « &amp; » dans la page — sans rien ajouter à la
// sécurité.
$affichage = User::pour_affichage($ligne);
$succes = AuthController::lire_succes();

$derniere_connexion = UserController::formater_horodatage(
    isset($ligne['last_login']) ? (string) $ligne['last_login'] : null
);

/**
 * Le document est rendu dans un tampon : la feuille de style et les deux
 * scripts sont insérés ensuite, par substitution sur le document complet, sans
 * que les fonctions d'en-tête et de pied de page aient à changer.
 */
ob_start();

UserController::entete_page('Tableau de bord', '/dashboard');

if ($succes !== null) {
    printf("<p class=\"message-succes\" role=\"status\">%s</p>", echapper($succes));
}

printf(
    <<<HTML
<section class="carte">
<h1 class="titre-page">Votre compte</h1>
<p class="accueil-connecte">Bonjour %s %s, vous êtes connecté.</p>
<dl class="fiche">
<dt class="fiche-libelle">Prénom</dt>
<dd class="fiche-valeur">%s</dd>
<dt class="fiche-libelle">Nom</dt>
<dd class="fiche-valeur">%s</dd>
<dt class="fiche-libelle">Adresse électronique</dt>
<dd class="fiche-valeur">%s</dd>
<dt class="fiche-libelle">Dernière connexion</dt>
<dd class="fiche-valeur">%s</dd>
</dl>
</section>
HTML,
    $affichage['prenom'],
    $affichage['nom'],
    $affichage['prenom'],
    $affichage['nom'],
    $affichage['email'],
    echapper($derniere_connexion)
);

UserController::pied_de_page();

/**
 * Insère la feuille de style dans l'en-tête et les deux scripts avant la
 * fermeture du corps, sur le document rendu en entier.
 */
$document = str_replace(
    '</head>',
    "<link rel=\"stylesheet\" href=\"/assets/style.css\">\n</head>",
    ob_get_clean()
);

$document = str_replace(
    '</body>',
    "<script src=\"/assets/validation.js\" defer></script>\n"
    . "<script src=\"/assets/app.js\" defer></script>\n</body>",
    $document
);

print $document;
