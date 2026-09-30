<?php

declare(strict_types=1);

namespace App;

/**
 * Contrôleur d'authentification : inscription, connexion, déconnexion.
 *
 * Les trois parcours partagent les mêmes garde-fou, dans le même ordre :
 * vérification du jeton CSRF, lecture des champs, validation, accès à la base,
 * puis redirection. Aucun de ces trois points ne renvoie de valeur : une
 * inscription, une connexion et une déconnexion se terminent toutes par un
 * changement d'adresse, afin que le navigateur ne puisse pas rejouer la
 * soumission par la touche de retour.
 *
 * Les messages d'échec de la connexion sont volontairement uniques. Ils ne
 * distinguent jamais une adresse inconnue d'un mot de passe faux, et le temps
 * de traitement est le même dans les deux cas : un attaquant ne peut donc pas
 * énumérer les comptes à partir de la réponse.
 */
final class AuthController
{
    /**
     * Message unique d'échec de connexion.
     *
     * Une seule constante alimente tous les refus : il ne peut donc pas
     * arrive qu'un chemin de refus en affiche un autre.
     */
    public const MESSAGE_CONNEXION_REFUSEE = 'Adresse électronique ou mot de passe incorrect.';

    /** Clé de session qui porte le message de succès d'un parcours réussi. */
    private const CLE_SUCCES = 'succes_parcours';

    /** Clé de session qui porte les valeurspreviouses à réafficher. */
    private const CLE_SAISIE = 'saisie_formulaire';

    /** Clé de session qui compte les échecs de connexion consécutifs. */
    private const CLE_TENTATIVES = 'tentatives_connexion';

    /**
     * Empreinte bcrypt de comparaison, utilisée quand l'adresse saisie
     * n'existe pas.
     *
     * Vérifier un mot de passe contre une empreinte qui n'appartient à personne
     * coûte le même temps que contre une empreinte réelle. Sans cela, une
     * réponse instantanée révélerait qu'aucun compte n'est inscrit sous
     * l'adresse essayée. Cette valeur n'est pas un secret : c'est une empreinte
     * publique, sans ligne correspondante dans la table.
     */
    private const EMPREINTE_DE_COMPARAISON = '$2y$10$3/puWdEdBb0K4nKaohJx7.YNL7ixPqIvMNDU/A9vjJrA5fajMtPJ2';

    /** Délai ajouté au premier échec de connexion, en microsecondes. */
    private const DELAI_UNITAIRE = 150000;

    /** Plafond du délai d'attente, en microsecondes. */
    private const DELAI_MAXIMUM = 600000;

    /** Champs du formulaire d'inscription qui sont réaffichés après un refus. */
    private const CHAMPS_AFFICHES_INSCRIPTION = ['nom', 'prenom', 'email'];

    /**
     * Traite la soumission du formulaire d'inscription.
     *
     * Aucune ligne n'est insérée tant qu'une seule règle échoue : la
     * validation est menée en entier avant tout accès en écriture, et la
     * création ne reçoit que des valeurs déjà acceptées.
     */
    public static function inscrire(): never
    {
        exiger_csrf();

        $donnees = self::lire_champs_inscription($_POST);
        $erreurs = valider_champs($donnees, CHAMPS_INSCRIPTION);

        // L'unicité n'est interrogée que si les champs sont acceptés : une
        // adresse mal formée n'a pas à atteindre la base.
        if ($erreurs === [] && User::existe_par_email($donnees['email'])) {
            $erreurs['email'] = 'Un compte est déjà enregistré avec cette adresse électronique.';
        }

        if ($erreurs !== []) {
            definir_erreurs($erreurs);
            self::conserver_saisie(self::sous_ensemble($donnees, self::CHAMPS_AFFICHES_INSCRIPTION));
            rediriger_vers('/register');
        }

        // La création refait elle-même la vérification d'unicité, car deux
        // inscriptions simultanées peuvent passer le contrôle ci-dessus. C'est
        // l'index unique de la base qui tranche, et un refus ici n'a rien
        // inséré.
        $identifiant = User::creer(
            $donnees['nom'],
            $donnees['prenom'],
            $donnees['email'],
            $donnees['password']
        );

        if ($identifiant === false) {
            definir_erreurs(['email' => 'Un compte est déjà enregistré avec cette adresse électronique.']);
            self::conserver_saisie(self::sous_ensemble($donnees, self::CHAMPS_AFFICHES_INSCRIPTION));
            rediriger_vers('/register');
        }

        $ligne = User::trouver_par_id($identifiant);

        if ($ligne === null) {
            // La ligne vient d'être insérée : son absence signale une anomalie
            // de base, qui doit être journalisée et non montrée.
            journaliser(sprintf('Compte créé mais introuvable ensuite (identifiant %d).', $identifiant));

            throw new \RuntimeException('Compte introuvable après création.');
        }

        self::oublier_tentatives();
        self::definir_succes('Votre compte a bien été créé. Vous êtes connecté.');

        // L'identifiant de session est régénéré avant d'y déposer l'identité :
        // une session ouverte avant l'authentification ne doit pas survivre à
        // l'authentification.
        ouvrir_session_utilisateur($ligne);
        User::enregistrer_connexion($ligne['id']);

        rediriger_vers('/dashboard');
    }

