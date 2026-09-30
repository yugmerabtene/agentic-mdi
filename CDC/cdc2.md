# Cahier des Charges : Agent IA Autonome « Micro-Projets »

## 1. Résumé Exécutif

**Objectif** : Concevoir un agent IA autonome capable de réfléchir à des idées de micro-projets logiciels, de les développer intégralement, puis de les publier automatiquement sur GitHub, à raison d'un projet toutes les 5 minutes.

**Environnement d'exécution** : Container Docker avec accès aux API externes (Groq pour l'IA, GitHub pour le versioning).

**Contrainte majeure** : Utilisation du tier gratuit de Groq, imposant des limites strictes de tokens et de requêtes.

---

## 2. Spécifications Fonctionnelles

### 2.1 Boucle Principale de l'Agent

L'agent doit implémenter un cycle autonome structuré comme suit :

```
Démarrage → Attendre 5 minutes → Phase de Réflexion (Idée) → Phase de Développement (Code) → Phase de Publication (GitHub) → Boucler
```

Chaque phase doit être atomique : en cas d'échec, l'agent doit logger l'erreur et passer au cycle suivant sans bloquer indéfiniment.

### 2.2 Phase de Réflexion (Idée de Projet)

**Mission** : Générer une idée de micro-projet réalisable en moins de 5 minutes par un LLM.

**Contraintes imposées** :
- **Taille du projet** : Maximum 3 fichiers (par exemple `main.py`, `requirements.txt`, `README.md`).
- **Langage** : Python (recommandé pour la rapidité de développement).
- **Complexité** : Niveau débutant à intermédiaire. Exemples : calculateur de BMI, générateur de nombres premiers, convertisseur de devises, jeu de devinettes .
- **Unicité** : L'agent doit éviter de reproduire un projet déjà généré. Un fichier de registre local (`projects_history.json`) doit tracer les noms et descriptions des projets passés.

**Prompt système suggéré** :
```
Tu es un générateur d'idées de micro-projets Python. Génère UNE idée de projet respectant strictement ces critères :
- Maximum 3 fichiers
- Code total < 150 lignes
- Réalisable en 5 minutes par un développeur
- Sans dépendances externes complexes
- Utile ou éducatif

Réponds UNIQUEMENT par un JSON avec : {"nom": "...", "description": "...", "fichiers": ["..."], "instructions": "..."}
```

### 2.3 Phase de Développement (Génération de Code)

**Mission** : Transformer l'idée en code fonctionnel.

**Stratégie d'optimisation des tokens** :
- Demander à Groq de générer **un seul fichier à la fois** par requête.
- Pour chaque fichier, fournir uniquement le contexte de l'idée (pas de l'historique complet).
- Utiliser le modèle `llama-3.1-8b-instant` qui offre 14 400 requêtes/jour et 6 000 tokens/minute , largement suffisant pour des micro-projets.

**Prompt système suggéré** :
```
Tu es un développeur Python. Génère le code COMPLET du fichier {nom_fichier} pour le projet suivant :
{description}

Règles :
- Code propre, commenté en français
- Inclure les tests unitaires basiques si pertinent
- Ne pas inclure de blocs markdown, juste le code brut
```

### 2.4 Phase de Publication (GitHub)

**Mission** : Créer un repository GitHub et y pousser les fichiers générés.

**Prérequis** :
- Un Personal Access Token (PAT) GitHub avec les scopes `repo` .
- La bibliothèque `PyGithub` installée dans le container.

**Workflow** :
1. Créer un repository nommé `micro-projet-{timestamp}-{nom-projet}`.
2. Créer les fichiers via l'API GitHub.
3. Faire un commit initial avec un message descriptif.
4. Optionnel : Créer une branche `dev` et une pull request pour simuler un workflow réaliste.

---

## 3. Spécifications Techniques

### 3.1 Architecture Docker

Le projet sera composé des fichiers suivants :

```
projet/
├── Dockerfile
├── docker-compose.yml
├── .env.example
├── requirements.txt
├── agent.py           # Script principal de l'agent
├── config.py          # Configuration et variables
└── projects_history.json  # Registre des projets générés
```

### 3.2 Dockerfile

