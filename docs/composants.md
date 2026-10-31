# Composants

## Dans l'éditeur

Barre d'outils : paragraphe et titres (section, sous-titre, niveau 4), gras, italique, effacer la mise en forme, lien,
listes, ligne de séparation, **Insérer un composant**, annuler / rétablir. Raccourcis : `## ` crée un titre de section,
`- ` une liste. Le texte collé (Word, page web) est nettoyé.

**Insérer un composant** ouvre une fenêtre DSFR : choix du composant, puis ses réglages. Un composant apparaît dans un
cadre avec ses réglages ; sélectionné, sa barre propose *Modifier les réglages* et *Supprimer le composant*. Les zones de
contenu (accordéon, onglet, encadrés) acceptent du texte et les composants sans zone, pas un composant à zones (pas
d'accordéon dans un accordéon). Un composant se déplace par sa poignée (glisser-déposer de CKEditor) ou par couper /
coller, y compris d'une colonne à l'autre.

| Composant | Réglages / zones | Affichage DSFR |
|---|---|---|
| Image | image, format (16:9, 32:9, 4:3, 3:2, 1:1, 3:4, 2:3), taille (petite, moyenne, grande), légende | Contenu média, `fr-ratio-*`, texte alternatif de la médiathèque |
| Colonnes | disposition (2 à 4 colonnes, total de 12), côte à côte à partir de la tablette ou de l'ordinateur, alignement vertical ; une zone par colonne | Grille DSFR `fr-grid-row--gutters`, `fr-col-12` + `fr-col-md-*` ou `fr-col-lg-*` |
| Documents à télécharger | documents (ordre choisi), chacun personnalisable (pictogramme, apparence) ; liens, cartes ou tuiles ; orientation, taille, apparence, pictogramme | Liens, cartes ou tuiles de téléchargement, format et poids affichés (RGAA) |
| Carte | titre, description, lien facultatif (carte entièrement cliquable), image ou pictogramme, badge, détail et icône ; orientation, taille, apparence | Carte ; plusieurs cartes ou tuiles à la suite : grille de 1 à 4 colonnes |
| Tuile | titre, description, lien, pictogramme ; orientation, taille, apparence | Tuile, en grille avec les cartes |
| Bouton | libellé, lien, style, taille, icône (à gauche ou à droite) | Bouton ; plusieurs à la suite : groupe de boutons |
| Bouton avec fenêtre | libellé, style, taille, icône, largeur de la fenêtre ; titre et contenu de la fenêtre | Bouton qui ouvre une fenêtre modale DSFR (`fr-modal`) ; groupé avec les boutons, fenêtre rendue après le groupe |
| Tableau | titre (caption, RGAA) au-dessus, en dessous ou masqué, taille, bordures, largeur des colonnes, couleur ; tableau rédigé dans l'éditeur (outil tableau de CKEditor : lignes, colonnes, en-têtes, fusion) | `fr-table` (`--bordered`, `--sm`/`--lg`, `--layout-fixed`, couleurs, `--caption-bottom`, `--no-caption`) |
| Bandeau d'information | type (information, avertissement, alerte, vigilance météo, enlèvement, attentat, appel à témoins, cyberattaque), titre, description, lien, icône | `fr-notice` (non refermable dans un contenu ; sans icône pour les 3 types génériques seulement) |
| Badge | libellé, type (état ou couleur), taille, icône des états | `fr-badge` ; toujours dans un groupe `fr-badges-group` |
| Tag | libellé, lien facultatif (tag cliquable, couleur), taille, icône | `fr-tag` ; groupe `fr-tags-group` |
| Icône | icône du DSFR, texte facultatif à droite, texte pour lecteurs d'écran (icône seule), taille, alignement | `fr-icon-*` (`--xs`, `--sm`, `--lg`) |
| Pictogramme | pictogramme du DSFR, texte alternatif facultatif (sinon décoratif), taille (56, 80, 120 px), alignement | `fr-artwork` (trois couches du pictogramme local) |
| Accordéon | titre, contenu | Groupe d'accordéons |
| Onglet | titre, contenu | Groupe d'onglets |
| Mise en avant | couleur, icône, titre facultatif, contenu | `fr-callout` |
| Mise en exergue | couleur, contenu | `fr-highlight` |
| Alerte | type, titre facultatif, contenu | `fr-alert` (compacte sans titre) |
| Citation | contenu, auteur, source et son lien, précisions, image, couleur | `fr-quote` ; avec image : `fr-quote--column` (seule disposition prévue par le DSFR) |
| Vidéo | adresse, titre, légende, transcription | Lecteur 16:9 et transcription (RGAA) ; plateformes autorisées seulement |
| *Image et texte* (ancien) | image, position, largeur, format, légende ; texte | Toujours affiché et modifiable, plus proposé : remplacé par « Colonnes » |

### Bloc « Colonnes »

- **Dispositions** (`ComponentRegistry::COLUMN_LAYOUTS`) : 6+6, 4+8, 8+4, 3+9, 9+3, 5+7, 7+5, 4+4+4, 3+6+3, 6+3+3, 3+3+6,
  3+3+3+3. Sur téléphone, chaque colonne prend toute la largeur (`fr-col-12`), dans l'ordre du contenu ; à partir de la
  tablette (`fr-col-md-*`) ou de l'ordinateur seulement (`fr-col-lg-*`), elles se placent côte à côte.
- **Contenu d'une colonne** : texte, titres, listes, médias et tous les composants (y compris accordéons, onglets,
  encadrés), sauf un autre bloc « Colonnes ».
