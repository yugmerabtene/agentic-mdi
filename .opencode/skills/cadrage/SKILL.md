---
name: cadrage
description: Transforme le cahier des charges du dossier CDC/ en contexte de travail CONTEXT.md, puis découpe le travail en tâches. À utiliser au démarrage d'un projet, lorsque le dossier CDC/ change, ou lorsqu'il est plus récent que le contexte.
---

# Cadrage

Commencez par charger la compétence `common`. Cette compétence a pour objet de
transformer un cahier des charges en un contexte de travail partagé, puis en
tâches exécutables.

## Marche à suivre

1. **Lisez tous les fichiers du dossier `CDC/`, en entier.** Aucun fichier ne
   doit être ignoré, même s'il paraît secondaire.
2. **Relevez ce que le cahier des charges ne tranche pas.** Ne comblez
   jamais ces trous par déduction : consignez-les comme zones d'ombre, et
   parlez-en à l'utilisateur.
3. **Remplissez les dix sections de `CONTEXT.md`**, décrites dans le tableau
   ci-dessous.
4. **Découpez le travail en tâches.** Chacune comporte trois à sept critères
   d'acceptation, et chaque critère décrit un comportement observable.
5. **Inscrivez ces tâches dans `suivi.json`**, puis horodatez.
6. **Ouvrez un blocage** si un point non tranché empêche d'avancer, en
   indiquant l'action attendue, puis signalez-le à l'utilisateur.

## Les dix sections de `CONTEXT.md`

| Section | Ce qu'elle doit contenir |
| --- | --- |
| 1. Finalité | Une seule phrase : à qui cela sert, et pour quoi faire. |
| 2. Périmètre | Les cas d'usage couverts, et les frontières à ne pas franchir. |
| 3. Hors périmètre | Ce qui est exclu, et ce qui est renvoyé à une version ultérieure. |
| 4. Contraintes imposées | Ce qui n'est pas négociable, exprimé en termes mesurables. |
| 5. Choix techniques | Chaque arbitrage non imposé, avec sa justification. |
| 6. Conventions | Arborescence, nommage, langue, gestion des erreurs, tests, versionnement. |
| 7. Jalons | Une ligne par jalon, avec son critère de fin vérifiable. |
| 8. Critères d'acceptation | Ce que l'utilisateur doit observer, en trois à sept points. |
| 9. Risques et zones d'ombre | Chaque point identifié, avec son urgence. |
| 10. Traçabilité | Chaque exigence du cahier des charges reliée à une tâche. |

## Ce qui est interdit

- **Un critère d'acceptation qui décrit une intention.** Les mots
  « correctement », « proprement » et « de façon robuste » n'acceptent aucune
  preuve, donc ils ne sont pas des critères.
- **Un choix technique sans justification ni alternative écartée.** Une
  décision qui n'explique pas pourquoi a été prise plutôt qu'une autre n'est
  pas une décision, c'est un hasard.
- **Un point non tranché comblé par déduction.** Le contexte doit refléter ce
  qui a été décidé, pas ce que vous supposez.