```dockerfile
FROM python:3.11-slim

WORKDIR /app

# Installation des dépendances
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copie du code
COPY . .

# Lancement de l'agent
CMD ["python", "-u", "agent.py"]
```

### 3.3 Docker Compose

```yaml
version: '3.8'

services:
  agent:
    build: .
    container_name: micro-projets-agent
    restart: unless-stopped
    env_file:
      - .env
    volumes:
      - ./projects_history.json:/app/projects_history.json
    environment:
      - GROQ_API_KEY=${GROQ_API_KEY}
      - GITHUB_TOKEN=${GITHUB_TOKEN}
      - CYCLE_INTERVAL=300  # 5 minutes en secondes
```

### 3.4 Variables d'Environnement (.env.example)

```bash
# Clé API Groq (gratuite sur console.groq.com)
GROQ_API_KEY=<VOTRE_CLE_API_GROQ>

# Token GitHub (Settings → Developer settings → Personal access tokens)
GITHUB_TOKEN=<VOTRE_TOKEN_GITHUB>

# Intervalle entre les cycles (en secondes)
CYCLE_INTERVAL=300
```

#### Où obtenir ces deux secrets

- La clé d'interface Groq se crée sur `console.groq.com`, dans la rubrique des clés d'API, et se remplace depuis la même page.
- Le jeton d'accès GitHub se crée dans les paramètres du compte, à la rubrique « Developer settings », puis « Personal access tokens », avec la portée `repo` indispensable pour publier les dépôts.
- Aucun de ces deux secrets ne doit être écrit dans un fichier versionné : ils se déclarent dans un fichier `.env` local, écarté par Git, dont `CDC/cdc2.env.example` fournit le modèle.

### 3.5 Dépendances (requirements.txt)

```
groq>=0.4.0
PyGithub>=2.1.0
python-dotenv>=1.0.0
```

---

## 4. Gestion des Limites Groq

### 4.1 Contraintes du Tier Gratuit

| Modèle | Requêtes/jour | Tokens/minute | Recommandation |
|--------|---------------|---------------|----------------|
| Llama 3.1 8B | 14 400 | 6 000 | **À utiliser** (idéal pour micro-projets) |
| Llama 3.3 70B | 1 000 | 12 000 | À éviter (trop limité en requêtes) |
| Llama 4 Scout | 1 000 | 30 000 | Optionnel pour idées complexes |

Le modèle `llama-3.1-8b-instant` est recommandé car il offre le meilleur équilibre entre volume de requêtes et capacité de génération pour des tâches simples .

### 4.2 Stratégie d'Atténuation des Limites

- **Séquençage des appels** : Espacer les appels API de quelques secondes au sein d'un même cycle.
- **Gestion du 429** : Implémenter une logique de retry avec backoff exponentiel en lisant le header `retry-after` .
- **Monitoring des tokens** : Compter les tokens estimés avant chaque appel. Si > 5 000, réduire le contexte.

---

## 5. Structure du Code (Agent)

### 5.1 Fichier `config.py`

```python
import os
from dotenv import load_dotenv

load_dotenv()

GROQ_API_KEY = os.getenv("GROQ_API_KEY")
GITHUB_TOKEN = os.getenv("GITHUB_TOKEN")
CYCLE_INTERVAL = int(os.getenv("CYCLE_INTERVAL", "300"))

MODEL_ID = "llama-3.1-8b-instant"
GITHUB_USERNAME = "votre-username"  # À configurer
```

### 5.2 Fichier `agent.py` (Squelette)

