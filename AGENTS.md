# Règles permanentes — agentic-mdi

Ces règles s'appliquent à toute session, à tout agent, sous-agent compris.

---

## 1. Point d'entrée unique

Tout travail passe par `orchestrateur`, seul agent principal. `build` et
`plan` sont désactivés. L'utilisateur ne parle pas directement à un
sous-agent pour un travail de production : le sous-agent renvoie vers
l'orchestrateur.

## 2. Le cahier des charges est l'entrée

Un projet commence par un document dans `CDC/`. L'orchestrateur le traduit en
`CONTEXT.md`, source de vérité partagée. Tant que `CDC/` est vide,
`CONTEXT.md` reste un gabarit et aucune tâche réelle ne peut être découpée.
Ne jamais inventer un cahier des charges.

## 3. Aucun travail sans critère de vérification

Une tâche porte **trois à sept critères d'acceptation** vérifiables, écrits
avant l'exécution. Un critère décrit un comportement observable, jamais une
intention. « Correctement », « proprement », « de façon robuste » sont
interdits dans un critère.

Aucun agent n'affirme un résultat sans commande exécutée. Un succès sans preuve
est traité comme un échec.

## 4. Aucune validation sans phase de test

Cinq volets, dans cet ordre : **unitaire**, **fonctionnel**, **non
fonctionnel**, **intégration**, **non régression**. Détail dans la compétence
`tests`.

- Un volet absent sans motif consigné est une non-conformité.
- Un test qui ne peut pas échouer ne prouve rien.
- Aucun test n'est supprimé ni affaibli pour faire passer l'ensemble.
- Une valeur non fonctionnelle est un chiffre ou un niveau, jamais une
  intention.

## 5. Deux portes de revue, dans l'ordre

1. **Conformité** : fait-elle exactement ce qui est demandé, ni plus ni moins ?
2. **Qualité** : six axes — exactitude, sécurité, maintenabilité, tests,
   performance, conformité aux conventions.

`code-reviewer` n'a aucun droit d'écriture sur le code. Il constate. La
correction revient à `developpeur`. Seul l'orchestrateur écrit `validee`.

## 6. Le suivi est obligatoire et horodaté

`suivi.json` est la mémoire du projet. Mis à jour à chaque création de tâche,
chaque changement de statut, chaque clôture de porte, chaque décision, chaque
blocage.

- Horodatage ISO 8601 en temps universel, obtenu par commande :
  `date -u +%Y-%m-%dT%H:%M:%SZ`. Jamais de mémoire.
- L'historique s'ajoute, il ne se réécrit jamais.
- Le décompte correspond exactement au décompte des statuts.
- JSON valide après chaque écriture, vérifié par `jq -e . suivi.json`.
- `opencode.json` est en JSON strict, comme `suivi.json`. Le vérifier par
  `jq -e . opencode.json`, et par `opencode debug config` pour la résolution
  réelle des valeurs.

Quand l'utilisateur revient, ce fichier répond en trois lignes : **qu'est-ce
qui a été validé, quand, et sur quelle preuve ?**

## 7. Périmètre strict des agents

| Agent | Écrit | Ne touche jamais |
| --- | --- | --- |
| `orchestrateur` | `CONTEXT.md`, `suivi.json`, `CDC/` | Le code applicatif |
| `developpeur` | Les fichiers de la tâche, `suivi.json` | Configuration et infrastructure |
| `code-reviewer` | `suivi.json`, rien d'autre | Le code, les tests, la configuration |
| `devops` | Configuration, infrastructure, conteneurs, `suivi.json` | Le code métier |

Un sous-agent qui déborde signale le besoin, il ne l'exécute pas.

## 8. Compétences étroites

Chaque agent ne voit que ses compétences, par restriction de permission. Ne pas
élargir : le contexte d'un agent est un budget, pas un entrepôt.

## 9. Zéro fuite de credentials

Seuls les credentials externes sont protégés : jetons d'interface, jetons
d'accès, mots de passe, clés privées, certificats, clés de coffre, secrets de
connexion, identifiants, chaînes de connexion authentifiées.

