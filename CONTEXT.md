# Contexte de travail — agentic-mdi

Traduit du dossier `CDC/`. Tenu par l'orchestrateur, ne pas modifier à la
main.

> **Gabarit initial.** Le dossier `CDC/` ne contient pas encore de cahier des
> charges. Dès que vous y déposez le vôtre, l'orchestrateur remplace ce
> fichier intégralement selon la compétence `cadrage`.

---

## 1. Finalité

Système agentique opencode pour ce dépôt : un orchestrateur unique qui reçoit
un cahier des charges, en fait un contexte de travail, découpe en tâches
vérifiables, délègue à trois sous-agents spécialisés, et tient un suivi
horodaté. Le système sert l'utilisateur : il doit pouvoir revenir après une
interruption et retrouver en trois lignes l'état exact du projet.

## 2. Périmètre

- Configuration opencode : `opencode.json`, `.opencode/agents/`,
  `.opencode/skills/`.
- Un agent primaire `orchestrateur`, trois sous-agents `developpeur`,
  `code-reviewer`, `devops`.
- Le dossier `CDC/` en entrée, `CONTEXT.md` en sortie de cadrage.
- `suivi.json` comme mémoire horodatée.
- `./check-secrets.sh` comme garde-fou avant commit.
- Un modèle unique pour tous les agents : `opencode/big-pickle`.

## 3. Hors périmètre

- Aucun service externe, aucune connexion réseau pour le fonctionnement du
  système.
- Aucune donnée réelle de client. Les livrables restent des environnements de
  laboratoire.
- L'orchestrateur n'écrit pas de code applicatif.
- Aucune automatisation de test du système lui-même : la validation passe par
  `jq -e .`, un contrôle JSON, et un contrôle de configuration au démarrage.

## 4. Contraintes imposées

| Contrainte | Origine | Vérification |
| --- | --- | --- |
| Un seul modèle, `opencode/big-pickle` | Demande utilisateur | `opencode.json` et les quatre agents |
| Attendre un cahier des charges dans `CDC/` | Demande utilisateur | Compétence `cadrage` |
| Le cahier des charges devient `CONTEXT.md` | Demande utilisateur | Compétence `cadrage` |
| Trois sous-agents imposés | Demande utilisateur | `opencode.json` |
| Un suivi JSON horodaté obligatoire | Demande utilisateur | Compétence `suivi` |
| Phase de test à cinq volets | Demande utilisateur | Compétence `tests` |
| Le `devops` tient les secrets | Demande utilisateur | Compétence `ops` |
| Agents optimisés pour le jeton, sans perdre en justesse | Demande utilisateur | `compaction`, `tool_output`, section 14 de `AGENTS.md` |
| Aucun secret en clair | Règle permanente du poste | `./check-secrets.sh` |
| Vocabulaire **LAB** | Règle permanente du poste | Tous les documents |

## 5. Choix techniques

| Sujet | Décision | Justification | Alternative écartée |
| --- | --- | --- | --- |
| Arborescence plate | Sept fichiers à la racine, agents et compétences seuls dans `.opencode/` | Conforme à la convention de vos quatorze projets existants, et moins de navigation pour l'agent | Un dossier par type de document |
| Point d'entrée | `default_agent: orchestrateur`, `build` et `plan` désactivés | Empêcher toute exécution qui contourne l'orchestrateur | Laisser `build` disponible |
| Permissions dans le JSON, pas dans les agents | Les fichiers d'agent ne portent que le comportement | Une seule source de vérité, pas de conflit entre les deux endroits | Permissions dupliquées dans chaque agent |
| Contrôle des sous-agents | `task` avec `"*": "deny"` puis autorisation nominative | Un refus explicite retire l'outil de la description donnée au modèle, qui cesse de tenter l'appel | Compter sur la consigne textuelle |
| Écriture par motif | `edit` avec `"*": "deny"` puis chemins autorisés | Motif large d'abord, étroit ensuite | Permissions larges avec discipline verbale |
| Suivi | `suivi.json` à la racine | Visible, versionnable, lisible par un humain comme par un agent | Fichier caché ou hors du dépôt |
| Sept compétences, dont une commune | Une compétence par rôle, plus `common` qui porte la preuve, les secrets, le français, les jetons et le versionnement | Le commun est écrit une seule fois et chargé par tout le monde ; chaque rôle n'ajoute que son propre ajout | Une compétence par notion, ou le commun recopié dans chaque compétence |
| Commandes | Aucune | Aucune de vos quatorze configurations n'en utilise | Quatre fichiers de commande |
| Économie de jetons | `tool_output` à 80 lignes, `prune: true`, fenêtre verbatim réduite, `subagent_depth: 1` | Le levier le plus rentable est la troncature des sorties d'outils, pas la compression du prompt | Réduire le nombre d'agents |
| Secrets | Trois barrières : contrôle local, accroche `pre-push`, contrôle en chaîne sur l'historique | Un secret déjà poussé se révoque, il ne se réécrit pas | Un seul contrôle local |
| `opencode.json` | JSON strict, sans commentaire, vérifiable par `jq` | La configuration doit rester vérifiable par un outil standard, et lisible par `jq` comme `suivi.json`. Les commentaires qui avaient été ajoutés ont été retirés sur demande explicite : la lecture se fait par la compétence concernée, pas par des annotations dans le fichier | Un fichier annexe de documentation de la configuration |

