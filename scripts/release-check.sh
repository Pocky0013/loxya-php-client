#!/usr/bin/env bash
set -euo pipefail

# Controle local et sans effet de bord des fichiers d'une future version.
version="${1:-}"
if [[ ! "$version" =~ ^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
    echo "Version invalide : utiliser vMAJEUR.MINEUR.CORRECTIF (ex. v0.3.0)." >&2
    exit 1
fi

number="${version#v}"
notes="docs/RELEASE-NOTES-${version}.md"
if [[ ! -s "$notes" ]]; then
    echo "Notes de version absentes ou vides : $notes" >&2
    exit 1
fi

if [[ ! -f CHANGELOG.md ]]; then
    echo "CHANGELOG.md introuvable." >&2
    exit 1
fi

escaped_number="${number//./\\.}"
if ! grep -Eq "^## \[$escaped_number\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$" CHANGELOG.md; then
    echo "Ajouter dans CHANGELOG.md une section datee : ## [$number] - AAAA-MM-JJ" >&2
    exit 1
fi

if ! grep -Fq "Loxya PHP Client $version" "$notes"; then
    echo "Les notes doivent identifier clairement Loxya PHP Client $version." >&2
    exit 1
fi

if head -n 8 "$notes" | grep -Eiq 'notes candidates|en préparation|tag.{0,45}(non publi|à vérifier)'; then
    echo "Finaliser l'en-tete des notes de version avant publication." >&2
    exit 1
fi

echo "Fichiers de publication verifies pour $version : CHANGELOG.md et $notes."
