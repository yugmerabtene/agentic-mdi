/* app.js — comportements d'ergonomie du laboratoire de gestion des
 * utilisateurs.
 *
 * Ce fichier ne traite aucune donnée et n'envoie rien : il n'ajoute que deux
 * conforts, tous deux facultatifs.
 *
 * 1. La confirmation avant la déconnexion. Le formulaire de déconnexion est
 *    entièrement gérée par le serveur — action, jeton de sécurité et adresse —
 *    et ce script ne fait que poser une question. Une question annulée laisse
 *    le formulaire intact et le serveur n'est jamais appelé à tort.
 * 2. La mise en évidence d'un message d'erreur lorsque le lecteur revient sur
 *    l'onglet. Un message d'erreur qui s'affiche pendant que l'onglet est en
 *    arrière-plan passe inaperçu, et la page suivante paraît alors muette. Au
 *    retour de l'onglet, le message est signalé une fois et placed sous le
 *    regard.
 *
 * Comme pour validation.js : aucune dépendance, aucun appel réseau, et
 * aucune écoute qui pourrait empêcher un envoi de formulaire.
 */
(function () {
  'use strict';

  /* Sélecteur du formulaire de déconnexion rendu par le pied de page. */
  var SELECTEUR_DECONNEXION = 'form.formulaire-deconnexion';

  /* Classe posée sur un message d'erreur pour le signaler au retour de l'onglet.
   * Elle est décrite dans style.css : l'animation y est limitée à une fois, et
   * remplacée par un contour permanent quand le lecteur demande moins
   * d'animation. */
  var ATTRIBUT_REACTIVITE = 'data-reactive';

  /**
   * Demande une confirmation avant la déconnexion.
   *
   * return false annule l'envoi et laisse la page en place. return true laisse
   * le navigateur poursuivre vers le serveur, qui reste seul juge de la
   * déconnexion.
   */
  function confirmer_deconnexion(evenement) {
    var accepte = window.confirm(
      'Voulez-vous vraiment vous déconnecter ?'
    );

    if (!accepte) {
      evenement.preventDefault();

      return false;
    }

    return true;
  }

  /**
   * Signale les messages d'erreur d'une page, une seule fois.
   */
  function signaler_messages() {
    /* Les messages produit par le serveur portent l'un des deux rôles : une
     * alerte pour un refus global, un statut pour une confirmation. Seuls les
     * refus sont signalés. */
    var messages = document.querySelectorAll('.message-erreur, .champ-erreur');
    var index;

    for (index = 0; index < messages.length; index += 1) {
      messages[index].setAttribute(ATTRIBUT_REACTIVITE, '1');
    }

    if (messages.length === 0) {
      return;
    }

    /* Le premier message est placé sous le regard sans faire défiler tout le
     * document : le défilement reste sous le contrôle du lecteur. */
    var premier = messages[0];

    if (typeof premier.scrollIntoView === 'function') {
      premier.scrollIntoView({ block: 'nearest', behavior: 'auto' });
    }
  }

  /**
   * Branche les comportements sur la page courante.
   */
  function demarrer() {
    Array.prototype.forEach.call(
      document.querySelectorAll(SELECTEUR_DECONNEXION),
      function (formulaire) {
        formulaire.addEventListener('submit', confirmer_deconnexion);
      }
    );

    /* Le document caché puis rendu de nouveau est le seul cas qui demande un
     * signalement : un message déjà vu n'a pas besoin d'être rappelé. */
    if (document.visibilityState === 'hidden') {
      document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
          signaler_messages();
        }
      });
    }
  }

  /* Le script est chargé avec l'attribut defer, le document est donc déjà
   * analysé ici. Le garde-fou reste utile si l'attribut vient à manquer. */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrer);
  } else {
    demarrer();
  }
}());
