---
name: common
description: Règles applicables à tous les agents : la preuve avant l'affirmation, l'absence totale de fuite de secrets, la qualité de la langue française, l'économie de jetons et le versionnement strict. À charger avant toute tâche, quel que soit l'agent.
---

# Règles communes

Chargez cette compétence avant de commencer votre travail. Ces règles
s'appliquent à tous les agents, sans exception, et priment sur les habitudes
personnelles.

## 1. La preuve avant l'affirmation

- **Une affirmation sans commande exécutée est un échec**, pas un succès. Si
  vous n'avez pas lancé la commande, vous ne savez pas.
- **Reformulez une sortie d'outil**, ne la recopiez jamais telle quelle.
- **Une valeur non fonctionnelle est un chiffre ou un niveau**, jamais une
  intention.
- **Signalez un échec au lieu de le contourner.** Un rapport qui ne mentionne
  aucun échec est un rapport faux.

## 2. L'absence totale de fuite de secrets

Un secret est un *credential* externe, c'est-à-dire une donnée
d'identification : jeton d'interface, jeton d'accès, mot de passe, clé
privée, certificat, secret de connexion, identifiant.

- **Jamais de valeur réelle en clair**, ni dans un fichier, ni dans une
  commande, ni dans un journal, ni dans un rapport, ni dans un message de
  versionnement, ni dans la mémoire du projet.
- **Jamais de lecture d'un fichier de secrets** pour en extraire la valeur.
  Le besoin d'un secret ne justifie jamais sa lecture : on se contente de son
  nom.
- **Toujours un gabarit** : `<A_COMPLETER>`, `{env:MA_CLE}`, `VOTRE_CLE`.
- **Avant chaque versionnement**, exécutez `./check-secrets.sh`. Un code de
  retour 1 bloque le versionnement.
- **Un secret déjà écrit reste compromis** après sa suppression : le
  signalez, et demandez la rotation au propriétaire.

## 3. La qualité de la langue française

- Écrivez un français soutenu : des phrases courtes, un sujet explicite, des
  verbes d'action.
- Mettez les accents : un mot sans accent est un défaut.
- Chaque terme technique est conservé, mais traduit au moins une fois.
- Employez les guillemets français, l'apostrophe typographique et le tiret
  cadratin.
- N'introduisez aucun caractère étranger dans un texte français.
- Le vocabulaire des livrables est **LAB** (laboratoire), jamais « TP ».
- Remplacez les anglicismes de confort quand un équivalent existe : sonder,
  journal, sortie, vérifier, configuration.

## 4. L'économie de jetons

Le contexte est la ressource la plus rare dont nous disposions.

| Ce qui est interdit | Ce qu'il faut faire |
| --- | --- |
| Lire un fichier entier pour y chercher une seule ligne | Lancer `grep` d'abord, puis `read` avec `offset` et `limit` |
| Lister un dossier pour savoir ce qu'il contient | Demander un motif de fichier, moins coûteux |
| Relire un fichier déjà lu | Mémoriser l'emplacement, puis citer le chemin |
| Explorer « pour voir » | Explorer pour répondre à une question réellement posée |
| Charger une compétence « au cas où » | La charger au moment où elle sert |
| Recopier une sortie d'outil dans un livrable | La reformuler, et ne garder que l'essentiel |
| Enchaîner plus de trois tâches sans rendre compte | Rendre compte, puis continuer |

Regroupez les appels d'outils liés en une seule fois. Ne déléguez jamais en
cascade : un sous-agent ne lance jamais de sous-agent.

## 5. Le versionnement strict

- **Une unité de travail par versionnement.** Jamais de lot fourre-tout.
- **Trois fichiers modifiés, puis versionnement.** Au-delà, la revue devient
  impossible.
- **Avant chaque versionnement**, dans cet ordre : `git status`,
  `git diff --cached --stat`, puis `./check-secrets.sh`. Un contrôle en échec
  bloque l'opération, sans exception.
- **Rédigez un message court et descriptif, en français.**
- **`suivi.json` est mis à jour dans le même versionnement** que la
  modification qu'il enregistre.
- **Interdits absolus** : `--force`, `--force-with-lease`, toute réécriture
  d'historique, ainsi que `git checkout .`, `git reset --hard` et
  `git clean -fd` pour effacer un travail. On versionne, ou on met le travail
  de côté. Jamais on ne détruit.
