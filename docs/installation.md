# Installation dans un projet

Durée : une quinzaine de minutes. Exemple pour un projet Symfony 7.4 avec AssetMapper, Stimulus, Doctrine et le DSFR.

## 1. Prérequis du projet

```bash
composer require symfony/asset-mapper symfony/stimulus-bundle doctrine/doctrine-bundle doctrine/doctrine-migrations-bundle
```

Le DSFR doit être présent dans les fichiers du projet (icônes et pictogrammes proposés dans l'éditeur), par défaut dans
`assets/dsfr` (contenu du dossier `dist` du paquet npm `@gouvfr/dsfr`), et chargé par les pages (`dsfr.min.css`,
`utility/icons/icons.min.css`, `dsfr.module.min.js`). Un autre dossier se règle avec `ckeditor_dsfr.dsfr` (voir
[configuration.md](configuration.md)).

## 2. Installer le paquet

Tant que le bundle n'a pas de dépôt Git, il s'installe depuis un dossier (dépôt Composer « path »). Copier le dossier du
bundle dans le projet (ex. `packages/ckeditor-dsfr-bundle`), puis dans `composer.json` :

```json
"repositories": {
    "ckeditor-dsfr-bundle": {
        "type": "path",
        "url": "packages/ckeditor-dsfr-bundle",
        "options": { "symlink": true, "relative": true }
    }
}
```

```bash
composer require "yassrzg/ckeditor-dsfr-bundle:@dev"
```

Avec un dépôt Git (GitLab) :

```json
"repositories": {
    "ckeditor-dsfr-bundle": { "type": "vcs", "url": "https://gitlab.exemple.fr/groupe/ckeditor-dsfr-bundle.git" }
}
```

```bash
composer require yassrzg/ckeditor-dsfr-bundle:^1.0
```

> Si Symfony Flex ajoute `public/assets/` ou `assets/vendor/` au `.gitignore` alors que le projet versionne ces fichiers,
> annuler cette modification.

## 3. Activer le bundle

`config/bundles.php` (Flex le fait en général) :

```php
Yassrzg\CkeditorDsfrBundle\CkeditorDsfrBundle::class => ['all' => true],
```

## 4. Configurer

`config/packages/ckeditor_dsfr.yaml` : tous les réglages ont une valeur par défaut, un fichier minimal suffit.

```yaml
ckeditor_dsfr:
    layout: 'base.html.twig'          # gabarit étendu par la médiathèque et les réglages (bloc « body »)
    storage_root: '%kernel.project_dir%/var/ckeditor_dsfr'
    roles:
        admin: ROLE_ADMIN             # médiathèque et éditeur
        settings: ROLE_ADMIN          # réglages de l'éditeur
        view: PUBLIC_ACCESS           # consultation des fichiers (IS_AUTHENTICATED pour un site privé)
    media_usages:                     # contenus qui utilisent les médias (suppression bloquée)
        - { class: App\Entity\Page, label: title, content_fields: [content], route: admin_page_edit }
```

Détail de chaque option : [configuration.md](configuration.md).

## 5. Importer les routes

`config/routes/ckeditor_dsfr.yaml` :

```yaml
ckeditor_dsfr_admin:
    resource: '@CkeditorDsfrBundle/config/routes/admin.php'
    prefix: /admin                    # médiathèque : /admin/mediatheque/, réglages : /admin/reglages-editeur

ckeditor_dsfr_media:
    resource: '@CkeditorDsfrBundle/config/routes/media.php'   # fichiers : /media/{id}, /media/{id}/telecharger
```

Les droits sont contrôlés par le bundle (`roles`) ; une règle `access_control` sur `/admin` reste recommandée.

## 6. Déclarer CKEditor et le contrôleur Stimulus

```bash
php bin/console ckeditor-dsfr:install --dry-run   # affiche ce qui sera ajouté
php bin/console ckeditor-dsfr:install
```

La commande, sans réseau et sans rien écraser :
- ajoute à `importmap.php` CKEditor 5 (`ckeditor5`, `ckeditor5/translations/fr.js`) et SortableJS (`sortablejs`) **fournis
  par le bundle**, seulement s'ils ne sont pas déjà déclarés ;
- déclare le contrôleur Stimulus `ckeditor-dsfr` dans `assets/controllers.json` (chargé à la demande : les 2 Mo de
  l'éditeur ne pèsent que sur les pages qui l'utilisent).

Relancée, elle ne modifie rien.

## 7. Feuille de style

Dans le `<head>` du gabarit de base, **après** le DSFR :

```twig
<link rel="stylesheet" href="{{ asset('dsfr/dsfr.min.css') }}">
<link rel="stylesheet" href="{{ asset('dsfr/utility/icons/icons.min.css') }}">
{{ ckeditor_dsfr_stylesheets() }}
{{ importmap('app', {nonce: csp_nonce()}) }}   {# nonce : seulement si le projet a une politique CSP #}
```

> **Politique de sécurité (CSP)** : ne pas importer de CSS depuis le JavaScript (`import './styles/app.css'`) si le
> projet envoie un en-tête `Content-Security-Policy` sans `data:` dans `script-src`. AssetMapper remplace chaque import
> de CSS par un module `data:application/javascript,` : refusé, il bloque **tout** le JavaScript de la page, et l'éditeur
> reste sur « Chargement de l’éditeur… ». Charger les feuilles de style par des balises `<link>` comme ci-dessus.

## 8. Créer les tables

```bash
php bin/console doctrine:migrations:migrate
```

La migration du bundle crée les tables `ckeditor_dsfr_media` et `ckeditor_dsfr_setting` **si elles n'existent pas**
(SQL produit par Doctrine d'après la configuration du projet : PostgreSQL, MySQL, SQLite…). Elle ne modifie aucune table
existante et peut être rejouée. Un projet qui gère lui-même ses tables met `migrations: false` (voir
[configuration.md](configuration.md#reprendre-des-tables-existantes)).

## 9. Utiliser l'éditeur

```php
use Yassrzg\CkeditorDsfrBundle\Form\EditorType;

$builder->add('content', EditorType::class, ['label' => 'Contenu', 'required' => false]);
```

```twig
{{ page.content|ckeditor_dsfr_render }}
```

Suite : [utilisation.md](utilisation.md).

## 10. Vérifier

```bash
php bin/console lint:container
php bin/console debug:router | grep ckeditor_dsfr
php bin/console asset-map:compile      # en production
```

Puis, connecté avec le rôle `admin` : ouvrir `/admin/mediatheque/`, ajouter une image, ouvrir un formulaire avec
l'éditeur, « Insérer un composant ». Avec le rôle `settings` : `/admin/reglages-editeur`.

## Dépannage

| Symptôme | Cause et solution |
| --- | --- |
| « L’éditeur n’a pas pu être chargé » | `ckeditor-dsfr:install` non lancé, ou assets non recompilés (`asset-map:compile`) |
| Reste sur « Chargement de l’éditeur… » | JavaScript bloqué : erreur CSP `data:application/javascript` dans la console (voir l'étape 7), ou contrôleur absent de `assets/controllers.json` (relancer `ckeditor-dsfr:install`) |
| Icônes et pictogrammes absents | `ckeditor_dsfr.dsfr.directory` ne pointe pas vers les fichiers du DSFR |
| Pictogrammes sans image | `ckeditor_dsfr.dsfr.asset_prefix` ne correspond pas au chemin public du DSFR |
| Pages de la médiathèque sans menu | surcharger `templates/bundles/CkeditorDsfrBundle/admin/_layout.html.twig` (voir [utilisation.md](utilisation.md#mise-en-page-de-ladministration)) |
| Vidéos bloquées par le navigateur | ajouter les hôtes à `frame-src` (option `content_security_policy: true` ou politique du projet) |
| Fichier refusé « Format non accepté » | format non coché dans les réglages, ou contenu du fichier différent de son extension |
