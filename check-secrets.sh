#!/usr/bin/env bash
# Contrôle anti-fuite de secrets avant commit.
# Renvoie 0 si le dépôt est propre, 1 si un secret est détecté.
set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$RACINE" || exit 2

echo "Contrôle anti-fuite — dépôt $(basename "$RACINE")"
echo "=============================================="

# Fichiers et dossiers jamais inspectés : contenu par définition sensible.
EXCLUS="^\./(\.git/|node_modules/|\.venv/|venv/|\.env|.*\.pem$|.*\.key$|.*\.p12$|id_rsa|\.netrc|\.npmrc|\.pypirc$|credentials|secrets)"

# Motifs de secrets factices ou réels.
# Chaque motif est volontairement spécifique : un mot générique comme
# "password" seul produirait des faux positifs sans valeur.
MOTIFS=(
  'AKIA[0-9A-Z]{16}'
  'ghp_[A-Za-z0-9]{36}'
  'gh[pousr]_[A-Za-z0-9]{20,}'
  'github_pat_[A-Za-z0-9_]{40,}'
  'glpat-[A-Za-z0-9_-]{20,}'
  'sk-[A-Za-z0-9]{20,}'
  'sk-proj-[A-Za-z0-9_-]{20,}'
  'xox[baprs]-[A-Za-z0-9-]{10,}'
  'AIza[0-9A-Za-z_-]{35}'
  'eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}'
  '-----BEGIN [A-Z ]*PRIVATE KEY-----'
  'ssh-rsa AAAA[0-9A-Za-z+/]{50,}'
  '(password|passwd|pwd|secret|token|api[_-]?key)[[:space:]]*[:=][[:space:]]*["'"'"']?[A-Za-z0-9!@#$%^&*_+\-]{12,}'
)

# Connexions authentifiees vers une base de donnees.
# Les segments utilisateur et mot de passe excluent < > { } : ces caracteres
# sont la signature d'un gabarit, donc la ligne est saine par construction.
MOTIFS+=('(postgres|postgresql|mysql|mongodb(\+srv)?|redis|amqp)://[^:/[:space:]<>{}]+:[^@[:space:]<>{}"]+@')

CIBLES=()
while IFS= read -r fichier; do
  CIBLES+=("$fichier")
done < <(
  {
    git ls-files 2>/dev/null
    git ls-files --others --exclude-standard 2>/dev/null
  } | sort -u | grep -vE "$EXCLUS"
)

if [ ${#CIBLES[@]} -eq 0 ]; then
  echo "Aucun fichier à contrôler."
  exit 0
fi

TROUVES=0
for motif in "${MOTIFS[@]}"; do
  # -I ignore les fichiers binaires, -n affiche le numéro de ligne.
  resultat="$(grep -rInE "$motif" "${CIBLES[@]}" 2>/dev/null)"
  if [ -n "$resultat" ]; then
    TROUVES=1
    echo
    echo "ALERTE — motif détecté : ${motif:0:40}"
    # Le contenu de la ligne n'est jamais affiché : il contient le secret.
    # Seul le fichier et le numéro de ligne sont signalés.
    printf '%s\n' "$resultat" | cut -d: -f1,2 | sort -u | while IFS= read -r lieu; do
      echo "  $lieu"
    done
  fi
done

echo
if [ "$TROUVES" -eq 0 ]; then
  echo "Résultat : aucun secret détecté dans ${#CIBLES[@]} fichier(s)."
  echo "Contrôle passé."
  exit 0
fi

echo "Résultat : secret détecté. Le commit est bloqué."
echo
echo "À faire :"
echo "  1. Remplacer la valeur par un gabarit, par exemple"
echo "       export GH_TOKEN=\"<VOTRE_TOKEN_GITHUB>\""
echo "       \"apiKey\": \"{env:MA_CLE_API}\""
echo "  2. Relancer ce contrôle, puis committer."
echo "Un secret déjà écrit dans un dépôt reste compromis : la rotation du secret"
echo "côté propriétaire est nécessaire, la suppression ne suffit pas."
exit 1