    /**
     * Traite la soumission du formulaire de connexion.
     */
    public static function connecter(): never
    {
        exiger_csrf();

        $email = self::valeur_texte($_POST['email'] ?? '');
        $mot_de_passe = self::valeur_texte($_POST['password'] ?? '');

        $ligne = null;

        if ($email !== '' && valider_email($email) === null) {
            $ligne = User::trouver_par_email($email);
        }

        if ($ligne !== null) {
            $accepte = User::verifier_mot_de_passe($mot_de_passe, $ligne['password'], $ligne['id']);
        } else {
            // Compte inconnu : le même coût de hachage est payé, pour que la
            // réponse ne distingue pas ce cas du précédent.
            $accepte = password_verify($mot_de_passe, self::EMPREINTE_DE_COMPARAISON);
        }

        if (!$accepte) {
            self::ralentir_echec();
            definir_erreurs(['email' => self::MESSAGE_CONNEXION_REFUSEE]);
            self::conserver_saisie(['email' => $email]);
            rediriger_vers('/login');
        }

        self::oublier_tentatives();
        self::definir_succes('Connexion réussie. Votre tableau de bord vous attend.');

        // ouvrir_session_utilisateur() régénère l'identifiant de session ; la
        // dernière connexion est notée après, pour que l'heure affichée par le
        // tableau de bord soit celle de cette session.
        ouvrir_session_utilisateur($ligne);
        User::enregistrer_connexion($ligne['id']);

        rediriger_vers('/dashboard');
    }

    /**
     * Traite la soumission du formulaire de déconnexion.
     *
     * La destruction porte sur la session entière : le cookie est renvoyé avec
     * une date d'expiration dans le passé, donc le navigateur ne garde aucune
     * copie de l'identifiant.
     */
    public static function deconnecter(): never
    {
        exiger_csrf();

        detruire_session();

        rediriger_vers('/login');
    }

    /**
     * Dépose un message de succès, lu une seule fois par la page suivante.
     */
    public static function definir_succes(string $message): void
    {
        demarrer_session();
        $_SESSION[self::CLE_SUCCES] = $message;
    }

    /**
     * Renvoie le message de succès du parcours précédent, puis l'efface.
     */
    public static function lire_succes(): ?string
    {
        demarrer_session();

        $message = $_SESSION[self::CLE_SUCCES] ?? null;
        unset($_SESSION[self::CLE_SUCCES]);

        if (!is_string($message) || $message === '') {
            return null;
        }

        return $message;
    }

    /**
     * Conserve les valeurspreviouses à réafficher dans le formulaire.
     *
     * Les mots de passe ne sont jamais conservés : une valeur secrète n'a pas
     * sa place dans une session, qui peut être lue par le code serveur.
     */
    public static function conserver_saisie(array $champs): void
    {
        demarrer_session();

        $propres = [];

        foreach ($champs as $nom => $valeur) {
            if (in_array((string) $nom, ['password', 'password_confirm'], true)) {
                continue;
            }

            $propres[(string) $nom] = self::valeur_texte($valeur);
        }

        $_SESSION[self::CLE_SAISIE] = $propres;
    }

    /**
     * Renvoie les valeurspreviouses, puis les efface.
     *
     * @return array<string, string>
     */
    public static function lire_saisie(): array
    {
        demarrer_session();

        $saisie = $_SESSION[self::CLE_SAISIE] ?? [];
        unset($_SESSION[self::CLE_SAISIE]);

        if (!is_array($saisie)) {
            return [];
        }

        $propres = [];

        foreach ($saisie as $nom => $valeur) {
            $propres[(string) $nom] = self::valeur_texte($valeur);
        }

        return $propres;
    }

    /**
     * Lit les cinq champs du formulaire d'inscription depuis la soumission.
     *
     * Chaque valeur est ramenée à une chaîne, sans octet nul : un tableau
     * envoyé à la place d'un champ ne doit pas atteindre la base.
     *
     * @param array<string, mixed> $source
     *
     * @return array<string, string>
     */
    private static function lire_champs_inscription(array $source): array
    {
        $champs = [];

        foreach (CHAMPS_INSCRIPTION as $champ) {
            $champs[$champ] = self::valeur_texte($source[$champ] ?? '');
        }

        return $champs;
    }

    /**
     * Ramène une valeur de soumission à une chaîne exploitable.
     */
    private static function valeur_texte(mixed $valeur): string
    {
        if (is_array($valeur) || is_object($valeur)) {
            return '';
        }

        $texte = is_scalar($valeur) ? (string) $valeur : '';

        return str_replace("\0", '', $texte);
    }

    /**
     * Ne conserve du tableau fourni que les champs demandés.
     *
     * @param  array<string, string> $donnees
     * @param  list<string>          $champs
     *
     * @return array<string, string>
     */
    private static function sous_ensemble(array $donnees, array $champs): array
    {
        $retenu = [];

        foreach ($champs as $champ) {
            $retenu[$champ] = $donnees[$champ] ?? '';
        }

        return $retenu;
    }

    /**
     * Attend un temps croissant après un échec de connexion.
     *
     * Le compteur vit en session : une suite d'essais depuis le même
     * navigateur allonge l'attente, sans infrastructure supplémentaire. Le
     * plafond borne l'attente, pour qu'une saisie correcte reste possible.
     */
    private static function ralentir_echec(): void
    {
        demarrer_session();

        $tentatives = (int) ($_SESSION[self::CLE_TENTATIVES] ?? 0) + 1;
        $_SESSION[self::CLE_TENTATIVES] = $tentatives;

        usleep(min(self::DELAI_MAXIMUM, self::DELAI_UNITAIRE * $tentatives));
    }

    /**
     * Remet le compteur d'échecs à zéro après un parcours réussi.
     */
    private static function oublier_tentatives(): void
    {
        demarrer_session();
        unset($_SESSION[self::CLE_TENTATIVES], $_SESSION[self::CLE_SAISIE], $_SESSION[CLE_ERREURS]);
    }
}
