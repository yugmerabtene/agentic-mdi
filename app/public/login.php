<?php

declare(strict_types=1);

/**
 * Formulaire de connexion.
 *
 * Comme l'inscription, la page ne rend qu'en affichage : la soumission est
 * confiée au contrôleur d'authentification, qui ne renvoie qu'une redirection
 * ou un message unique, jamais l'indication qu'un compte existe ou non.
 */

use App\AuthController;
use App\UserController;

use function App\echapper;
use function App\jeton_csrf;
use function App\lire_erreurs;

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    AuthController::connecter();
}

$erreurs = lire_erreurs();
$saisie = AuthController::lire_saisie();
$succes = AuthController::lire_succes();

UserController::entete_page('Connexion', '/login');

if ($succes !== null) {
    printf("<p class=\"message-succes\" role=\"status\">%s</p>", echapper($succes));
}

$refus = $erreurs['email'] ?? null;

if (is_string($refus) && $refus !== '') {
    printf(
        "<p class=\"message-erreur\" id=\"erreur-email\" role=\"alert\">%s</p>",
        echapper($refus)
    );
}

printf(
    <<<HTML
<section class="carte">
<h1 class="titre-page">Se connecter</h1>
<form class="formulaire" method="post" action="/login" novalidate>
<input type="hidden" name="jeton_csrf" value="%s">

<div class="champ">
<label class="etiquette" for="email">Adresse électronique</label>
<input class="saisie" type="email" id="email" name="email" value="%s" autocomplete="email" maxlength="255" required autofocus>
</div>

<div class="champ">
<label class="etiquette" for="password">Mot de passe</label>
<input class="saisie" type="password" id="password" name="password" autocomplete="current-password" required>
</div>

<button class="bouton bouton-principal" type="submit">Se connecter</button>
</form>
<p class="lien-secondaire">Pas encore de compte ? <a class="lien" href="/register">Créer un compte</a>.</p>
</section>
HTML,
    echapper(jeton_csrf()),
    echapper($saisie['email'] ?? '')
);

UserController::pied_de_page();