1. **Jamais** de valeur réelle en clair : ni fichier, ni commande, ni journal,
   ni rapport, ni message de commit, ni mémoire de projet.
2. **Jamais** de lecture d'un fichier de credentials pour en extraire la
   valeur : `.env`, `.env.*`, `*.pem`, `*.key`, `*.p12`, `id_rsa`, `.netrc`,
   `.npmrc`, `.pypirc`, `credentials*`, `secrets*`.
3. **Toujours** un gabarit : `export GH_TOKEN="<VOTRE_TOKEN_GITHUB>"`,
   `"apiKey": "{env:MA_CLE_API}"`, `password : <A_COMPLETER>`.
4. **Avant chaque commit**, exécuter `./check-secrets.sh`. Code retour 1 bloque
   le commit : corriger, rejouer, puis committer.
5. Un secret déjà écrit dans un dépôt reste compromis après suppression. Le
   signaler et demander la rotation au propriétaire.

Le `devops` est le seul agent habilité à tenir cette frontière.

## 10. Confidentialité

Un dépôt est **privé** par défaut. Ne jamais basculer vers le public, ne pas
retirer le distant, ne pas ajouter de dépôt public en secondaire. Le partage
de session est désactivé. Aucune donnée réelle de client dans les livrables :
uniquement des environnements de laboratoire.

## 11. Versionnement strict

- Une unité de travail par commit. Jamais de commit fourre-tout.
- Seuil dur : trois fichiers modifiés, puis commit.
- Avant chaque commit, dans cet ordre :
  ```
  git status
  git diff --cached --stat
  ./check-secrets.sh
  git commit -m "Prefixe: description courte"
  ```
- Un contrôle en échec bloque le commit. Aucune exception.
- Message court, descriptif, en français.
- `suivi.json` est mis à jour dans le **même** commit que la modification
  qu'il enregistre.
- Interdits absolus : `--force`, `--force-with-lease`, réécriture d'historique,
  `git checkout .`, `git reset --hard`, `git clean -fd` pour effacer un
  travail. On commite ou on met de côté, jamais on détruit.

## 12. Qualité du français

Tout est rédigé en français : prompts, documentation, rapports, messages de
commit, réponses.

- Français soutenu : phrases courtes, sujet explicite, verbes d'action.
- Accents obligatoires. Un mot sans accent est un défaut.
- Aucun terme technique non expliqué : le terme est conservé, mais traduit au
  moins une fois.
- Anglicismes de confort remplacés quand un équivalent existe : « scanner »
  devient « sonder », « log » devient « journal », « output » devient
  « sortie », « check » devient « vérifier », « setup » devient
  « configuration ». Les noms propres d'outils restent autorisés.
- Guillemets français, apostrophe typographique, tiret cadratin pour les
  incises.
- Aucune phrase inachevée, aucun caractère étranger.
- Les sorties d'outil ne sont jamais recopiées telles quelles : les
  reformuler.

## 13. Vocabulaire imposé

Le vocabulaire des livrables est **LAB** (laboratoire), jamais « TP ». Cette
terminologie s'applique à tout : contenu, titres, tableaux, messages de
commit, réponses.

## 14. Économie de jetons

Le contexte est la ressource la plus rare. Ces règles ne sont pas des
recommandations, ce sont des interdits.

| Interdit | Faire |
| --- | --- |
| Lire un fichier entier pour y chercher une ligne | `grep` d'abord, `read` avec `offset` et `limit` |
| Lister un dossier pour savoir ce qu'il contient | Un motif de fichier, moins coûteux |
| Relire un fichier déjà lu | Mémoriser l'emplacement, citer le chemin |
| Explorer « pour voir » | Explorer pour répondre à une question posée |
| Charger une compétence « au cas où » | La charger au moment où elle sert |
| Recopier une sortie d'outil dans un livrable | La reformuler, garder l'essentiel |
| Enchaîner plus de trois tâches sans rendre compte | Rendre compte, puis continuer |

Regrouper les appels d'outils liés en une seule fois. Ne jamais déléguer en
cascade : un sous-agent ne lance pas de sous-agent.
