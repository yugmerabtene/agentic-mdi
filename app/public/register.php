<?php

declare(strict_types=1);

/**
 * Formulaire d'inscription.
 *
 * Ce fichier ne rend la page qu'en affichage. Toute soumission part vers le
 * contrôleur d'authentification, qui valide, crée le compte, puis change
 * d'adresse : le navigateur ne reçoit jamais le résultat d'un traitement.
 */

use App\AuthController;
use App\UserController;

use function App\echapper;
use function App\jeton_csrf;
use function App\lire_erreurs;

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    AuthController::inscrire();
}

$erreurs = lire_erreurs();
$saisie = AuthController::lire_saisie();
$succes = AuthController::lire_succes();

/**
 * Rend le message d'erreur d'un champ, et rien s'il n'y en a pas.
 *
 * Le message vient de la validation, jamais du navigateur : il est échappé une
 * seconde fois ici, par principe de défense.
 */
$message_erreur = static function (string $champ) use ($erreurs): string {
    $message = $erreurs[$champ] ?? null;

    if (!is_string($message) || $message === '') {
        return '';
    }

    return sprintf(
        "<p class=\"champ-erreur\" id=\"erreur-%s\" role=\"alert\">%s</p>",
        echapper($champ),
        echapper($message)
    );
};

UserController::entete_page('Inscription', '/register');

if ($succes !== null) {
    printf("<p class=\"message-succes\" role=\"status\">%s</p>", echapper($succes));
}

if ($erreurs !== []) {
    printf(
        "<p class=\"message-erreur\" role=\"alert\">Le formulaire comporte %d champ(s) à corriger.</p>",
        count($erreurs)
    );
}

$balisage = <<<HTML
<section class="carte">
<h1 class="titre-page">Créer un compte</h1>
<form class="formulaire" method="post" action="/register" novalidate>
<input type="hidden" name="jeton_csrf" value="{{jeton}}">

<div class="champ">
<label class="etiquette" for="nom">Nom</label>
<input class="saisie" type="text" id="nom" name="nom" value="{{nom}}" autocomplete="family-name" maxlength="50" required aria-describedby="erreur-nom">
{{erreur_nom}}
</div>

<div class="champ">
<label class="etiquette" for="prenom">Prénom</label>
<input class="saisie" type="text" id="prenom" name="prenom" value="{{prenom}}" autocomplete="given-name" maxlength="50" required aria-describedby="erreur-prenom">
{{erreur_prenom}}
</div>

<div class="champ">
<label class="etiquette" for="email">Adresse électronique</label>
<input class="saisie" type="email" id="email" name="email" value="{{email}}" autocomplete="email" maxlength="255" required aria-describedby="erreur-email">
{{erreur_email}}
</div>

<div class="champ">
<label class="etiquette" for="password">Mot de passe</label>
<input class="saisie" type="password" id="password" name="password" autocomplete="new-password" required aria-describedby="aide-password erreur-password">
<p class="aide" id="aide-password">Huit caractères au moins, dont une majuscule, une minuscule et un chiffre.</p>
{{erreur_password}}
</div>

<div class="champ">
<label class="etiquette" for="password_confirm">Confirmation du mot de passe</label>
<input class="saisie" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required aria-describedby="erreur-password_confirm">
{{erreur_password_confirm}}
</div>

<button class="bouton bouton-principal" type="submit">Créer mon compte</button>
</form>
<p class="lien-secondaire">Un compte existe déjà ? <a class="lien" href="/login">Se connecter</a>.</p>
</section>
HTML;

echo strtr($balisage, [
    '{{jeton}}' => echapper(jeton_csrf()),
    '{{nom}}' => echapper($saisie['nom'] ?? ''),
    '{{prenom}}' => echapper($saisie['prenom'] ?? ''),
    '{{email}}' => echapper($saisie['email'] ?? ''),
    '{{erreur_nom}}' => $message_erreur('nom'),
    '{{erreur_prenom}}' => $message_erreur('prenom'),
    '{{erreur_email}}' => $message_erreur('email'),
    '{{erreur_password}}' => $message_erreur('password'),
    '{{erreur_password_confirm}}' => $message_erreur('password_confirm'),
]);

UserController::pied_de_page();
