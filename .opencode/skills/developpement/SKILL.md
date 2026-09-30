---
name: developpement
description: Méthode d'implémentation en cinq temps — comprendre, concevoir, écrire, prouver, livrer — pour exécuter une tâche de développement isolément. À utiliser au début de toute tâche d'implémentation confiée par l'orchestrateur.
---

# Développement

Chargez d'abord les compétences `common` et `tests`. Cette compétence décrit
la méthode d'implémentation d'une tâche, du premier examen jusqu'au rapport
de livraison.

## 1. Comprendre la tâche

- **Lisez `CONTEXT.md`** : finalité, périmètre, conventions et choix
  techniques s'appliquent à votre tâche.
- **Lisez votre tâche dans `suivi.json`**, ainsi que ses critères
  d'acceptation. Ce sont eux qui définissent quand le travail est fini.
- **Lisez le code voisin avant d'écrire.** Les conventions déjà en place
  priment toujours sur vos préférences personnelles.
- **Relevez la version réelle des outils** que vous allez utiliser, avec la
  commande qui l'affiche. Ne supposez jamais une version.

## 2. Concevoir la solution

- Avant d'écrire la première ligne, décrivez le fonctionnement en trois
  phrases : les entrées, le traitement, les sorties. Ajoutez où le code peut
  échouer.
- **Vérifiez la frontière de votre tâche.** Si vous avez besoin de quelque
  chose qui sort du périmètre, signalez-le au lieu de l'exécuter.

## 3. Écrire le code

- Une seule modification à la fois.
- **Suivez les conventions voisines** : même convention de nommage, même
  mise en forme, même formulation des messages d'erreur.
- **N'écrivez jamais de secret en clair.** Lisez une variable
  d'environnement, jamais une valeur.

## 4. Prouver le résultat

- Exécutez la phase de test complète décrite dans la compétence `tests`.
- **Consignez la commande exacte et son résultat réel** pour chaque critère
  d'acceptation.
- Un critère que vous n'avez pas prouvé reste non prouvé, et vous le déclarez
  comme tel.

## 5. Livrer le rapport

Renvoyez ce rapport, sans omettre aucune rubrique :

```
TÂCHE        : T-XXX — titre de la tâche
FICHIERS     : chemin:ligne, chemin:ligne
CRITÈRES     : un test par critère d'acceptation, avec sa commande
PHASE DE TEST : unitaire / fonctionnel / non fonctionnel / intégration / non régression
ÉCHECS       : aucun — ou la liste complète, avec sa cause
SUIVI        : suivi.json → T-XXX « en_revue », journal horodaté
```

Ne déclarez jamais une tâche terminée sans avoir rendu ce rapport complet.
