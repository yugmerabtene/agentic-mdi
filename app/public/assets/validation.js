/* validation.js — validation de confort des formulaires du laboratoire.
 *
 * Portée et limite de ce fichier, à lire avant toute modification.
 *
 * Ce script n'est qu'un confort d'affichage. Il ne fait jamais autorité :
 * la validation du serveur, dans app/src/validator.php, seule decides de
 * l'acceptation d'une inscription, et elle revérifie l'ensemble des champs.
 * Le formulaire reste donc toujours envoyable, même si ce fichier est absent,
 * bloqué par le navigateur ou en erreur. Pour cette raison, ce script ne fait
 * jamais aucune des trois choses suivantes : il n'appelle pas
 * preventDefault, il ne désactive ni le champ ni le bouton d'envoi, et il
 * n'écrit rien dans setCustomValidity, qui bloquerait la soumission.
 *
 * Aucune dépendance, aucun appel réseau, aucune police. Les règles ci-dessous
 * reprennent mot pour mot celles du validateur du serveur : un message
 * identique des deux côtés évite que le lecteur croie à une contradiction.
 */
(function () {
  'use strict';

  /* Longueurs reprises de app/src/validator.php. */
  var LONGUEUR_NOM_MIN = 2;
  var LONGUEUR_NOM_MAX = 50;
  var LONGUEUR_MOT_DE_PASSE_MIN = 8;
  var LONGUEUR_EMAIL_MAX = 255;

  /* Motifs identiques à ceux du serveur. Les séquences d'échappement de
   * propriété Unicode (\p{L}, \p{M}, \p{Lu}, \p{Ll} et \d) couvrent les lettres
   * accentuées, les lettres d'autres écritures et les marques de composition :
   * c'est le même alphabet que celui du validateur côté serveur. */
  var MOTIF_NOM = /^[\p{L}\p{M} '’-]+$/u;
  var MOTIF_MAJUSCULE = /\p{Lu}/u;
  var MOTIF_MINUSCULE = /\p{Ll}/u;
  var MOTIF_CHIFFRE = /\d/u;

  /* Les quatre conditions du mot de passe, dans l'ordre d'affichage de la
   * jauge. Chacune renvoie vrai lorsque la condition est satisfaite. */
  var REGLES_MOT_DE_PASSE = [
    {
      cle: 'longueur',
      texte: 'Huit caractères au moins',
      test: function (valeur) {
        return valeur.length >= LONGUEUR_MOT_DE_PASSE_MIN;
      }
    },
    {
      cle: 'majuscule',
      texte: 'Une lettre majuscule',
      test: function (valeur) {
        return MOTIF_MAJUSCULE.test(valeur);
      }
    },
    {
      cle: 'minuscule',
      texte: 'Une lettre minuscule',
      test: function (valeur) {
        return MOTIF_MINUSCULE.test(valeur);
      }
    },
    {
      cle: 'chiffre',
      texte: 'Un chiffre',
      test: function (valeur) {
        return MOTIF_CHIFFRE.test(valeur);
      }
    }
  ];

  /* Validateurs par nom de champ. Chacun renvoie un message lorsque la saisie
   * est refusée, et la valeur nulle lorsqu'elle est acceptable. Les libellés
   * reprennent ceux du serveur, au caractère près. */
  var VALIDATEURS = {
    nom: function (valeur) {
      return valider_nom_ou_prenom(valeur, 'nom');
    },
    prenom: function (valeur) {
      return valider_nom_ou_prenom(valeur, 'prénom');
    },
    email: function (valeur) {
      var texte = valeur.trim();

      if (texte === '' || texte.length > LONGUEUR_EMAIL_MAX) {
        return 'Cette adresse électronique n’est pas acceptée.';
      }

      /* La forme d'une adresse est ici un simple confort : le seul filtre qui
       * fait autorité est celui du serveur. */
      if (!/^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/.test(texte)) {
        return 'Cette adresse électronique n’est pas acceptée.';
      }

      return null;
    },
    password: function (valeur) {
      if (valeur === '') {
        return 'Ce mot de passe n’est pas accepté.';
      }

      /* La forme complète n'est exigeée que sur le formulaire d'inscription,
       * qui porte un mot de passe de création. Sur le formulaire de connexion,
       * exiger huit caractères et une majuscule aurait tort : la règle
       * appartient à l'inscription, pas à la reconnaissance du mot de passe
       * déjà choisi. */
      if (!document.querySelector('input[name="password_confirm"]')) {
        return null;
      }

      var satisfaites = compter_regles_satisfaites(valeur);

      return satisfaites === REGLES_MOT_DE_PASSE.length ? null : 'Ce mot de passe n’est pas accepté.';
    },
    password_confirm: function (valeur) {
      var attendu = document.querySelector('input[name="password"]');

      if (attendu === null) {
        return null;
      }

      if (valeur !== attendu.value) {
        return 'Les deux mots de passe ne sont pas identiques.';
      }

      return null;
    }
  };

  /**
   * Applique au nom et au prénom les règles communes du serveur.
   */
  function valider_nom_ou_prenom(valeur, libelle) {
    var texte = valeur.trim();
    var refus = 'Ce ' + libelle + ' n’est pas accepté.';

    if (texte.length < LONGUEUR_NOM_MIN || texte.length > LONGUEUR_NOM_MAX) {
      return refus;
    }

    if (!MOTIF_NOM.test(texte)) {
      return refus;
    }

    return null;
  }

  /**
   * Compte les conditions de mot de passe satisfaites par une saisie.
   */
  function compter_regles_satisfaites(valeur) {
    var total = 0;
    var index;

    for (index = 0; index < REGLES_MOT_DE_PASSE.length; index += 1) {
      if (REGLES_MOT_DE_PASSE[index].test(valeur)) {
        total += 1;
      }
    }

    return total;
  }

  /**
   * Crée, sous un champ, le paragraphe qui accueille le message de confort.
   *
   * Le paragraphe est créé une seule fois et porte un identifiant distinct de
   * celui du serveur : le message du serveur reste affiché tel quel, jamais
   * réécrit par le navigateur.
   */
  function conteneur_message(champ) {
    var identifiant = 'erreur-confort-' + champ.name;
    var existant = document.getElementById(identifiant);

    if (existant !== null) {
      return existant;
    }

    var message = document.createElement('p');
    message.className = 'champ-erreur';
    message.id = identifiant;
    message.setAttribute('role', 'alert');
    message.setAttribute('aria-live', 'polite');
    message.hidden = true;

    /* Le message doit être lu par les technologies d'assistance avec le
     * champ, et non perdu dans la lecture de la page. */
    var decrit = champ.getAttribute('aria-describedby');
    champ.setAttribute('aria-describedby', decrit ? decrit + ' ' + identifiant : identifiant);

    /* Le conteneur du champ est un div.champ ; à défaut, on se place juste
     * après l'étiquette, ce qui laisse le message sous le champ. */
    var parent = champ.closest('.champ');

    if (parent === null) {
      champ.parentNode.insertBefore(message, champ.nextSibling);
    } else {
      parent.appendChild(message);
    }

    return message;
  }

  /**
   * Affiche, ou efface, le message de confort d'un champ.
   */
  function publier(champ, message) {
    var paragraphe = conteneur_message(champ);

    if (message === null) {
      paragraphe.hidden = true;
      paragraphe.textContent = '';
      champ.removeAttribute('aria-invalid');

      return;
    }

    paragraphe.hidden = false;
    paragraphe.textContent = message;
    champ.setAttribute('aria-invalid', 'true');
  }

  /**
   * Construit la jauge de force du mot de passe et la place sous le champ.
   *
   * La jauge n'est construite que pour un champ de création de mot de passe,
   * reconnaissable à son attribut de complétion automatique. Elle est absente du
   * formulaire de connexion, où aucune règle de forme n'est attendue.
   */
  function construire_jauge(champ) {
    var identifiant = 'force-mot-de-passe';

    if (document.getElementById(identifiant) !== null) {
      return document.getElementById(identifiant);
    }

    var bloc = document.createElement('div');
    bloc.className = 'force';
    bloc.id = identifiant;

    var resume = document.createElement('p');
    resume.className = 'force-resume';
    resume.setAttribute('aria-live', 'polite');
    resume.textContent = 'Aucune règle satisfaite sur quatre.';

    var jauge = document.createElement('span');
    jauge.className = 'force-jauge';
    /* La jauge est un repère de progression : elle expose sa valeur au
     * navigateur d'assistance, qui l'annonce à chaque changement. */
    jauge.setAttribute('role', 'progressbar');
    jauge.setAttribute('aria-valuemin', '0');
    jauge.setAttribute('aria-valuemax', String(REGLES_MOT_DE_PASSE.length));
    jauge.setAttribute('aria-valuenow', '0');
    jauge.setAttribute('aria-labelledby', 'force-mot-de-passe-resume');

    var part = document.createElement('span');
    part.className = 'force-part';
    jauge.appendChild(part);

    var detail = document.createElement('ul');
    detail.className = 'force-detail';

    REGLES_MOT_DE_PASSE.forEach(function (regle) {
      var item = document.createElement('li');
      item.className = 'force-regle';
      item.setAttribute('data-satisfaite', '0');
      item.textContent = regle.texte;
      detail.appendChild(item);
    });

    resume.id = 'force-mot-de-passe-resume';
    bloc.appendChild(resume);
    bloc.appendChild(jauge);
    bloc.appendChild(detail);

    var parent = champ.closest('.champ');

    if (parent === null) {
      champ.parentNode.insertBefore(bloc, champ.nextSibling);
    } else {
      parent.appendChild(bloc);
    }

    return bloc;
  }

  /**
   * Met la jauge de force en accord avec la saisie courante.
   */
  function publier_jauge(bloc, valeur) {
    var satisfaites = compter_regles_satisfaites(valeur);
    var part = bloc.querySelector('.force-part');
    var jauge = bloc.querySelector('.force-jauge');
    var resume = bloc.querySelector('.force-resume');
    var items = bloc.querySelectorAll('.force-regle');
    var proportion = (satisfaites / REGLES_MOT_DE_PASSE.length) * 100;
    var index;

    /* La largeur de la portion remplie est la seule donnée qui porte la
     * proportion ; la couleur reste constante pour ne pas signaler une couleur
     * par une autre couleur. */
    part.style.width = proportion + '%';
    jauge.setAttribute('aria-valuenow', String(satisfaites));
    jauge.setAttribute(
      'aria-valuetext',
      satisfaites + ' règle(s) satisfaite(s) sur ' + REGLES_MOT_DE_PASSE.length
    );

    resume.textContent = satisfaites === 0
      ? 'Aucune règle satisfaite sur ' + REGLES_MOT_DE_PASSE.length + '.'
      : satisfaites + ' règle(s) satisfaite(s) sur ' + REGLES_MOT_DE_PASSE.length + '.';

    for (index = 0; index < items.length; index += 1) {
      items[index].setAttribute(
        'data-satisfaite',
        REGLES_MOT_DE_PASSE[index].test(valeur) ? '1' : '0'
      );
    }
  }

  /**
   * Vérifie un champ et publie son message, sans jamais interrompre la saisie.
   */
  function verifier(champ, jauge) {
    var validateur = VALIDATEURS[champ.name];

    if (typeof validateur !== 'function') {
      return;
    }

    /* Un champ encore vide ne reçoit aucun message : accuser l'utilisateur
     * avant qu'il n'ait rien saisi rend la page hostile. Le premier caractère
     * saisi déclenche la première vérification. */
    if (champ.value.trim() === '') {
      publier(champ, null);

      if (jauge !== null) {
        publier_jauge(jauge, '');
      }

      return;
    }

    /* La confirmation se revérifie dès que le mot de passe change, sans
     * attendre que le champ de confirmation perde le focus. */
    publier(champ, validateur(champ.value));

    if (jauge !== null && champ.name === 'password') {
      publier_jauge(jauge, champ.value);
    }
  }

  /**
   * Branche la validation de confort sur un formulaire.
   */
  function brancher(formulaire) {
    var jauge = null;

    Object.keys(VALIDATEURS).forEach(function (nom) {
      var champ = formulaire.querySelector('[name="' + nom + '"]');

      if (champ === null) {
        return;
      }

      if (jauge === null && nom === 'password'
          && champ.getAttribute('autocomplete') === 'new-password') {
        jauge = construire_jauge(champ);
        publier_jauge(jauge, champ.value);
      }

      /* Trois moments de vérification : la saisie, la perte de focus, et le
       * survol du champ. La saisie donne le retour immédiat, la perte de focus
       * fige le verdict quand le lecteur passe à autre chose, et le survol
       * rappelle la règle d'un champ survolé sans avoir été rempli. Aucun de
       * ces trois écouteurs ne peut interrompre l'envoi. */
      ['input', 'blur', 'mouseover'].forEach(function (evenement) {
        champ.addEventListener(evenement, function () {
          verifier(champ, jauge);
        });
      });
    });

    /* Le mot de passe et sa confirmation sont solidaires : retoucher l'un
     * revérifie l'autre, pour que le second message ne reste pas faux. */
    var motDePasse = formulaire.querySelector('input[name="password"]');
    var confirmation = formulaire.querySelector('input[name="password_confirm"]');

    if (motDePasse !== null && confirmation !== null) {
      motDePasse.addEventListener('input', function () {
        verifier(confirmation, null);
      });
    }
  }

  /* Le script est chargé avec l'attribut defer, le document est donc déjà
   * analysé ici. Le garde-fou reste utile si l'attribut vient à manquer. */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      demarrer();
    });
  } else {
    demarrer();
  }

  function demarrer() {
    Array.prototype.forEach.call(document.querySelectorAll('form.formulaire'), brancher);
  }
}());
