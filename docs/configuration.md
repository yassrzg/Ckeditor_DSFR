# Configuration

Deux niveaux :
- la **configuration** (`config/packages/ckeditor_dsfr.yaml`), fixée par le développeur : infrastructure, droits, limites ;
- les **réglages de l'éditeur**, modifiés par l'administrateur dans l'application (page « Réglages de l'éditeur »),
  enregistrés en base. Tant qu'un réglage n'a pas été enregistré, il vaut sa valeur de `defaults`.

## Référence complète

```yaml
ckeditor_dsfr:
    # Gabarit étendu par les pages de la médiathèque et des réglages (bloc « body »).
    layout: 'base.html.twig'

    dsfr:
        directory: '%kernel.project_dir%/assets/dsfr'   # fichiers du DSFR : icônes et pictogrammes proposés
        asset_prefix: 'dsfr'                             # chemin AssetMapper (ou public/) de ce dossier

    # Composants du projet (YAML, même format que config/components.yaml du bundle) : ajout, remplacement
    # (même nom) ou retrait (enabled: false) ; voir composants.md.
    component_files: []

    # Dossier racine des fichiers : l'administrateur choisit dessous les dossiers des images et des documents.
    # Hors de public/ de préférence : les fichiers sont servis par le bundle, avec contrôle des droits.
    storage_root: '%kernel.project_dir%/var/ckeditor_dsfr'

    tables:
        media: ckeditor_dsfr_media
        setting: ckeditor_dsfr_setting
    migrations: true            # false : le projet crée lui-même les tables

    roles:
        admin: ROLE_ADMIN       # médiathèque, données de l'éditeur, ajout depuis l'éditeur
        settings: ROLE_ADMIN    # page des réglages
        view: PUBLIC_ACCESS     # fichiers (/media/{id}) ; IS_AUTHENTICATED pour un site privé

    # Formats que l'administrateur peut cocher (il choisit parmi ces listes).
    extension_catalog:
        image: [jpg, jpeg, png, webp, gif, avif]
        document: [pdf, odt, ods, odp, odg, doc, docx, xls, xlsx, ppt, pptx, csv, txt, rtf, zip, 7z, gz, tar, json, epub, mp3, mp4, webm]

    max_size_limit: 200         # poids maximal (Mo) que l'administrateur peut fixer

    # Valeurs initiales des réglages de l'administrateur.
    defaults:
        video_hosts: []                                  # ex. [www.youtube.com, player.vimeo.com]
        image_extensions: [jpg, jpeg, png, webp]
        document_extensions: [pdf, odt, ods, odp, doc, docx, xls, xlsx, ppt, pptx, csv, txt, zip]
        image_max_size: 10                               # Mo
        document_max_size: 50                            # Mo
        disabled_components: []                          # ex. [tab, quote]
        image_directory: image                           # sous storage_root
        document_directory: document

    # Contenus qui utilisent les médias : affichés sur la fiche du média, suppression bloquée.
    media_usages:
        - class: App\Entity\Article         # entité Doctrine
          label: title                      # propriété affichée
          content_fields: [content]         # champs saisis avec l'éditeur
          relation_fields: [thumbnail]      # relations vers un média (ManyToOne)
          route: admin_article_edit         # lien de modification (facultatif)
          route_parameters: { id: id }      # paramètre de route => propriété (par défaut { id: id })

    # Ajoute les hôtes vidéo autorisés à la directive frame-src de l'en-tête Content-Security-Policy du projet.
    content_security_policy: false
```

## Réglages de l'administrateur

Page `ckeditor_dsfr_settings` (`/admin/reglages-editeur` avec le préfixe conseillé), rôle `roles.settings` :

| Réglage | Effet |
| --- | --- |
| Composants proposés | Un composant décoché n'est plus proposé dans l'éditeur ; les contenus qui l'utilisent restent affichés et modifiables. « Colonnes » s'active ou se désactive ici comme les autres ; un ancien composant (« Image et texte ») n'y figure plus. |
| Plateformes vidéo | Un hôte par ligne (une adresse complète est réduite à son hôte). Vide : composant « Vidéo » retiré et vidéos masquées (réglages conservés). |
| Formats d'image, de document | Cases à cocher parmi `extension_catalog`. Le format est déduit du **contenu** du fichier. |
| Poids maximal | 1 à `max_size_limit` Mo. PHP doit aussi l'accepter (`upload_max_filesize`, `post_max_size`), la page affiche la limite de PHP. |
| Dossier des images, des documents | Sous-dossier de `storage_root` (lettres, chiffres, `-`, `_`, séparés par `/`). Un changement **déplace les fichiers existants** ; au moindre échec, tout revient en place et le réglage n'est pas modifié. Les deux catégories doivent être dans des dossiers différents. |

Les réglages sont lus en une requête par requête HTTP. Si la base est indisponible, les valeurs par défaut s'appliquent,
vidéos désactivées (erreur journalisée).

## Sécurité

- **Formats toujours refusés**, même ajoutés à `extension_catalog` (la configuration est alors refusée au démarrage) :
  scripts et exécutables (php, phtml, phar, js, sh, exe, bat…), formats interprétés par le navigateur (html, htm, xhtml,
  svg, xml, css…). Un fichier HTML ou SVG servi par le site pourrait exécuter un script avec la session de la personne
  qui l'ouvre.
- Fichiers servis par le bundle avec `X-Content-Type-Options: nosniff`, documents toujours en téléchargement, noms de
  fichiers générés (jamais le nom envoyé), aucun chemin issu d'une saisie (dossiers limités à `storage_root`).
- HTML de l'éditeur filtré à l'enregistrement et à l'affichage (`symfony/html-sanitizer` : aucun script, style ni
  attribut libre) ; chaque réglage de composant est revalidé à l'affichage (listes de valeurs, adresses http(s)/mailto/
  tel/relatives, médias existants, icônes et pictogrammes du DSFR).
- Contenu contrôlé à l'enregistrement (`ContentValidator`, par `EditorType`) : un envoi forgé (composant inconnu, valeur
  hors liste, adresse `javascript:`, icône ou pictogramme hors DSFR, média inexistant ou du mauvais type, imbrication
  interdite, nombre de colonnes incohérent) est refusé avec un message sur le champ.
- Vidéos : HTTPS et hôtes autorisés uniquement ; ajouter ces hôtes à `frame-src` (`content_security_policy: true`, ou
  politique du projet via `EditorSettings::videoHosts()`).
- CSRF sur tous les formulaires et la suppression ; droits par `roles`.

## Reprendre des tables existantes

Les noms de tables sont configurables : un projet peut reprendre une médiathèque existante de même structure
(colonnes de `Yassrzg\CkeditorDsfrBundle\Entity\Media` et `Setting`). La table des réglages peut être partagée avec
d'autres réglages du projet : seules les clés du bundle sont lues (`video_hosts`, `media_*`, `disabled_components`).

```yaml
ckeditor_dsfr:
    tables: { media: media, setting: site_setting }
    migrations: false
    storage_root: '%kernel.project_dir%/public/uploads/contenu'   # fichiers existants dans image/ et document/
```

Vérifier ensuite que le mapping correspond à la base : `php bin/console doctrine:schema:validate`.

## Journaux

Erreurs et déplacements de fichiers sont journalisés sur le canal Monolog `ckeditor_dsfr` (sans contenu saisi ni secret).
