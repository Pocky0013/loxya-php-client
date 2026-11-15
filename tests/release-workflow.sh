#!/usr/bin/env bash
set -euo pipefail

# Tests de publication hors reseau et sans Git ; aucun tag n'est cree.
repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
mkdir -p "$work/scripts" "$work/docs/projet/publications"
cp "$repo_dir/scripts/release-check.sh" "$work/scripts/release-check.sh"

cat > "$work/CHANGELOG.md" <<'EOF'
## [Unreleased]

## [0.3.0] - 2026-11-14
- Modifications fictives.
EOF
cat > "$work/docs/projet/publications/RELEASE-NOTES-v0.3.0.md" <<'EOF'
# Loxya PHP Client v0.3.0
Notes de publication fictives.
EOF

(cd "$work" && bash scripts/release-check.sh v0.3.0 >/dev/null)

for version in 0.3.0 v0.03.0 v0.3 v0.3.0-extra v0.3.1; do
    if (cd "$work" && bash scripts/release-check.sh "$version" >/dev/null 2>&1); then
        echo "Validation abusive du nom de version : $version" >&2
        exit 1
    fi
done

printf '## [0.3.0] - 2026-11-14\n' > "$work/CHANGELOG.md"
printf '# Notes candidates Loxya PHP Client v0.3.0\n' > "$work/docs/projet/publications/RELEASE-NOTES-v0.3.0.md"
if (cd "$work" && bash scripts/release-check.sh v0.3.0 >/dev/null 2>&1); then
    echo "Notes candidates erronees acceptees." >&2
    exit 1
fi

printf '# Loxya PHP Client v0.3.0\n' > "$work/docs/projet/publications/RELEASE-NOTES-v0.3.0.md"
printf '## [Unreleased]\n' > "$work/CHANGELOG.md"
if (cd "$work" && bash scripts/release-check.sh v0.3.0 >/dev/null 2>&1); then
    echo "Version absente du changelog acceptee." >&2
    exit 1
fi

echo "Tests du controle de release reussis (hors reseau)."