- **Changer de disposition** : des colonnes vides sont ajoutées ; le contenu des colonnes retirées est regroupé à la fin de
  la dernière colonne gardée (rien n'est perdu).
- **Largeur minimale** (réglage `width` d'un composant, en colonnes de 12) : carte et tuile 3 par élément d'une ligne
  (4 par ligne), le double à l'horizontale ; documents en cartes ou tuiles 3 par document ; vidéo 4. Une insertion, un
  déplacement, un collage ou un changement de disposition qui rend une colonne trop étroite est **annulé**, avec une
  alerte DSFR en haut à droite (ex. 4 cartes = 12 colonnes, refusées dans une colonne de 6). Un contenu déjà trop
  large à l'ouverture reste modifiable.

### Composants du DSFR pris en charge ou non

| Statut | Composants DSFR |
|---|---|
| Pris en charge | Contenu média (image, vidéo), carte, tuile, bouton et groupe de boutons, accordéon, onglets, mise en avant, mise en exergue, alerte, citation, téléchargement de fichier (lien, carte, tuile), grille, icônes, pictogrammes |
| Utilisé par le texte | Titres, listes, liens, séparateur (`hr`) : styles du DSFR sur le HTML simple |
| Non proposé (adaptateur à écrire) | Tableau, badge, tag, mise en relief de lien, sommaire automatique (fourni par `@CkeditorDsfr/_summary.html.twig` hors éditeur), étapes (*stepper*), bandeau d'information, transcription seule : composant propre au projet par `ComponentProviderInterface` |
| Hors du rôle d'un contenu | Formulaires, en-tête, pied de page, navigation, fenêtre modale, fil d'Ariane, pagination, connexion FranceConnect, sélecteur de thème |

Les classes produites par chaque composant, pour chacune de ses valeurs, sont vérifiées contre le DSFR du projet par
un test de l'application hôte (voir `maintenance.md`).

- **Médiathèque dans la fenêtre** : les champs image et documents ouvrent l'étape « Médiathèque » de la même fenêtre
  (recherche, grille, cases à cocher, *Valider la sélection*). « Ajouter à la médiathèque » y ajoute un fichier sans
  quitter l'éditeur (titre proposé d'après le nom du fichier, erreurs affichées dans les champs, fichier conservé).
  Plusieurs documents s'ordonnent par glisser-déposer ou boutons *Monter* / *Descendre* (50 au plus).
- **Parties facultatives** : une zone laissée vide (titre facultatif d'une alerte, d'une mise en avant…) est enregistrée
  par l'éditeur avec un espace insécable ; elle est traitée comme vide et sa balise n'est pas produite. Un réglage
  obligatoire n'est exigé que s'il est proposé (ex. type du badge d'une carte, seulement avec un badge).
- **Réglages par document** : en cartes ou tuiles, le bouton *Personnaliser* d'un document lui donne son propre
  pictogramme et sa propre apparence ; une valeur vide reprend celle du composant. Enregistrés en JSON dans
  `data-media-ids-settings` (`{"12": {"pictogram": "system/warning", "surface": "shadow"}}`).
- **Icônes et pictogrammes** : uniquement ceux du DSFR (aucun fichier déposé), dans la même grille (recherche, 200
  résultats affichés).
- **Couleurs** : les 17 couleurs d'accentuation du DSFR, bleu France par défaut. **Variantes** : apparence (bordure, fond
  gris, sans bordure, sans fond, ombre), orientation, taille. Une valeur inconnue revient à la variante par défaut.
- **Réglages conditionnels** : un réglage n'est proposé que si l'affichage choisi l'utilise (ex. position de l'icône
  seulement avec une icône) ; un réglage masqué n'est pas enregistré.
