<?php

declare(strict_types=1);

namespace App;

/**
 * Contrôleur utilisateur : identité courante, protection du tableau de bord,
 * changement d'adresse et ossature des pages.
 *
 * Le contrôleur ne se fie jamais à la session seule. La session indique qui
 * prétend être connecté ; c'est la base qui dit qui existe encore. Un compte
 * supprimé entre deux requêtes perd donc sa session au lieu de conserver un
 * tableau de bord affichant une identité qui n'existe plus.
 *
 * L'ossature des pages vit ici pour une raison de périmètre : le cahier des
 * charges ne prévoit aucun gabarit, et le nombre de fichiers est limité. Dès
 * qu'un dossier de vues existe, `entete_page()` et `pied_de_page()` doivent y
 * être déplacées telles quelles.
 */
final class UserController
{
    /** Adresses servies par l'application, dans l'ordre de navigation. */
    private const NAVIGATION = [
        '/register' => 'Inscription',
        '/login' => 'Connexion',
        '/dashboard' => 'Tableau de bord',
    ];

    /**
     * Renvoie la ligne de l'utilisateur connecté, ou change d'adresse.
     *
     * Deux refus sont possibles et tous deux redirigent vers la connexion par
     * un code 302 : l'absence de session, et une session dont le compte a
     * disparu de la base. Dans les deux cas la session est détruite, pour
     * qu'aucune trace ne subsiste d'une identité qui ne peut plus servir.
     *
     * @return array<string, mixed> Ligne complète, empreinte du mot de passe comprise.
     */
    public static function exiger_utilisateur(): array
    {
        $session = utilisateur_courant();

        if ($session === null) {
            rediriger_vers('/login');
        }

        $ligne = User::trouver_par_id($session['id']);

        if ($ligne === null) {
            journaliser(sprintf(
                'Session referring à un compte absent de la base (identifiant %d).',
                $session['id']
            ));

            detruire_session();
            rediriger_vers('/login');
        }

        return $ligne;
    }

    /**
     * Indique si une identité est portée par la session courante.
     *
     * Cette question décide de l'adresse d'arrivée de la racine du site. Elle
     * se limite volontairement à la session : la racine n'a pas à interroger la
     * base, et le tableau de bord refait ce contrôle de lui-même.
     */
    public static function session_ouverte(): bool
    {
        return utilisateur_courant() !== null;
    }

    /**
     * Écrit l'adresse courante au format court, dans la fuseau du serveur.
     *
     * Une valeur illisible ou nulle ne fait pas échouer la page : elle est
     * rendue par une mention neutre, et le journal garde le détail.
     */
    public static function formater_horodatage(?string $valeur): string
    {
        if ($valeur === null || trim($valeur) === '') {
            return 'Jamais';
        }

        try {
            $instant = new \DateTimeImmutable($valeur);
        } catch (\Exception $exception) {
            journaliser('Horodatage illisible en base.', $exception);

            return 'Indisponible';
        }

        return $instant->format('d/m/Y à H:i');
    }

    /**
     * Ouvre le document et écrit l'en-tête commun aux pages.
     *
     * @param string $titre  Titre de la page, déjà échappé.
     * @param string $adresse Adresse courante, pour marquer l'onglet actif.
     */
    public static function entete_page(string $titre, string $adresse): void
    {
        $titre_echappe = echapper($titre);
        $titre_site = 'Gestion des utilisateurs';

        printf(
            "<!DOCTYPE html>\n<html lang=\"fr\">\n<head>\n<meta charset=\"utf-8\">\n"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . "<meta name=\"robots\" content=\"noindex, nofollow\">\n"
            . "<title>%s — %s</title>\n</head>\n<body class=\"page\">\n"
            . "<header class=\"entete\">\n<p class=\"marque\">%s</p>\n"
            . "<nav class=\"navigation\" aria-label=\"Navigation principale\">\n<ul class=\"liste-navigation\">\n",
            $titre_echappe,
            $titre_site,
            $titre_site
        );

        foreach (self::NAVIGATION as $cible => $libelle) {
            printf(
                "<li class=\"element-navigation\"><a class=\"lien-navigation\" href=\"%s\"%s>%s</a></li>\n",
                echapper($cible),
                $cible === $adresse ? ' aria-current="page"' : '',
                echapper($libelle)
            );
        }

        print "</ul>\n</nav>\n</header>\n<main class=\"contenu\">\n";
    }

    /**
     * Ferme le document, et rend le formulaire de déconnexion s'il y a lieu.
     */
    public static function pied_de_page(): void
    {
        if (self::session_ouverte()) {
            printf(
                "<form class=\"formulaire-deconnexion\" method=\"post\" action=\"/logout\">\n"
                . "<input type=\"hidden\" name=\"%s\" value=\"%s\">\n"
                . "<button class=\"bouton\" type=\"submit\">Se déconnecter</button>\n"
                . "</form>\n",
                echapper(CLE_JETON_CSRF),
                echapper(jeton_csrf())
            );
        }

        print "</main>\n<footer class=\"pied\">\n"
            . "<p class=\"mention\">Laboratoire de gestion des utilisateurs — pile PHP, Apache et MySQL.</p>\n"
            . "</footer>\n</body>\n</html>\n";
    }
}
