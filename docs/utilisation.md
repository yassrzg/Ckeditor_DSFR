# Utilisation dans un projet

## Champ de formulaire

```php
use Yassrzg\CkeditorDsfrBundle\Form\EditorType;

$builder->add('content', EditorType::class, [
    'label' => 'Contenu',
    'required' => false,
    'attr' => ['rows' => 20],   // hauteur de la zone de texte si JavaScript est indisponible
]);
```

- Valeur stockée → éditeur : une ancienne valeur en **texte simple** s'ouvre en paragraphes (retours à la ligne gardés) :
  un champ texte existant peut passer à l'éditeur sans migration de données.
- Saisie → valeur stockée : HTML filtré (`ContentSanitizer`), `null` si le contenu est vide.
- Le champ est rendu par le thème `@CkeditorDsfr/form/editor.html.twig` (ajouté automatiquement aux thèmes de
  formulaire) ; le libellé, l'aide et les erreurs suivent le thème du projet. Sans JavaScript, la zone de texte HTML reste
  utilisable.
- La colonne doit être de type `TEXT`.

## Affichage

```twig
{# Contenu rendu en DSFR (ancienne valeur en texte simple : paragraphes) #}
{{ page.content|ckeditor_dsfr_render }}

{# Contenu et sommaire : titres de section (h2) et sous-titres (h3) avec leurs ancres #}
{% set document = ckeditor_dsfr_document(page.content) %}
{% if document.summary|length >= 2 %}
    {{ include('@CkeditorDsfr/_summary.html.twig', {summary: document.summary, id: 'page-summary'}) }}
{% endif %}
{{ document.html|raw }}
```

Le HTML produit est sûr (filtré puis rendu par les gabarits du bundle). Un composant incomplet (média supprimé, lien
invalide, vidéo d'une plateforme retirée) n'est pas affiché.

## Fonctions Twig

| Fonction / filtre | Rôle |
| --- | --- |
| `ckeditor_dsfr_render` (filtre) | Rendu DSFR d'un contenu |
| `ckeditor_dsfr_document(contenu)` | `{html, summary}` : rendu et sommaire |
| `ckeditor_dsfr_stylesheets()` | Feuille de style du bundle (dans le `<head>`) |
| `ckeditor_dsfr_components()` | Composants proposés (définitions) |
| `ckeditor_dsfr_pictogram_url(chemin)` | Adresse d'un pictogramme du DSFR (`digital/search`) |
| `ckeditor_dsfr_is_granted('admin' \| 'settings' \| 'view')` | Droit configuré dans `roles` |

## Services utiles (autowiring)

| Service | Usage |
| --- | --- |
| `Yassrzg\CkeditorDsfrBundle\Editor\ContentSanitizer` | Filtrer un HTML saisi hors formulaire (`sanitize()`) |
| `Yassrzg\CkeditorDsfrBundle\Editor\ContentValidator` | Contrôler un HTML filtré (`validate()` : composants, valeurs, médias, colonnes ; liste de messages) ; appliqué automatiquement par `EditorType` |
| `Yassrzg\CkeditorDsfrBundle\Editor\ContentRenderer` | Rendu en PHP (`render()`, `renderDocument()`) |
| `Yassrzg\CkeditorDsfrBundle\Editor\ContentText` | Texte brut d'un contenu, pour un moteur de recherche (zones et réglages textuels des composants compris) |
| `Yassrzg\CkeditorDsfrBundle\Settings\EditorSettings` | Réglages de l'administrateur (`videoHosts()` pour une politique CSP, `extensions()`, `maxSize()`…) |
| `Yassrzg\CkeditorDsfrBundle\Media\MediaManager` | Enregistrer / supprimer un média, `usages()` |
| `Yassrzg\CkeditorDsfrBundle\Media\MediaStorage` | Chemin d'un fichier (`path()`) |
| `Yassrzg\CkeditorDsfrBundle\Repository\MediaRepository` | Recherche de médias |

Une entité du projet peut référencer un média (vignette…) :

```php
use Yassrzg\CkeditorDsfrBundle\Entity\Media;

#[ORM\ManyToOne]
#[ORM\JoinColumn(onDelete: 'SET NULL')]
private ?Media $thumbnail = null;
```

```twig
<img src="{{ path('ckeditor_dsfr_media_show', {id: page.thumbnail.id}) }}" alt="{{ page.thumbnail.altText }}">
```

Déclarer alors la relation dans `media_usages` (`relation_fields`) pour bloquer la suppression du média.

## Routes

| Route | Adresse (préfixes conseillés) | Droit |
| --- | --- | --- |
| `ckeditor_dsfr_media_index` | `/admin/mediatheque/` | admin |
| `ckeditor_dsfr_media_create`, `_edit`, `_delete` | `/admin/mediatheque/…` | admin |
| `ckeditor_dsfr_media_selection`, `ckeditor_dsfr_artwork`, `ckeditor_dsfr_media_quick_add` | données de l'éditeur | admin |
| `ckeditor_dsfr_settings` | `/admin/reglages-editeur` | settings |
| `ckeditor_dsfr_media_show`, `ckeditor_dsfr_media_download` | `/media/{id}`, `/media/{id}/telecharger` | view |

## Mise en page de l'administration

Les pages du bundle étendent `@CkeditorDsfr/admin/_layout.html.twig`, qui étend `ckeditor_dsfr.layout` et remplit son
bloc `body`. Pour les intégrer à l'administration du projet (menu, fil d'Ariane), surcharger ce gabarit :

```twig
{# templates/bundles/CkeditorDsfrBundle/admin/_layout.html.twig #}
{% extends 'admin/_layout.html.twig' %}

{% block title %}Administration - {{ block('ckeditor_dsfr_title') }}{% endblock %}

{% block content %}
    {{ include('@CkeditorDsfr/admin/_flashes.html.twig') }}
    {{ block('ckeditor_dsfr_content') }}
{% endblock %}
```

Messages : types `ckeditor_dsfr_success` et `ckeditor_dsfr_error`, affichés par `@CkeditorDsfr/admin/_flashes.html.twig`
(à retirer si le gabarit du projet affiche déjà tous les messages).

Tous les gabarits se surchargent de la même façon (`templates/bundles/CkeditorDsfrBundle/<chemin>`), par exemple le rendu
d'un composant (`components/card.html.twig`).