- **Orientation** : le DSFR ne déplace que l'en-tête. Carte : proposée seulement avec une image ou un pictogramme,
  horizontale à partir de la tablette (`fr-card--horizontal` n'agit qu'à partir de 768 px). Tuile : seulement avec un
  pictogramme. Documents : tuiles seulement ; la carte de téléchargement du DSFR est toujours horizontale, la tuile de
  téléchargement l'est par défaut et devient verticale à partir de la tablette avec `fr-tile--vertical@md`.
- **Espaces** : plusieurs Entrée créent des paragraphes vides, gardés à l'affichage (sauf entre deux composants d'un même
  groupe).

## Format enregistré

HTML simple, jamais le code DSFR :

```html
<div data-component="image" data-media-id="12" data-ratio="4x3" data-size="md"></div>
<div data-component="download" data-media-ids="12,4,7" data-variant="card"></div>
<div data-component="accordion"><div data-slot="title">Question</div><div data-slot="body"><p>Réponse</p></div></div>
<div data-component="columns" data-columns="4-8" data-breakpoint="md" data-align="top">
  <div data-slot="column"><div data-component="image" data-media-id="12" data-ratio="1x1" data-size="lg"></div></div>
  <div data-slot="column"><p>Texte à droite de l'image</p></div>
</div>
```

Le rendu DSFR est fait à l'affichage par `@CkeditorDsfr/components/<nom>.html.twig` (et
`@CkeditorDsfr/groups/<groupe>.html.twig` pour les composants consécutifs d'un même groupe : cartes, boutons,
accordéons, onglets). Une évolution du DSFR se fait dans les gabarits, sans toucher aux contenus.

## Architecture

- `config/components.yaml` : **catalogue déclaratif** des composants (réglages, listes de valeurs, valeurs par défaut,
  conditions, zones, groupes, largeurs). Lu et contrôlé par `Editor\ComponentCatalog` à la compilation du conteneur :
  une erreur de déclaration empêche le démarrage avec un message qui nomme le fichier, le composant et le réglage.
- `Editor\ComponentRegistry` : **liste unique** des composants construite depuis ce catalogue. Elle alimente l'éditeur
  (JSON), la fenêtre de réglages, le filtre HTML (`ContentSanitizer`), le contrôle à l'enregistrement
  (`ContentValidator`), la page des réglages, le rendu (`ContentRenderer`) et l'extraction du texte (`ContentText`).
- `assets/src/components_plugin.js` : plugin CKEditor 5 (schéma, conversions, commandes `insertComponent` /
  `updateComponent`) ; `assets/src/editor_controller.js` : contrôleur Stimulus `ckeditor-dsfr` (fenêtre, médiathèque,
  aperçus).
- Types de réglages : `text`, `textarea`, `url`, `choice`, `media`, `medias`, `icon`, `pictogram` ; conditions `when()`,
  `whenFilled()`, `optionWhen()`.
- `ComponentDefinition` : `group` (affichage groupé), `layout` (bloc de colonnes : réglage `columns` « 4-8 », zone
  `column` répétée), `legacy` (ancien composant, lu et modifiable mais plus proposé), `width` (largeur minimale dans
  une colonne).