```python
import time
import json
import os
from datetime import datetime
from groq import Groq
from github import Github
from config import *

# Initialisation des clients
groq_client = Groq(api_key=GROQ_API_KEY)
github_client = Github(GITHUB_TOKEN)

def reflexion():
    """Génère une idée de projet via Groq."""
    prompt = """Génère UNE idée de micro-projet Python.
    Contraintes : max 3 fichiers, < 150 lignes, réalisable en 5 min.
    Réponds en JSON : {"nom": "...", "description": "...", "fichiers": ["..."]}"""
    
    response = groq_client.chat.completions.create(
        model=MODEL_ID,
        messages=[{"role": "user", "content": prompt}],
        max_tokens=500,
        temperature=0.8
    )
    return json.loads(response.choices[0].message.content)

def developpement(idee):
    """Génère le code de chaque fichier."""
    fichiers_code = {}
    for nom_fichier in idee["fichiers"]:
        prompt = f"""Génère le code Python complet pour le fichier {nom_fichier}.
        Projet : {idee['description']}
        Ne retourne QUE le code, sans markdown."""
        
        response = groq_client.chat.completions.create(
            model=MODEL_ID,
            messages=[{"role": "user", "content": prompt}],
            max_tokens=2000
        )
        fichiers_code[nom_fichier] = response.choices[0].message.content
    return fichiers_code

def publication(idee, fichiers_code):
    """Crée le repo et push les fichiers sur GitHub."""
    user = github_client.get_user()
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    repo_name = f"micro-projet-{timestamp}-{idee['nom'].lower().replace(' ', '-')}"
    
    repo = user.create_repo(repo_name, description=idee["description"])
    
    for nom_fichier, contenu in fichiers_code.items():
        repo.create_file(nom_fichier, f"Ajout {nom_fichier}", contenu)
    
    print(f"✅ Publié : https://github.com/{user.login}/{repo_name}")
    return repo_name

def boucle_principale():
    """Boucle infinie de l'agent."""
    print("🤖 Agent démarré. Cycle de 5 minutes.")
    
    while True:
        try:
            print("\n⏳ Prochain cycle dans 5 minutes...")
            time.sleep(CYCLE_INTERVAL)
            
            print("🧠 Réflexion...")
            idee = reflexion()
            print(f"💡 Idée : {idee['nom']}")
            
            print("💻 Développement...")
            code = developpement(idee)
            
            print("📤 Publication...")
            publication(idee, code)
            
        except Exception as e:
            print(f"❌ Erreur cycle : {e}")
            continue

if __name__ == "__main__":
    boucle_principale()
```

---

## 6. Sécurité et Bonnes Pratiques

### 6.1 Gestion des Secrets

- **Ne jamais** hardcoder les tokens dans le code. Utiliser exclusivement des variables d'environnement .
- Ajouter `.env` au `.gitignore`.
- Utiliser des tokens GitHub à durée limitée (fine-grained tokens).

### 6.2 Gestion des Erreurs

- Chaque phase (réflexion, développement, publication) doit être entourée d'un `try/except`.
- Les erreurs 429 (rate limit Groq) doivent déclencher un `time.sleep(retry_after)`.
- Les erreurs GitHub (repo existant, permissions) doivent être loggées mais ne pas arrêter l'agent.

### 6.3 Persistance

Le fichier `projects_history.json` doit être monté en volume pour survivre aux redémarrages du container :
```json
{
  "projets": [
    {"nom": "bmi-calculator", "date": "2026-09-30T10:00:00", "repo": "user/micro-projet-..."}
  ]
}
```

---

## 7. Livrables

1. **Code source complet** de l'agent (`agent.py`, `config.py`).
2. **Dockerfile** et **docker-compose.yml** fonctionnels.
3. **Fichier `.env.example`** documenté.
4. **README.md** expliquant l'installation et le lancement.
5. **Registre `projects_history.json`** initialisé avec un tableau vide.

---

## 8. Contraintes et Limites Connues

| Contrainte | Impact | Mitigation |
|------------|--------|------------|
| Rate limit Groq (6k TPM) | Cycles espacés de 5 min | Utiliser Llama 3.1 8B, prompts courts |
| GitHub API rate limit | Création repo + fichiers | Attendre entre les commits si nécessaire |
| Qualité du code généré | Erreurs possibles | Tests basiques dans le prompt |
| Absence de test runtime | Code non exécuté | Optionnel : exécuter le code dans un sandbox |

---

## 9. Conclusion

Ce cahier des charges décrit un agent IA autonome minimaliste mais fonctionnel. La clé du succès réside dans la **simplicité des projets** et l'**optimisation des appels API** pour respecter les limites du tier gratuit de Groq. Le cycle de 5 minutes est réaliste pour des micro-projets, compte tenu des temps de génération LLM et des contraintes de rate limiting.