## 6. Conventions

- **Arborescence** : tout à la racine, sauf `.opencode/agents/` et
  `.opencode/skills/`.
- **Nommage** : agents en minuscules avec trait d'union, un seul mot
  (`orchestrateur`, `developpeur`, `code-reviewer`, `devops`). Compétences de
  même forme, dossier identique au nom déclaré. Tâches `T-001`, décisions
  `D-001`, blocages `B-001`.
- **Langue** : français partout. Aucun caractère étranger dans un texte
  français.
- **Erreurs** : remontées avec le contexte, jamais avalées. Un rapport sans
  mention d'échec est un rapport faux.
- **Tests** : cinq volets, compétence `tests`.
- **Versionnement** : une unité de travail par commit, message court en
  français. Jamais d'astérisque, jamais de réécriture d'historique.
- **Documentation** : ce qui est décisionnel vit dans `CONTEXT.md`, ce qui
  est horodaté dans `suivi.json`.

## 7. Jalons

| Jalon | Livrable | Critère de fin |
| --- | --- | --- |
| J1 — Socle | `opencode.json`, quatre agents, sept compétences | Configuration valide, quatre agents listés, une seule instruction par agent |
| J2 — Pilotage | `CONTEXT.md`, `suivi.json` | Suivi valide, horodaté, décompté |
| J3 — Garde-fous | `check-secrets.sh`, `.gitignore`, `AGENTS.md` | Le contrôle renvoie `0` sur un dépôt propre, `1` sur un secret |
| J4 — Économie de jetons | `compaction`, `tool_output`, section 14 | Aucun levier désactivé, aucune régression de justesse |
| J5 — Votre projet | Contexte et suivi réels depuis `CDC/` | `CONTEXT.md` remplace le gabarit, `suivi.json` porte de vraies tâches |

## 8. Critères d'acceptation du projet

1. Ouvrir opencode dans ce dépôt sélectionne `orchestrateur` sans intervention.
2. `orchestrateur` est le seul agent primaire dans la barre de tabulation.
3. Les trois sous-agents sont invocables par l'orchestrateur, et toute
   délégation non autorisée est refusée.
4. `code-reviewer` n'a de droit d'écriture que sur `suivi.json` : aucune
   écriture sur le code, les tests ou la configuration.
5. `developpeur` n'a aucun droit de délégation.
6. Chaque agent ne voit que les compétences qui lui sont attribuées.
7. Un cahier des charges déposé dans `CDC/` produit un `CONTEXT.md` complet
   suivant le gabarit de la compétence `cadrage`.
8. `suivi.json` reste valide après chaque écriture et répond à la question
   « qu'est-ce qui a été validé, quand, et sur quelle preuve ? ».
9. `./check-secrets.sh` renvoie `0` sur un dépôt propre et `1` sur un secret,
   sans jamais afficher la valeur.
10. Aucune tâche n'est `validee` sans les cinq volets de test et les deux portes
    de revue.

## 9. Risques et zones d'ombre

| Point | Pourquoi c'est un risque | Arbitrage attendu | Urgence |
| --- | --- | --- | --- |
| `CDC/` ne contient pas encore de cahier des charges | Le contexte reste un gabarit, donc aucune tâche d'un projet réel ne peut être découpée | Déposer le cahier des charges | Haute |
| La section 5 repose sur des choix par défaut, non validés par vous | Le système est calibré sur vos autres projets, mais sur aucun projet réel de cette façon | Valider ou corriger les choix | Haute |
| `opencode/big-pickle` est imposé sans variante mesurée | La qualité peut varier selon la nature de la tâche | Mesurer sur un projet réel | Moyenne |
| La commande de vérification du projet livré est inconnue | `developpeur` ne pourra pas prouver son travail tant qu'elle n'est pas déclarée | Déclarer la commande dans `CONTEXT.md` | Moyenne |
| La troncature des sorties d'outils à 80 lignes peut masquer la fin d'une longue sortie | Le fichier complet est écrit sur disque, mais l'agent doit penser à aller le lire | Vérifier sur un projet réel | Basse |
| Aucun test du système lui-même | Une régression de configuration ne serait vue qu'à l'ouverture d'opencode | Valider la configuration à chaque modification | Basse |

## 10. Traçabilité

| Exigence d'origine | Section | Suivi |
| --- | --- | --- |
| Agent orchestrateur qui délègue | 1, 2 | T-001 |
| Modèle unique `opencode/big-pickle` | 4, 5 | T-001 |
| Transformation du cahier des charges en contexte | 1, 2 | T-005 |
| Sous-agent développeur | 2 | T-001 |
| Sous-agent code-reviewer | 2 | T-001 |
| Sous-agent DevOps | 2 | T-001 |
| Compétences par agent, avec une compétence commune à tous | 2 | T-002 |
| Suivi JSON horodaté | 2, 4 | T-003 |
| Phase de test à cinq volets | 2, 4 | T-004 |
| Le `devops` gère les secrets | 2, 4 | T-006 |
| Agents optimisés pour le jeton | 4, 5 | T-007 |
