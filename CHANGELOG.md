# Changements

## 1.1.0 — non publiée

- Bloc **Colonnes** : 2 à 4 colonnes de la grille DSFR (12 dispositions), côte à côte à partir de la tablette ou de
  l'ordinateur, l'une sous l'autre sur téléphone ; texte, médias et tous les composants dans une colonne ; largeur
  minimale des composants contrôlée (alerte et annulation) ; activable dans les réglages.
- « Image et texte » devient un ancien composant (`legacy`) : affiché et modifiable, plus proposé.
- Correction : un composant retiré des réglages (ou une vidéo sans hôte autorisé) n'est plus perdu quand un contenu qui
  le contient est réenregistré dans l'éditeur.
- Composants atomiques déplaçables par leur poignée (glisser-déposer de CKEditor).
- **Catalogue YAML** (`config/components.yaml`) : composants, réglages, listes, conditions et largeurs déclarés en YAML,
  contrôlés au démarrage ; fichiers du projet (`component_files`) pour ajouter, remplacer ou retirer un composant.
- Tous les composants acceptés dans les zones de contenu et les colonnes (colonnes dans un accordéon, accordéon dans
  un onglet…), sauf dans un titre et une cellule de tableau.
- Nouveaux composants : Tableau (outil tableau de CKEditor, `fr-table`), Bouton avec fenêtre (`fr-modal`), Bandeau
  d'information (`fr-notice`), Badge, Tag ; badge dans la carte ; citation avec image, lien de la source et précisions.
- Composants Icône et Pictogramme (ressources du DSFR local) ; lien de la carte facultatif (sans lien, la carte n'est
  pas cliquable : un lien s'ajoute avec les composants Bouton, Bouton avec fenêtre…).
- Zones facultatives laissées vides (espace insécable) : aucune balise produite (ex. titre d'alerte) ; réglage
  obligatoire exigé seulement s'il est proposé.
- Réglages par document (*Personnaliser*) : pictogramme et apparence propres à chaque document en cartes ou tuiles.
- Contrôle à l'enregistrement (`ContentValidator`) ; orientation des cartes et tuiles proposée seulement quand elle a un
  effet, tuile de téléchargement verticale (`fr-tile--vertical@md`).
- Namespace `Yassrzg\CkeditorDsfrBundle`, paquet `yassrzg/ckeditor-dsfr-bundle`.
- **Vidéos envoyées** : catégorie « Vidéos » de la médiathèque (formats, poids, dossier `video` sous `storage_root`) ;
  composant Vidéo par lien ou par fichier (lecteur du navigateur, sous-titres VTT, image d'aperçu), sources proposées
  choisies dans les réglages (`defaults.video_sources`, `link` par défaut) ; fichiers vidéo servis par morceaux.
- Catalogue : `media_type: video`, `formats` (formats acceptés d'un média), `category` (rubrique des réglages).
- Page des réglages réorganisée : sommaire latéral, résumé, composants par catégorie, réglages des vidéos affichés
  selon les sources cochées, bouton d'enregistrement toujours visible.

## 1.0.0 — 2026-10-28

Première version :
- éditeur CKEditor 5 à 13 composants DSFR, variantes, icônes et pictogrammes du DSFR, réglages conditionnels ;
- médiathèque en base (images, documents), ajout depuis l'éditeur, médias utilisés protégés (`media_usages`) ;
- réglages de l'administrateur : composants proposés, plateformes vidéo, formats, poids et dossiers des fichiers ;
- commande `ckeditor-dsfr:install` (CKEditor 5 et SortableJS fournis, sans réseau), migration idempotente des tables.
