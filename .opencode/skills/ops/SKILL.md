---
name: ops
description: Recettes vérifiées pour la chaîne d'intégration continue, les conteneurs, l'infrastructure déclarée, le déploiement, la surveillance, les sauvegardes, la performance, et gestion complète des secrets. À utiliser dès qu'une tâche DevOps est confiée.
---

# Exploitation

Chargez d'abord les compétences `common` et `tests`. Une configuration qui n'a
pas été validée par son outil n'est pas écrite. Une sauvegarde qui n'a pas été
testée n'existe pas.

## Les cinq principes

1. **Reproductibilité.** À partir du même état du dépôt, on obtient toujours
   le même résultat. Une version est épinglée, jamais « la dernière connue ».
2. **Idempotence.** Rejouer deux fois un déploiement ou une commande
   d'infrastructure produit le même état, sans effet de bord.
3. **Droit minimal.** Exécution en utilisateur non privilégié, image de base
   minimale, surface d'attaque réduite.
4. **Observabilité avant mise en production.** On ne déploie pas ce que l'on
   ne sait pas surveiller.
5. **Un laboratoire reste un laboratoire.** Aucune donnée réelle de client,
   aucune adresse de production réelle dans un livrable.

## La chaîne d'intégration continue

Relevez la version réelle de chaque outil avec la commande qui l'affiche.
Épinglez les actions par version, jamais par branche. Enchaînez exactement
cet ordre : récupération du code, cache des dépendances, installation,
vérification de style, vérification de type, tests, construction. Toute étape
doit interrompre la chaîne en cas d'échec.

| Erreur fréquente | Effet | Correction |
| --- | --- | --- |
| Action épinglée sur une branche | Le comportement change sans versionnement | Épingler sur une version précise |
| `continue-on-error` sur les tests | La chaîne passe au rouge sans rien dire | Retirer cette option |
| Aucun déclenchement sur la branche principale | La chaîne ne tourne jamais | Vérifier le filtre de branche |
| Secret en clair dans un fichier de flux | Fuite | Le coffre du dépôt, jamais une valeur littérale |
| Installation non déterministe | Deux exécutions, deux résultats | Verrouiller le fichier des dépendances |

## Les conteneurs

Épinglez l'image de base sur une version précise, jamais sur `latest`.
Créez un utilisateur non privilégié et changez de contexte avant de lancer le
processus. Séparez l'étape de construction de l'étape finale. N'introduisez
qu'un seul processus par conteneur. Les secrets arrivent par variable
d'environnement au moment de l'exécution : jamais copiés dans l'image, jamais
passés à la construction.

| Erreur fréquente | Effet | Correction |
| --- | --- | --- |
| `COPY . .` sans filtre | Copie de secrets, image démesurée | Un fichier d'exclusion strict |
| Exécution en root | Surface d'attaque inutilement élargie | Un utilisateur dédié |
| Fichier de secrets copié dans l'image | Fuite durable | Une variable d'environnement |
| Absence de fichier d'exclusion | Image énorme, contexte transmis en entier | Le créer dès la première image |

## L'infrastructure déclarée

Décrivez l'état souhaité, et non une suite de clics : un fichier relu dit ce
que l'infrastructure sera, une suite de commandes ne dit rien. Validez avant
d'appliquer, à chaque écriture, avec la commande de validation de votre outil.
Formatez, validez, puis appliquez. Une ressource qui n'est décrite dans aucun
fichier n'existe pas.

## Le déploiement

**Avant** tout déploiement, écrivez la procédure de retour arrière : la
commande exacte, l'état précédent attendu, et le critère de décision qui
déclenche le retour. Déployez par paliers, en mesurant entre deux paliers.
Déclarez la stratégie de bascule, car une bascule instantanée sur un service en
production est interdite. Après la bascule, mesurez avant d'annuler la
bascule précédente. Le déploiement s'arrête au premier signe anormal : on
répare, on ne pousse pas la catastrophe plus loin.

## La surveillance

À mettre en place avant la mise en production, jamais après. Prévenez des
journaux structurés, avec un horodatage et un identifiant de requête.
Suivez les métriques de débit, de taux d'erreur et de latence. Ajoutez des
sondes qui vérifient la dépendance réelle, pas seulement l'existence du
processus. Créez des alertes sur des conditions que l'on peut traiter : une
alerte que personne ne lit est un bruit qui endort la surveillance. Ne
journalisez jamais de donnée personnelle ni de jeton.

## Les sauvegardes

Une sauvegarde n'existe que si sa restauration a été testée. Définissez ce qui
est sauvegardé, à quelle fréquence, et combien de générations sont conservées.
Testez une restauration réelle, dans un environnement distinct, et mesurez sa
durée. Vérifiez qu'aucune sauvegarde ne contient de secret en clair.

## La performance

| Levier | Action | Preuve attendue |
| --- | --- | --- |
| Temps d'installation | Cache des dépendances, étapes parallèles | Deux exécutions comparées |
| Taille d'image | Base minimale, une étape finale, exclusions strictes | La taille rapportée |
| Temps d'exécution | Supprimer les boucles, mettre en cache, paralléliser | Une mesure avant et après |

Toute optimisation est annoncée avec sa mesure. Une optimisation sans mesure
reste une intuition.

## La gestion des secrets

Vous êtes le seul agent habilité à tenir cette frontière.

1. **L'inventaire** — un tableau de **noms** seulement : le nom du secret, son
   type, l'endroit où il sert, qui le fournit, et à quelle fréquence il est
   renouvelé. Jamais la valeur. Ce tableau vit dans un fichier d'inventaire
   seul, hors du versionnement.
2. **L'injection** — par variable d'environnement, au moment de l'exécution.
   Le fichier de configuration ne contient que le nom de la variable.
3. **Les gabarits** — un fichier d'exemple versionné, avec des valeurs de
   remplacement uniquement. Jamais de valeur réelle, même en laboratoire.
4. **L'exclusion** — le fichier d'exclusion bloque les fichiers de secrets,
   l'historique est sondé, et la chaîne d'intégration continue rejoue le même
   contrôle sur l'ensemble du dépôt.
5. **La rotation** — un secret déjà écrit reste compromis après sa
   suppression. On révoque, on fait tourner chez le propriétaire, et on ne
   réécrit pas l'historique.
6. **Les trois barrières** — un contrôle local avant chaque versionnement, une
   accroche de poussée, et un contrôle en chaîne sur l'historique complet.
   Jamais une seule barrière.
7. **Ne lisez jamais** un fichier de secrets pour vérifier une valeur.
   Arrêtez-vous sur le nom.
