---
name: tests
description: Phase de test obligatoire en cinq volets — unitaire, fonctionnel, non fonctionnel, intégration, non régression — avec les seuils de passage, les commandes de preuve et le rapport attendu. À utiliser pour toute tâche qui crée ou modifie un comportement, et à chaque porte de revue sur l'axe « Tests ».
---

# Phase de test

Commencez par charger la compétence `common`. Aucune tâche ne peut être
validée sans avoir passé cette phase complète.

## Pourquoi cinq volets

Un test qui passe ne prouve pas que le code est juste : il prouve qu'un
ensemble de cas a été vérifié. Les cinq volets ci-dessous couvrent cinq
questions différentes. Un volet manquant laisse une zone entière sans preuve,
que rien d'autre ne rattrape.

## Les cinq volets, dans cet ordre

| Volet | Question à laquelle il répond | Seuil de passage |
| --- | --- | --- |
| **Unitaire** | Chaque unité de code fonctionne-t-elle isolément ? | Les branches critiques et les cas limites sont couverts |
| **Fonctionnel** | Le besoin est-il satisfait de bout en bout ? | Au moins un test par critère d'acceptation |
| **Non fonctionnel** | Le comportement tient-il en conditions réelles ? | Un chiffre mesuré pour chaque exigence |
| **Intégration** | Les frontières du système dialoguent-elles correctement ? | Une frontière = un test, sur le succès et sur l'échec |
| **Non régression** | Le comportement d'avant est-il intact ? | La suite complète passe à zéro échec |

## Règles à ne pas enfreindre

- **Un volet absent est une non-conformité**, pas une économie de temps. Si
  vous ne pouvez pas l'exécuter, déclarez-le absent et consignez le motif.
- **Un test qui ne peut pas échouer ne prouve rien.** Vérifiez qu'un test
  échoue bien quand vous cassez le code : sinon il ne teste pas.
- **Aucun test n'est supprimé ni affaibli** pour faire passer l'ensemble de la
  suite.
- **Une valeur non fonctionnelle est un chiffre ou un niveau**, jamais une
  intention. « Performant » ne veut rien dire ; « 120 ms au 95ᵉ centile pour
  un seuil de 200 ms » veut tout dire.
- **Le rapport nomme la commande et son résultat réel** pour chaque volet. Une
  affirmation sans commande exécutée est traitée comme un échec.

## Modèle de rapport

```
PHASE DE TEST — T-XXX
UNITAIRE        : 42 tests, 0 échec — branches critiques et cas limites
FONCTIONNEL     : 9 tests, 0 échec — un test par critère d'acceptation
NON FONCTIONNEL : latence au 95ᵉ centile 120 ms, seuil 200 ms ; 0 erreur sur 1 000 requêtes
INTÉGRATION     : 4 tests, 0 échec — base de données, disque, file d'attente
NON RÉGRESSION  : suite complète, 128 tests, 0 échec
VOLETS ABSENTS  : aucun — ou le motif consigné
```

Un volet qui n'a pas été exécuté se déclare absent, avec son motif. Il ne se
remplace jamais par une affirmation.
