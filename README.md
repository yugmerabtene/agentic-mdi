# agentic-mdi

Système agentique opencode. Un orchestrateur reçoit un cahier des charges, en
fait un contexte de travail, découpe en tâches vérifiables, délègue à trois
sous-agents, et tient un suivi horodaté. Un seul modèle pour tout :
`opencode/big-pickle`.

## Démarrer

1. Déposer le cahier des charges dans `CDC/`.
2. Ouvrir opencode à la racine. L'orchestrateur est déjà sélectionné.
3. Dire : **« démarre le projet »**. Rien d'autre à taper.

Revenir plus tard ? Dire **« où en est le projet »**.

## Les quatre agents

| Agent | Rôle | Écrit |
| --- | --- | --- |
| `orchestrateur` | Seul agent principal. Cadrage, découpage, délégation, arbitrage | `CONTEXT.md`, `suivi.json`, `CDC/` |
| `developpeur` | Implémente une tâche bornée et la prouve | Le code de la tâche, `suivi.json` |
| `code-reviewer` | Deux portes de revue. Constate, ne corrige pas | `suivi.json` uniquement |
| `devops` | Chaîne, conteneurs, infrastructure, déploiement, surveillance, **secrets** | Configuration et infrastructure |

`build` et `plan` sont désactivés : rien ne contourne l'orchestrateur.

## Fichiers

| Fichier | Rôle |
| --- | --- |
| `AGENTS.md` | Règles permanentes du dépôt, lues à chaque tour |
| `CONTEXT.md` | Contexte de travail, traduit du cahier des charges |
| `suivi.json` | Suivi horodaté : tâches, statuts, décisions, blocages, journal |
| `CDC/` | Le cahier des charges, déposé par vous, jamais modifié |
| `opencode.json` | Configuration opencode, en JSON strict, lisible par `jq` |
| `check-secrets.sh` | Contrôle anti-fuite, obligatoire avant chaque commit |
| `.opencode/agents/` | Les quatre agents |
| `.opencode/skills/` | Les sept compétences |

## Les sept compétences

| Compétence | Pour qui | Contenu |
| --- | --- | --- |
| `common` | tous | Preuve obligatoire, zéro fuite de secrets, qualité du français, économie de jetons, versionnement |
| `cadrage` | `orchestrateur` | Du cahier des charges au contexte, puis au découpage en tâches |
| `developpement` | `developpeur` | Comprendre, concevoir, écrire, prouver, livrer |
| `tests` | tous | Phase de test à cinq volets |
| `revue` | `code-reviewer` | Deux portes, six axes, audit de sécurité |
| `ops` | `devops` | Chaîne d'exploitation et gestion des secrets |
| `suivi` | tous | Tenir `suivi.json`, horodatage, décompte, historique |

Chaque agent ne voit que les compétences qui lui servent, et `common` est la
seule que tout le monde charge. La restriction est dans la configuration, pas
dans la prose : le modèle ne charge pas ce qu'il ne peut pas voir.

## Les cinq volets de test

Aucune tâche n'est validée sans phase de test complète.

| Volet | Objet | Seuil |
| --- | --- | --- |
| Unitaire | Une unité isolée | Branches critiques et cas limites couverts |
| Fonctionnel | Le besoin de bout en bout | Un test par critère d'acceptation |
| Non fonctionnel | Latence, charge, sécurité, reprise | Un chiffre mesuré par exigence |
| Intégration | Les frontières du projet | Une frontière = un test, succès et échec |
| Non régression | Le comportement d'avant | Suite complète à zéro échec |

Un volet sauté sans motif consigné est une non-conformité, pas une économie.

## Les secrets

Le `devops` tient la frontière entre le public et le privé. Le `developpeur`
écrit le code qui lit une variable d'environnement, le `code-reviewer`
constate une fuite.

```bash
./check-secrets.sh    # code 0 : dépôt propre
```

Trois barrières : contrôle local avant commit, accroche `pre-push`, contrôle
dans la chaîne d'intégration continue sur l'historique complet. Un secret
déjà poussé se révoque et se fait tourner, il ne se réécrit pas.

## Reprendre le système ailleurs

Copier `opencode.json`, `AGENTS.md`, `CONTEXT.md`, `suivi.json`,
`check-secrets.sh`, `.gitignore`, `CDC/`, `.opencode/agents/` et
`.opencode/skills/`. Rien d'autre.
