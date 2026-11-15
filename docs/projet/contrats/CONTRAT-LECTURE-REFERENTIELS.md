# Contrat de lecture des référentiels matériel et barèmes

**15 novembre 2026 · En test.** Routes et sérialisation confirmées dans le code public Loxya, révision `2ce970474d8bd76e0f9da739c10fb33eb445e587`. Recette sur instance SaaS à réaliser.

## Catégories

`GET /api/categories` retourne une liste JSON racine de catégories avec `id`, `name` et `sub_categories`. Les sous-catégories exposent `id`, `name` et `category_id`. Les lignes de matériel en événement et les fiches catalogue exposent des IDs associés. `categories()` donne accès aux libellés et liens, sans les reconstruire.

## Dégressivité

`GET /api/degressive-rates` renvoie une liste JSON racine de barèmes avec `id`, `name`, `is_used` et `tiers`. Les paliers contiennent `from_day`, `is_rate` et `value`.

Le modèle `DegressiveRate::serialize()` peut ajouter un palier virtuel à partir du premier jour (100 %) si aucun premier palier n'est stocké au jour 1. Le retour HTTP n'est donc pas toujours une image exacte de la table SQL `degressive_rate_tiers`. La route renvoie 404 lorsque le mode facturation est désactivé.

Le barème catalogue (`material.degressive_rate_id`) et le coefficient éventuellement contextualisé sur une ligne d'événement (`degressive_rate`) sont des informations distinctes. Le SDK ne calcule ni coefficient, ni facture, ni agrégat.

**Recette SaaS à conduire :** libellés des catégories et sous-catégories, liens IDs matériel, barème avec plusieurs paliers, valeur fixe versus pourcentage, palier virtuel, barème absent, facturation désactivée, droits restrictifs. Pas de données métier réelles versionnées.