- L'éditeur reçoit **tous** les composants (`insertable` vaut `false` pour un composant retiré, ancien ou vidéo sans
  hôte) : un contenu qui en contient est relu et réenregistré sans perte.
- Modèle CKEditor : `cmpAtom` (sans zone), `cmpBox` (zones `cmpSlotInline` / `cmpSlotBlock`), `cmpLayout` (colonnes
  `cmpColumn`) ; le schéma interdit un composant à zones dans une zone et un bloc « Colonnes » dans une zone ou une
  colonne.

## Modifier ou ajouter un composant (YAML)

**Une déclaration, tout suit** : une variante ajoutée ou retirée d'une liste, un nouveau réglage, une condition ou une
valeur par défaut modifiée dans le YAML s'applique à la fenêtre de l'éditeur, au filtre HTML, au contrôle à
l'enregistrement, à la page des réglages et au rendu. Seule une évolution de la **structure HTML** d'un composant DSFR
demande de modifier son gabarit Twig. Après modification : `php bin/console cache:clear` (le conteneur est recompilé
automatiquement en développement).

Format (voir l'en-tête de `config/components.yaml`) :

```yaml
lists:
    services: {sdi: Service A, cyber: Service B}     # listes partagées, utilisées par « @services »
components:
    contact:
        label: Contact
        description: Carte de contact d'un service.
        icon: fr-icon-mail-line                      # icône DSFR dans la liste du choix
        group: cards                                 # facultatif : affichage groupé (groups/<groupe>.html.twig)
        width: {min: 3, grouped: true}               # facultatif : largeur minimale dans une colonne
        fields:
            service: {type: choice, label: Service, options: '@services'}
            email: {type: text, label: Adresse, max_length: 255, when: {service: [cyber]}}
            pictogram: {type: pictogram, label: Pictogramme, when_empty: email}
            documents:
                type: medias
                label: Documents
                media_type: document
                items: {pictogram: {type: pictogram, label: Pictogramme}}   # réglages par document
                items_when: {service: [cyber]}
        slots:
            body: {label: Contenu}                   # zone rédigée dans l'éditeur (inline, required, components, tables)
```

- Types : `text`, `textarea`, `url`, `choice`, `media`, `medias`, `icon`, `pictogram` ; obligatoires par défaut :
  `url`, `choice`, `media`, `medias` (`required: false` sinon).
- Conditions : `when` (valeur parmi une liste), `when_filled` (l'un des réglages renseigné), `when_empty`, et
  `option_when` pour une valeur d'une liste. Un réglage masqué n'est pas enregistré.
- Les valeurs `yes`, `no`, `true`… s'écrivent entre guillemets (sinon lues comme des booléens par YAML).
- `{video_hosts}` dans une aide affiche les plateformes vidéo autorisées.

**Dans un projet** : `ckeditor_dsfr.component_files` liste des fichiers YAML lus après celui du bundle. Un composant du
même nom **remplace** celui du bundle, `enabled: false` le **retire**. Le gabarit d'un nouveau composant se place dans
`templates/bundles/CkeditorDsfrBundle/components/<nom>.html.twig` (variable `c` : `values`, `media`, `medias`,
`mediaSettings`, `slots`, `columns`, `id`), un groupe dans `templates/bundles/CkeditorDsfrBundle/groups/<groupe>.html.twig`.

```yaml
# config/packages/ckeditor_dsfr.yaml
ckeditor_dsfr:
    component_files: ['%kernel.project_dir%/config/ckeditor_dsfr_components.yaml']
```

Un composant calculé en PHP reste possible : service qui implémente `ComponentProviderInterface` (enregistré
automatiquement) et renvoie des `ComponentDefinition`.

## Médias utilisés par un cas particulier

Les contenus courants se déclarent dans `media_usages` (configuration). Pour un cas particulier (JSON, autre stockage),
implémenter `Media\MediaUsageProviderInterface` (service enregistré automatiquement) et renvoyer des `MediaUsage`
(libellé, adresse facultative).
