<?php

declare(strict_types=1);

namespace App;

use PDOException;
use PDOStatement;

/**
 * Modèle Utilisateur : les huit colonnes de la table users.
 *
 * Aucune méthode ne construit une requête en collant une valeur dans le texte
 * SQL : tout passe par un paramètre lié. Aucune méthode ne renvoie ni ne
 * journalise le mot de passe saisi.
 */
final class User
{
    /** Colonnes utiles à l'affichage. La colonne password n'y figure jamais. */
    private const COLONNES_AFFICHAGE = ['id', 'nom', 'prenom', 'email', 'created_at', 'last_login'];

    /**
     * Indique si une adresse électronique est déjà enregistrée.
     */
    public static function existe_par_email(string $email): bool
    {
        $requete = self::preparer('SELECT id FROM users WHERE email = ? LIMIT 1');
        $requete->execute([$email]);

        return $requete->fetchColumn() !== false;
    }

    /**
     * Compte les lignes portant cette adresse, pour prouver qu'une création
     * refusée n'a rien inséré.
     */
    public static function compter_par_email(string $email): int
    {
        $requete = self::preparer('SELECT COUNT(*) FROM users WHERE email = ?');
        $requete->execute([$email]);

        return (int) $requete->fetchColumn();
    }

    /**
     * Renvoie la ligne complète correspondant à une adresse, ou une valeur
     * nulle. Une adresse absente n'est pas une erreur.
     *
     * La ligne contient l'empreinte du mot de passe, nécessaire à la
     * vérification ; elle ne doit jamais être rendue telle quelle.
     */
    public static function trouver_par_email(string $email): ?array
    {
        $requete = self::preparer(
            'SELECT id, nom, prenom, email, password, created_at, updated_at, last_login FROM users WHERE email = ? LIMIT 1'
        );
        $requete->execute([$email]);

        $ligne = $requete->fetch();

        return is_array($ligne) ? self::convertir($ligne) : null;
    }

    /**
     * Renvoie la ligne complète correspondant à un identifiant, ou une valeur
     * nulle.
     */
    public static function trouver_par_id(int $id): ?array
    {
        $requete = self::preparer(
            'SELECT id, nom, prenom, email, password, created_at, updated_at, last_login FROM users WHERE id = ? LIMIT 1'
        );
        $requete->execute([$id]);

        $ligne = $requete->fetch();

        return is_array($ligne) ? self::convertir($ligne) : null;
    }

    /**
     * Crée un compte et renvoie son identifiant, ou false si l'adresse est
     * déjà enregistrée.
     *
     * Le mot de passe est immédiatement converti en empreinte bcrypt : la
     * valeur saisie n'est ni conservée, ni journalisée, ni renvoyée. Un refus
     * n'insère aucune ligne.
     */
    public static function creer(string $nom, string $prenom, string $email, string $mot_de_passe): int|false
    {
        if (self::existe_par_email($email)) {
            return false;
        }

        $empreinte = password_hash($mot_de_passe, PASSWORD_BCRYPT);

        $requete = self::preparer('INSERT INTO users (nom, prenom, email, password) VALUES (?, ?, ?, ?)');

        try {
            $requete->execute([$nom, $prenom, $email, $empreinte]);
        } catch (PDOException $exception) {
            // 23000 : contrainte d'unicité violée. Deux inscriptions simultanées
            // peuvent passer la vérification ci-dessus ; c'est l'index unique
            // qui tranche, et la seconde doit être refusée sans exception.
            if ((string) $exception->getCode() === '23000') {
                return false;
            }

            journaliser(sprintf('Création de compte refusée par la base (%s)', $exception->getCode()), $exception);

            throw $exception;
        }

        return (int) base_de_donnees()->lastInsertId();
    }

    /**
     * Note l'instant de la dernière connexion à l'instant courant.
     *
     * La valeur de retour dit si l'ordre a été accepté par la base, pas si une
     * ligne a changé : MySQL ne compte pas une ligne dont la valeur est
     * identique, ce qui arrive si deux connexions tombent dans la même seconde.
     */
    public static function enregistrer_connexion(int $id): bool
    {
        $requete = self::preparer('UPDATE users SET last_login = NOW() WHERE id = ?');

        return $requete->execute([$id]);
    }

    /**
     * Compare un mot de passe saisi à une empreinte bcrypt.
     *
     * Si le coût de hachage a augmenté depuis la création du compte, l'empreinte
     * est remise à niveau : la mise à jour passe par un paramètre lié et le
     * mot de passe saisi n'est pas journalisé.
     */
    public static function verifier_mot_de_passe(string $saisi, string $empreinte, ?int $id = null): bool
    {
        if ($empreinte === '' || !password_verify($saisi, $empreinte)) {
            return false;
        }

        if ($id !== null && password_needs_rehash($empreinte, PASSWORD_BCRYPT)) {
            $requete = self::preparer('UPDATE users SET password = ? WHERE id = ?');
            $requete->execute([password_hash($saisi, PASSWORD_BCRYPT), $id]);
        }

        return true;
    }

    /**
     * Renvoie une copie de la ligne où chaque colonne a le type attendu.
     */
    public static function convertir(array $ligne): array
    {
        return [
            'id' => (int) $ligne['id'],
            'nom' => (string) $ligne['nom'],
            'prenom' => (string) $ligne['prenom'],
            'email' => (string) $ligne['email'],
            'password' => (string) $ligne['password'],
            'created_at' => $ligne['created_at'] === null ? null : (string) $ligne['created_at'],
            'updated_at' => $ligne['updated_at'] === null ? null : (string) $ligne['updated_at'],
            'last_login' => $ligne['last_login'] === null ? null : (string) $ligne['last_login'],
        ];
    }

    /**
     * Renvoie les champs affichables, escapés pour le navigateur.
     *
     * C'est la seule porte de sortie du modèle vers le navigateur : la
     * colonne password en est absente, et nom, prenom et email sont échappés.
     */
    public static function pour_affichage(array $ligne): array
    {
        $affichage = [];

        foreach (self::COLONNES_AFFICHAGE as $colonne) {
            $affichage[$colonne] = isset($ligne[$colonne]) ? echapper($ligne[$colonne]) : '';
        }

        return $affichage;
    }

    /**
     * Prépare une requête sur la connexion de la requête courante.
     */
    private static function preparer(string $sql): PDOStatement
    {
        return base_de_donnees()->prepare($sql);
    }
}
