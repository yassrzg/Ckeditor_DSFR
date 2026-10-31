# Maintenance

## Tests du bundle

Le bundle a son propre projet de test (`tests/App` : noyau minimal, SQLite, utilisateurs en mémoire, entité `Page`).

```bash
cd packages/ckeditor-dsfr-bundle    # ou la racine du dépôt du bundle
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

Autre base (ex. PostgreSQL) : `CKEDITOR_DSFR_TEST_DATABASE_URL="postgresql://…" vendor/bin/phpunit`.

Couverture : médiathèque (ajout, formats, droits, suppression bloquée, CSRF, ajout depuis l'éditeur), rendu de chaque
composant et de ses variantes, sommaire, filtrage HTML, réglages (droits, vidéos, formats, composants, déplacement des
dossiers), migration (tables manquantes seulement), commande d'installation, valeurs saisies (hôtes, formats, dossiers).

Après toute modification du JavaScript ou de CKEditor : vérifier l'éditeur dans un navigateur (insertion et modification
de composants, médiathèque, ajout d'un fichier, icônes et pictogrammes, bloc « Colonnes » : changement de disposition,
glisser-déposer d'un composant d'une colonne à l'autre, refus d'un composant trop large).

Mise à jour du DSFR dans un projet : le bundle ne fournit pas le DSFR. Un test de l'application hôte peut rendre chaque
composant avec chacune de ses valeurs et vérifier que toute classe `fr-*` produite existe dans le DSFR du projet
(exemple : `tests/Functional/Content/DsfrCatalogueTest.php` de l'application d'origine).

## Mettre à jour CKEditor 5

CKEditor est la version navigateur autonome du paquet npm `ckeditor5` (dossier `dist/browser`, traduction
`dist/translations/fr.js`), copiée dans `assets/vendor/ckeditor5/` : aucun appel extérieur (intranet).

1. Télécharger une version publiée depuis au moins deux semaines : `npm pack ckeditor5@<version>`.
2. Remplacer `ckeditor5.js`, `ckeditor5.css`, `translations/fr.js` et `LICENSE.md` ; retirer la ligne `sourceMappingURL`.
3. Lancer les tests, puis dans chaque projet : `php bin/console asset-map:compile` et vérification dans le navigateur.

SortableJS (`assets/vendor/sortablejs/sortable.js`, version ESM) se met à jour de la même façon.

## Publier le bundle dans son propre dépôt Git

1. Créer le dépôt (ex. GitLab `groupe/ckeditor-dsfr-bundle`) et y pousser le contenu de ce dossier (le `.gitignore`
   exclut `vendor/`, `var/` et `composer.lock`).
2. Étiqueter une version : `git tag v1.0.0 && git push --tags` (versionnage sémantique : une rupture de compatibilité
   — route, nom de bloc, option, format enregistré — impose une version majeure).
3. Dans chaque projet, remplacer le dépôt « path » par le dépôt Git :

```json
"repositories": {
    "ckeditor-dsfr-bundle": { "type": "vcs", "url": "https://gitlab.exemple.fr/groupe/ckeditor-dsfr-bundle.git" }
}
```

```bash
composer require yassrzg/ckeditor-dsfr-bundle:^1.0
```

puis supprimer la copie locale du bundle (`packages/ckeditor-dsfr-bundle`).

## Bibliothèques front-end

Vérifié sur les fichiers fournis (aucun npm, Node.js, Webpack Encore ni Vite) :

| Bibliothèque | Version | Licence | Format | Chargement |
| --- | --- | --- | --- | --- |
| CKEditor 5 | 49.0.0 | GPL-2.0-or-later (`licenseKey: 'GPL'`) ou commerciale | module ES unique (`ckeditor5.js`, sans dépendance), traduction `translations/fr.js`, `ckeditor5.css` | importmap, chemins du bundle (`ckeditor-dsfr:install`) |
| SortableJS | 1.15.7 | MIT | module ES (`sortable.esm.js` regroupé par jsDelivr) | importmap ; la copie du projet est réutilisée si `sortablejs` y est déjà déclaré |
| DSFR | 1.15.x (testé 1.15.3) | Etalab 2.0, usage réservé aux sites de l'État | fichiers du projet (`dist` de `@gouvfr/dsfr`) | `<link>` et `dsfr.module.min.js` chargés par le projet ; le bundle ne fait que lire icônes et pictogrammes |

- CKEditor 5 se charge directement par l'importmap car la distribution `ckeditor5` est un module ES autonome ; une version
  en plusieurs fichiers (`@ckeditor/ckeditor5-*`) demanderait de déclarer chaque paquet et n'est pas utilisée.
- Les feuilles de style ne sont jamais importées depuis JavaScript : AssetMapper les transformerait en modules `data:`,
  bloqués par une politique de sécurité (CSP) `script-src 'self'`.

## Compatibilité

- Format enregistré (`data-component`, `data-*`, `data-slot`) : stable ; un composant ou un réglage ne se renomme pas
  (un ancien format reste lu, ex. `data-media-id` d'un ancien composant de téléchargement).
- Routes, blocs Twig (`ckeditor_dsfr_title`, `ckeditor_dsfr_content`), options de configuration et noms des réglages en
  base : changement = version majeure, documentée dans `CHANGELOG.md`.
