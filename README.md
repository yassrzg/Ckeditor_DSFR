# CKEditor DSFR (`ckeditor_dsfr`)

Bundle Symfony : éditeur **CKEditor 5** à **composants du DSFR** (Système de Design de l'État), **médiathèque** en base
de données et **réglages administrables** (plateformes vidéo, formats et poids des fichiers, dossiers de stockage,
composants proposés).

- 19 composants : image, documents à télécharger (réglables document par document), carte (lien facultatif), tuile,
  bouton, bouton avec fenêtre, accordéon, onglet, tableau, mise en avant, mise en exergue, alerte, bandeau d'information,
  badge, tag, icône, pictogramme, citation, vidéo (variantes DSFR : orientation, taille, apparence, couleurs, icônes et pictogrammes du DSFR), et un
  bloc **Colonnes** (2 à 4 colonnes de la grille DSFR, responsive) qui accueille texte, médias et composants ;
  l'ancien composant « Image et texte » reste lu.
- **Catalogue YAML** : composants, réglages, valeurs et conditions déclarés dans `config/components.yaml` ; un projet
  ajoute, remplace ou retire des composants par ses propres fichiers YAML.
- Le contenu est enregistré en HTML simple (`<div data-component="card" data-title="…">`), jamais en code DSFR : le rendu
  DSFR est produit à l'affichage, une évolution du DSFR ne touche pas aux contenus.
- Médiathèque : images et documents, ajout depuis l'éditeur, choix par grille, ordre par glisser-déposer, protection des
  médias utilisés.
- Sécurité : HTML filtré à l'enregistrement et à l'affichage, réglages revalidés, formats exécutables toujours refusés,
  CSRF, droits configurables.
- Accessibilité (RGAA) : texte alternatif obligatoire, fenêtre unique, navigation au clavier, transcription des vidéos.
- Sans réseau : CKEditor 5 et SortableJS sont fournis par le bundle.

## Prérequis

PHP 8.3, Symfony 7.4 ou 8, Doctrine ORM 3, AssetMapper et StimulusBundle, le DSFR dans le projet (par défaut
`assets/dsfr`, fichiers du paquet `@gouvfr/dsfr`).

## Documentation

| Document | Contenu |
| --- | --- |
| [docs/installation.md](docs/installation.md) | Installer le bundle dans un projet, pas à pas |
| [docs/configuration.md](docs/configuration.md) | Configuration (`ckeditor_dsfr`) et réglages de l'administrateur |
| [docs/utilisation.md](docs/utilisation.md) | Formulaires, affichage, Twig, services, recherche |
| [docs/composants.md](docs/composants.md) | Composants, format enregistré, ajouter un composant |
| [docs/maintenance.md](docs/maintenance.md) | Tests, mise à jour de CKEditor, publication du bundle |

## Licence

GPL-2.0-or-later : le bundle contient CKEditor 5, distribué sous licence GPL (clé `licenseKey: 'GPL'`).
Voir [LICENSE](LICENSE) et `assets/vendor/ckeditor5/LICENSE.md`. SortableJS : licence MIT.
