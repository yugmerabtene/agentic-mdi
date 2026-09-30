<?php

declare(strict_types=1);

namespace App;

/**
 * Validation des champs de formulaire, côté serveur.
 *
 * Ce fichier ne dépend d'aucune autre couche : ni base, ni session, ni
 * navigateur. Il peut donc être testé seul, et il ne lève jamais d'exception
 * pour une saisie incorrecte — il renvoie un message.
 *
 * Les messages sont destinés au lecteur du formulaire. Ils disent qu'un champ
 * est refusé, sans énoncer la règle complète : une aide qui énumère les
 * conditionsExactes d'un mot de passe facilitate sa reconstruction.
 */

/** Longueur minimale et maximale d'un nom ou d'un prénom. */
const LONGUEUR_NOM_MIN = 2;
const LONGUEUR_NOM_MAX = 50;

/** Longueur minimale d'un mot de passe. */
const LONGUEUR_MOT_DE_PASSE_MIN = 8;

/** Champs du formulaire d'inscription, dans l'ordre de leur affichage. */
const CHAMPS_INSCRIPTION = ['nom', 'prenom', 'email', 'password', 'password_confirm'];

/**
 * Motif accepté pour un nom ou un prénom : lettres de tout alphabet, espaces,
 * tirets et apostrophes droches ou typographiques.
 *
 * \p{L} accepte les lettres accentuées ; \p{M} accepte les marques de
 * composition, indispensables pour certaines écritures. Le tiret est placé en
 * fin de classe pour rester un caractère littéral. Les bornes de longueur sont
 * vérifiées à part, par mb_strlen(), qui compte les caractères et non les
 * octets.
 */
const MOTIF_NOM = "/^[\p{L}\p{M} \'’-]+$/u";

/**
 * Applique les règles communes à un nom et à un prénom.
 *
 * Le libellé sert uniquement à choisir le message ; il ne change pas la règle.
 */
function valider_nom_ou_prenom(mixed $valeur, string $libelle): ?string
{
    $texte = trim((string) $valeur);
    $refus = sprintf('Ce %s n’est pas accepté.', $libelle);
    $longueur = mb_strlen($texte, 'UTF-8');

    if ($longueur < LONGUEUR_NOM_MIN || $longueur > LONGUEUR_NOM_MAX) {
        return $refus;
    }

    if (preg_match(MOTIF_NOM, $texte) !== 1) {
        return $refus;
    }

    return null;
}

/**
 * Renvoie un message si le nom est refusé, ou une valeur nulle s'il est accepté.
 */
function valider_nom(mixed $valeur): ?string
{
    return valider_nom_ou_prenom($valeur, 'nom');
}

/**
 * Renvoie un message si le prénom est refusé, ou une valeur nulle s'il est accepté.
 */
function valider_prenom(mixed $valeur): ?string
{
    return valider_nom_ou_prenom($valeur, 'prénom');
}


/**
 * Renvoie un message si l'adresse électronique est refusée, ou une valeur nulle.
 *
 * Le filtre de courrier de PHP est la seule autorité : une adresse mal formée
 * est refusée avant toute requête en base.
 */
function valider_email(mixed $valeur): ?string
{
    $texte = trim((string) $valeur);

    if ($texte === '' || mb_strlen($texte, 'UTF-8') > 255) {
        return 'Cette adresse électronique n’est pas acceptée.';
    }

    if (filter_var($texte, FILTER_VALIDATE_EMAIL) === false) {
        return 'Cette adresse électronique n’est pas acceptée.';
    }

    return null;
}

/**
 * Renvoie un message si le mot de passe est refusé, ou une valeur nulle.
 *
 * Les quatre conditions du cahier des charges sont vérifiées séparément, mais
 * un seul et même message est rendu : le formulaire ne révèle pas laquelle
 * manque.
 */
function valider_mot_de_passe(mixed $valeur): ?string
{
    $texte = (string) $valeur;
    $refus = 'Ce mot de passe n’est pas accepté.';

    if (mb_strlen($texte, 'UTF-8') < LONGUEUR_MOT_DE_PASSE_MIN) {
        return $refus;
    }

    if (preg_match('/\p{Lu}/u', $texte) !== 1) {
        return $refus;
    }

    if (preg_match('/\p{Ll}/u', $texte) !== 1) {
        return $refus;
    }

    if (preg_match('/\d/u', $texte) !== 1) {
        return $refus;
    }

    return null;
}

/**
 * Compare la confirmation au mot de passe saisi, à temps constant.
 *
 * La comparaison est stricte et de longueur constante, ce qui évite qu'un
 * attaquant mesure le temps mis à rejeter une confirmation.
 */
function valider_confirmation(mixed $mot_de_passe, mixed $confirmation): ?string
{
    if (!is_string($mot_de_passe) || !is_string($confirmation)) {
        return 'Les deux mots de passe ne sont pas identiques.';
    }

    if (!hash_equals($mot_de_passe, $confirmation)) {
        return 'Les deux mots de passe ne sont pas identiques.';
    }

    return null;
}

/**
 * Renvoie le validateur associé à un champ nommé.
 *
 * Les champs gérés sont ceux du formulaire d'inscription ; tout autre nom est
 * renvoyé comme valeur nulle et donc ignoré par valider_champs().
 */
function validateur_pour(string $champ): ?callable
{
    return match ($champ) {
        'nom' => valider_nom(...),
        'prenom' => valider_prenom(...),
        'email' => valider_email(...),
        'password' => valider_mot_de_passe(...),
        'password_confirm' => valider_confirmation(...),
        default => null,
    };
}

/**
 * Valide un ensemble de champs et renvoie les messages par nom de champ.
 *
 * Les valeurs sont lues dans le tableau fourni, dans l'ordre des champs
 * demandés. Par défaut, tous les champs du formulaire d'inscription sont
 * validés, confirmation comprise. Seuls les champs refusés figurent dans le
 * résultat : un tableau vide signifie que tout est accepté.
 *
 * @param array<string, mixed> $donnees
 * @param list<string>|null    $champs Champs à valider, ou tous les champs gérés.
 *
 * @return array<string, string> Messages indexés par nom de champ.
 */
function valider_champs(array $donnees, ?array $champs = null): array
{
    $champs = $champs ?? CHAMPS_INSCRIPTION;

    $erreurs = [];

    foreach ($champs as $champ) {
        $validateur = validateur_pour((string) $champ);

        if ($validateur === null) {
            continue;
        }

        $saisie = $donnees[(string) $champ] ?? '';

        // La confirmation se compare au mot de passe saisi : les deux
        // lectures viennent du même ensemble de données.
        if ($champ === 'password_confirm') {
            $message = valider_confirmation($donnees['password'] ?? '', $saisie);
        } else {
            $message = $validateur($saisie);
        }

        if ($message !== null) {
            $erreurs[(string) $champ] = $message;
        }
    }

    return $erreurs;
}
