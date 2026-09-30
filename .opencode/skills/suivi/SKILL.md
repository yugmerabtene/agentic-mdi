---
name: suivi
description: Tenir à jour le fichier de suivi horodaté suivi.json — tâches, statuts, décisions, blocages et journal. À utiliser à chaque création de tâche, chaque changement de statut, chaque clôture de porte de revue, et à chaque reprise de session. Transversal : les quatre agents.
---

# Suivi

Chargez d'abord la compétence `common`. Le fichier `suivi.json` est la mémoire
du projet : c'est lui qui répond, après une interruption, à la question « en
où en est-on ? ».

## L'horodatage

- **Le format est ISO 8601 en temps universel**, et il s'obtient par
  commande : `date -u +%Y-%m-%dT%H:%M:%SZ`.
- **Jamais de mémoire, jamais de date devinée.** Une date inventée fausse tout
  l'historique.
- **L'historique s'ajoute, il ne se réécrit jamais.** Une entrée corrigée
  reste visible, avec sa correction.

## Les statuts d'une tâche

```
a_faire → en_cours → en_revue → validee
                                 ↘ rejetee
                     ↘ bloquee     annulee
```

- Seul le `code-reviewer` inscrit son verdict de revue et peut passer une
  tâche à `rejetee`. **Seul l'orchestrateur écrit `validee`** et clôture la
  tâche, après avoir instruit les deux portes.
- **Jamais le développeur sur sa propre tâche.** Il produit la preuve, pas la
  validation.

## Les règles de mise à jour

1. Une tâche porte **trois à sept critères d'acceptation**, des dates, et son
   historique.
2. **Le décompte correspond exactement au décompte des statuts.** Un écart
   entre les deux rend le fichier mensonger.
3. **Le JSON est valide après chaque écriture**, et vous le vérifiez par
   `jq -e . suivi.json`.
4. Chaque décision porte un identifiant, un horodatage, la décision elle-même,
   sa justification, les alternatives écartées, et sa conséquence.
5. Chaque blocage porte sa description, son impact, l'action attendue, et son
   statut.

## À chaque changement

1. Appliquez le changement dans le projet.
2. Mettez `suivi.json` à jour **dans le même versionnement** que la
   modification qu'il enregistre.
3. Vérifiez la validité du JSON.
4. Ajoutez une ligne au journal : horodatage, agent, événement, détail.

## À la reprise d'une session

Ouvrez `suivi.json`, puis répondez en trois lignes :

1. **Qu'est-ce qui a été validé ?**
2. **Quand ?**
3. **Sur quelle preuve ?**

Annoncez ensuite la prochaine action. **Ne supposez rien de l'état
précédent** : le fichier fait foi, et rien d'autre.
