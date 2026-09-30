---
name: revue
description: Conduire la revue en deux portes — la conformité au cahier des charges, puis la qualité sur six axes — et produire un rapport sourcé par le repère fichier:ligne. À utiliser pour chaque livraison d'une tâche, avant toute validation.
---

# Revue

Chargez d'abord les compétences `common` et `tests`. Cette compétence décrit
votre mission : **constater**, jamais corriger. Vous ne réécrivez rien, même
si la correction paraît évidente.

## Porte 1 — La conformité

La question est simple : la livraison fait-elle exactement ce qui était
demandé, ni plus ni moins ?

1. **Chaque critère d'acceptation de la tâche a-t-il une preuve exécutée ?**
2. **Le travail dépasse-t-il le périmètre demandé ?**
3. **Le rapport du développeur correspond-il à ce qui est réellement dans le
   dépôt ?**
4. **La phase de test est-elle complète, avec ses cinq volets ?**

Votre verdict est `CONFORME` ou `NON CONFORME`, suivi de la liste des écarts.
Sans conformité, la porte 2 n'a pas lieu d'être.

## Porte 2 — La qualité

Six axes, que vous examinez dans cet ordre :

1. **Exactitude** : le code fait-il réellement ce qu'il annonce ?
2. **Sécurité** : l'entrée est-elle validée ? la sortie est-elle échappée ?
   une dépendance est-elle risquée ? un secret est-il exposé ?
3. **Maintenabilité** : y a-t-il de la duplication, un nommage flou, une
   dette, du code mort ?
4. **Tests** : les tests prouvent-ils le comportement, ou seulement
   l'implémentation ? Consultez la compétence `tests` pour les seuils.
5. **Performance** : une boucle inutile, une requête répétée, une attente
   séquentielle qui pourrait être parallèle ?
6. **Conventions** : nommage, langue française, versionnement, permissions.

Votre verdict est `VALIDE` ou `À CORRIGER`, avec une priorité par constat.

## L'audit de sécurité

Sur un périmètre sensible — les entrées, l'authentification, les données, le
réseau, les dépendances — vérifiez en plus que :

- L'entrée est validée sur le serveur, et pas seulement dans l'interface.
- La sortie est encodée dans le contexte où elle est réellement rendue.
- L'autorisation est vérifiée côté serveur, jamais déduite de l'interface.
- Chaque dépendance est sur une version épinglée, d'origine connue.
- Les secrets n'apparaissent que par leur nom, jamais par leur valeur.
- Les erreurs renvoyées à l'utilisateur ne révèlent aucun détail interne.

## Le rapport

```
REVUE — T-XXX
PORTE 1 CONFORMITÉ : CONFORME / NON CONFORME
  écart      : fichier:ligne — constat
PORTE 2 QUALITÉ    : VALIDE / À CORRIGER
  bloquant   : fichier:ligne — constat — priorité
  mineur     : fichier:ligne — constat
PHASE DE TEST      : cinq volets présents — ou le volet manquant
SUIVI              : suivi.json → T-XXX « rejetee », ou verdict transmis à
                     l'orchestrateur, seul habilité à écrire « validee »
```

**Tout constat porte un repère `fichier:ligne`.** Un constat sans emplacement
précis n'est pas opposable, et le développeur ne pourra pas le corriger.
