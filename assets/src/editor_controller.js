/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import {
    Autoformat, Bold, ClassicEditor, Essentials, Heading, HorizontalLine, Italic, Link, List, Paragraph, PasteFromOffice, RemoveFormat,
    Table, TableToolbar,
} from 'ckeditor5';
import translations from 'ckeditor5/translations/fr.js';
import Sortable from 'sortablejs';
import { createComponentsPlugin } from './components_plugin.js';

/*
 * Éditeur à composants DSFR (bundle CKEditor DSFR) : CKEditor 5 (licence GPL, fichiers fournis par le bundle dans
 * assets/vendor/ckeditor5) et composants DSFR. Identifiant Stimulus : ckeditor-dsfr (assets/package.json).
 * Chargé à la demande (fetch: lazy) : les 2 Mo de l'éditeur ne pèsent que sur les pages qui l'utilisent.
 *
 * La fenêtre de réglages d'un composant est rendue par le serveur (@CkeditorDsfr/form/editor.html.twig, champs DSFR) ;
 * ce contrôleur l'ouvre, la remplit et transmet les valeurs aux commandes du plugin (insertComponent, updateComponent).
 * Images et documents se choisissent dans l'étape « Médiathèque » de la même fenêtre (pas de fenêtre dans une fenêtre) ;
 * plusieurs documents s'ordonnent par glisser-déposer ou par les boutons Monter / Descendre. Un fichier absent s'ajoute
 * à la médiathèque depuis cette étape (formulaire de la médiathèque chargé à la demande), puis est sélectionné.
 * Les icônes et pictogrammes du DSFR (liste fixe, route ckeditor_dsfr_artwork) se choisissent dans la même grille.
 * Sans JavaScript ou si l'éditeur ne se charge pas, la zone de texte HTML reste utilisable.
 * Plusieurs documents : chacun peut avoir ses propres réglages (bouton « Personnaliser » : pictogramme, apparence…),
 * enregistrés en JSON dans data-<champ>-settings et prioritaires sur ceux du composant.
 * Tableaux : outil tableau de CKEditor, actif seulement dans le composant Tableau.
 * Bloc « Colonnes » : une modification qui rend une colonne trop étroite pour son contenu (ex. 4 cartes, 12 colonnes,
 * dans une colonne de 6) est annulée et signalée par une alerte en haut à droite de l'écran.
 */
const SAFE_URL = /^(https?:\/\/|mailto:|tel:|\/(?!\/))/i;
const LIBRARY_LIMIT = 200;
const NOTICE_DELAY = 10000;

export default class extends Controller {
    static targets = [
        'source', 'status', 'opener', 'dialog', 'title', 'chooser', 'choice', 'panel', 'submit', 'back', 'footer',
        'library', 'librarySearch', 'libraryStatus', 'libraryGrid', 'libraryValidate', 'libraryAdd', 'libraryAddButton', 'notices',
    ];
    static values = { components: Array, mediaUrl: String, quickAddUrl: String, artworkUrl: String, label: String };

    async connect() {
        this.definitions = new Map(this.componentsValue.map((definition) => [definition.name, definition]));
        this.media = { image: new Map(), document: new Map(), icon: new Map(), pictogram: new Map() };
        this.openItems = new Set();
        const Components = createComponentsPlugin(this.componentsValue, {
            open: (options) => this.open(options),
            renderPreview: (dom, definition, values) => this.renderPreview(dom, definition, values),
        });

        try {
            const editor = await ClassicEditor.create(this.sourceTarget, {
                licenseKey: 'GPL',
                language: 'fr',
                translations: [translations],
                label: this.labelValue,
                plugins: [Essentials, Paragraph, Heading, Bold, Italic, RemoveFormat, Link, List, HorizontalLine, Autoformat, PasteFromOffice, Table, TableToolbar, Components],
                toolbar: {
                    items: ['heading', '|', 'bold', 'italic', 'removeFormat', '|', 'link', 'bulletedList', 'numberedList', 'horizontalLine', '|', 'insertComponent', 'insertTable', '|', 'undo', 'redo'],
                    shouldNotGroupWhenFull: true,
                },
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraphe', class: 'ck-heading_paragraph' },
                        { model: 'heading2', view: 'h2', title: 'Titre de section', class: 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Sous-titre', class: 'ck-heading_heading3' },
                        { model: 'heading4', view: 'h4', title: 'Titre de niveau 4', class: 'ck-heading_heading4' },
                    ],
                },
                link: { defaultProtocol: 'https://', allowedProtocols: ['https?', 'mailto', 'tel'] },
                // Lignes et colonnes (en-têtes compris) et fusion de cellules ; le style du tableau vient du composant Tableau.
                table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'] },
            });
            // Page quittée pendant le chargement
            if (!this.element.isConnected) return editor.destroy();
            this.editor = editor;
        } catch (error) {
            console.error('Éditeur CKEditor DSFR :', error);
            this.statusTarget.textContent = 'L’éditeur n’a pas pu être chargé : le contenu reste modifiable en HTML.';
            return;
        }

        this.statusTarget.remove();
        // Zone de texte tenue à jour : le formulaire envoie toujours le dernier contenu.
        this.editor.model.document.on('change:data', () => this.editor.updateSourceElement());
        this.watchColumnWidths();
        this.beforeCache = () => this.destroy();
        document.addEventListener('turbo:before-cache', this.beforeCache);

        await this.loadMedia(['image', 'document']);
        this.editor?.plugins.get('DsfrComponents').refreshPreviews();
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.beforeCache);
        this.destroy();
    }

    destroy() {
        if (!this.editor) return;
        const editor = this.editor;
        this.editor = null;
        editor.updateSourceElement();
        editor.destroy();
    }

    // --- Largeur des colonnes ------------------------------------------------------------------------------------

    watchColumnWidths() {
        const editor = this.editor;
        const plugin = editor.plugins.get('DsfrComponents');
        // Un contenu déjà trop large à l'ouverture reste modifiable : seule une nouvelle colonne trop étroite est refusée.
        let known = plugin.widthViolations().length;
        editor.model.document.on('change:data', (event, batch) => {
            const violations = plugin.widthViolations();
            if (violations.length > known && batch.isUndoable && batch.isLocal) {
                const { column, need, span } = violations[violations.length - 1];
                setTimeout(() => editor.execute('undo', batch));
                this.notify(`Ce contenu demande au moins ${need} colonnes sur 12 : la colonne ${column} du bloc « Colonnes » n’en fait que ${span}. `
                    + 'Placez-le au-dessus ou en dessous du bloc, ou choisissez une disposition plus large.');
                return;
            }
            known = violations.length;
        });
    }

    /** Alerte DSFR en haut à droite (annoncée aux lecteurs d'écran), fermée par son bouton ou après 10 secondes. */
    notify(message) {
        const alert = document.createElement('div');
        alert.className = 'fr-alert fr-alert--warning fr-alert--sm';
        alert.setAttribute('role', 'alert');
        const text = document.createElement('p');
        text.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'fr-btn--close fr-btn';
        close.title = 'Masquer le message';
        close.textContent = 'Masquer le message';
        close.addEventListener('click', () => { alert.remove(); this.editor?.editing.view.focus(); });
        alert.append(text, close);
        this.noticesTarget.replaceChildren(alert);
        setTimeout(() => alert.remove(), NOTICE_DELAY);
    }

    // --- Fenêtre de réglages ---------------------------------------------------------------------------------------

    async open({ mode, inSlot = false, inColumn = false, element = null, name = null, values = {} }) {
        this.dialogState = { mode, element, name: null };
        this.libraryState = null;
        this.cancelMedia();
        this.libraryTarget.hidden = true;
        this.footerTarget.hidden = false;
        this.titleTarget.textContent = 'edit' === mode ? `Modifier : ${this.definitions.get(name).label}` : 'Insérer un composant';
        this.chooserTarget.hidden = 'edit' === mode;
        // Dans la zone de contenu d'un composant, pas de composant à zones (pas d'accordéon dans un accordéon) ; dans
        // une colonne, pas d'autre bloc « Colonnes ».
        this.choiceTargets.forEach((item) => {
            item.hidden = (inSlot && '1' === item.dataset.box) || ((inSlot || inColumn) && '1' === item.dataset.layout);
        });
        this.panelTargets.forEach((panel) => { panel.hidden = true; });
        this.submitTarget.hidden = true;
        this.backTarget.hidden = true;
        this.showDialog();

        await this.loadMedia(['image', 'document', 'icon', 'pictogram']);
        if ('edit' === mode) this.showPanel(name, values);
    }

    choose({ params: { name } }) {
        const definition = this.definitions.get(name);
        if (!definition.fields.length) {
            this.hideDialog();
            this.editor.execute('insertComponent', { name, values: {} });
            return;
        }
        this.showPanel(name, {});
    }

    back() {
        this.dialogState.name = null;
        this.panelTargets.forEach((panel) => { panel.hidden = true; });
        this.chooserTarget.hidden = false;
        this.submitTarget.hidden = true;
        this.backTarget.hidden = true;
        this.chooserTarget.querySelector('button:not([hidden])')?.focus();
    }

    showPanel(name, values) {
        const definition = this.definitions.get(name);
        this.dialogState.name = name;
        this.chooserTarget.hidden = true;
        const panel = this.panelFor(name);
        this.panelTargets.forEach((item) => { item.hidden = item !== panel; });

        this.openItems.clear();
        definition.fields.forEach((field) => {
            const input = panel.querySelector(`[data-field="${field.attribute}"]`);
            input.value = values[field.attribute] ?? field.default ?? '';
            const settings = this.settingsInput(input);
            if (settings) settings.value = values[field.settingsAttribute] ?? '';
            this.setError(input, '');
            if (this.isMedia(field)) this.renderMediaField(input);
        });

        this.applyConditions(name);
        this.submitTarget.textContent = 'edit' === this.dialogState.mode ? 'Enregistrer' : 'Insérer';
        this.submitTarget.hidden = false;
        this.backTarget.hidden = 'edit' === this.dialogState.mode;
        this.focusable(panel.querySelector('[data-field]'))?.focus();
    }

    isMedia(field) {
        return 'media' === field.type || 'medias' === field.type;
    }

    // Élément à focaliser pour un champ : le bouton « Sélectionner… » d'un champ média (champ caché).
    focusable(input) {
        return input?.type === 'hidden' ? input.closest('[data-field-group]').querySelector('[data-media-open]') : input;
    }

    submit() {
        const { name, mode, element } = this.dialogState ?? {};
        if (!name) return;
        const definition = this.definitions.get(name);
        const panel = this.panelFor(name);
        const values = {};
        let firstInvalid = null;

        definition.fields.forEach((field) => {
            const input = panel.querySelector(`[data-field="${field.attribute}"]`);
            // Réglage non proposé pour l'affichage choisi : ni contrôlé ni enregistré.
            if (this.isHiddenField(input)) return;
            const value = input.value.trim();
            let error = '';
            if (field.required && '' === value) error = 'Ce champ est obligatoire.';
            else if ('url' === field.type && value && !SAFE_URL.test(value)) error = 'Adresse non valide : https://…, mailto:…, tel:… ou page du site commençant par /.';
            else if ('video' === name && 'url' === field.type && value && !value.toLowerCase().startsWith('https://')) error = 'La vidéo doit être une adresse https://.';
            this.setError(input, error);
            if (error) firstInvalid ??= input;
            if (value) values[field.attribute] = value;
            // Réglages par média : gardés pour les médias encore choisis, si l'affichage les utilise.
            if (field.items && value && this.itemsShown(input)) {
                const ids = value.split(',');
                const settings = Object.fromEntries(Object.entries(this.readSettings(input)).filter(([id]) => ids.includes(id)));
                if (Object.keys(settings).length) values[field.settingsAttribute] = JSON.stringify(settings);
            }
        });

        if (firstInvalid) {
            this.focusable(firstInvalid).focus();
            return;
        }

        this.hideDialog();
        if ('edit' === mode) this.editor.execute('updateComponent', { element, values });
        else this.editor.execute('insertComponent', { name, values });
    }

    // --- Réglages conditionnels (ComponentField::when…) : proposés seulement si l'affichage choisi les utilise -----

    refreshConditions() {
        if (this.dialogState?.name) this.applyConditions(this.dialogState.name);
    }

    applyConditions(name) {
        const definition = this.definitions.get(name);
        const panel = this.panelFor(name);
        // Deux passes : un réglage masqué compte comme vide pour les conditions des autres.
        for (let pass = 0; pass < 2; pass++) {
            definition.fields.forEach((field) => {
                const input = panel.querySelector(`[data-field="${field.attribute}"]`);
                input.closest('.fr-fieldset__element').hidden = !this.conditionMet(field.showIf, panel);
                if (!field.optionShowIf || 'SELECT' !== input.tagName) return;
                [...input.options].forEach((option) => {
                    const condition = field.optionShowIf[option.value];
                    option.hidden = option.disabled = Boolean(condition) && !this.conditionMet(condition, panel);
                });
                if (input.selectedOptions[0]?.disabled) input.value = [...input.options].find((option) => !option.disabled)?.value ?? '';
            });
        }
        // Réglages par média proposés seulement si l'affichage les utilise (ex. documents en cartes ou tuiles).
        definition.fields.filter((field) => field.items).forEach((field) => {
            const input = panel.querySelector(`[data-field="${field.attribute}"]`);
            input.closest('[data-field-group]').querySelector('[data-media-field]').classList.toggle('cmp-media--no-items', !this.conditionMet(field.itemsShowIf, panel));
        });
    }

    conditionMet(condition, panel) {
        if (!condition) return true;
        const values = condition.field.map((attribute) => {
            const input = panel.querySelector(`[data-field="${attribute}"]`);
            return input && !this.isHiddenField(input) ? input.value.trim() : '';
        });
        if (condition.values) return condition.values.includes(values[0]);
        const filled = values.some((value) => '' !== value);
        return condition.filled ? filled : !filled;
    }

    isHiddenField(input) {
        return Boolean(input.closest('.fr-fieldset__element')?.hidden);
    }

    panelFor(name) {
        return this.panelTargets.find((panel) => panel.dataset.componentName === name);
    }

    setError(input, message) {
        const group = input.closest('[data-field-group]');
        const error = group.querySelector('[data-field-error]');
        const kind = 'SELECT' === input.tagName ? 'select' : 'input';
        const target = this.focusable(input);
        error.textContent = message;
        error.hidden = !message;
        group.classList.toggle(`fr-${kind}-group--error`, Boolean(message));
        target.classList.toggle(`fr-${kind}--error`, Boolean(message) && target === input);
        // Bouton d'un champ média : son libellé reste décrit par le nom du champ.
        const described = target === input ? [] : [`${input.id}-label`];
        if (message) {
            target.setAttribute('aria-invalid', 'true');
            target.setAttribute('aria-describedby', [...described, error.id].join(' '));
        } else {
            target.removeAttribute('aria-invalid');
            if (described.length) target.setAttribute('aria-describedby', described.join(' '));
            else target.removeAttribute('aria-describedby');
        }
    }

    // Ouverture et fermeture par les boutons DSFR : le DSFR gère le piège du focus et la touche Échap.
    showDialog() {
        this.openerTarget.click();
    }

    hideDialog() {
        this.dialogTarget.querySelector('.fr-btn--close').click();
    }

    // Fenêtre fermée (bouton, Échap, insertion) : retour du focus dans l'éditeur.
    focusEditor() {
        this.editor?.editing.view.focus();
    }

    // --- Médias -----------------------------------------------------------------------------------------------------

    // Médiathèque (rechargée : un média a pu être ajouté) ; icônes et pictogrammes du DSFR (liste fixe, chargée une fois).
    async loadMedia(types) {
        const artwork = types.some((type) => 'icon' === type || 'pictogram' === type);
        await Promise.all([
            ...types.filter((type) => 'image' === type || 'document' === type).map(async (type) => {
                const list = await this.fetchJson(this.mediaUrlValue.replace('__TYPE__', type));
                if (list) this.media[type] = new Map(list.map((media) => [String(media.id), media]));
            }),
            artwork && !this.artworkLoaded ? this.fetchJson(this.artworkUrlValue).then((lists) => {
                if (!lists) return;
                ['icon', 'pictogram'].forEach((type) => { this.media[type] = new Map(lists[type].map((item) => [item.id, item])); });
                this.artworkLoaded = true;
            }) : null,
        ]);
    }

    async fetchJson(url) {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            return response.ok ? await response.json() : null;
        } catch (error) {
            console.error('Médiathèque indisponible :', error);
            return null;
        }
    }

    mediaOf(type, id) {
        return this.media[type].get(String(id));
    }

    // Médias choisis d'un champ : vignette ou icône, titre et détail ; ordre et retrait (glisser-déposer pour plusieurs).
    renderMediaField(input) {
        const field = input.closest('[data-field-group]').querySelector('[data-media-field]');
        const { mediaType: type, multiple } = field.dataset;
        const list = field.querySelector('[data-media-list]');
        const ids = input.value.split(',').filter(Boolean);
        list.replaceChildren(...ids.map((id, index) => {
            const media = this.mediaOf(type, id);
            const title = media ? media.title : `Média n° ${id} (introuvable)`;
            const item = document.createElement('li');
            item.className = 'cmp-media__item';
            item.dataset.id = id;
            if ('1' === multiple) item.append(Object.assign(document.createElement('span'), { className: 'fr-icon-drag-move-2-line cmp-media__handle', ariaHidden: 'true', title: 'Glisser pour déplacer' }));
            item.append(this.mediaVisual(type, media));
            const text = document.createElement('span');
            text.className = 'cmp-media__text';
            text.append(Object.assign(document.createElement('strong'), { textContent: title }), media ? ` ${media.detail}` : '');
            item.append(text);
            const actions = document.createElement('span');
            actions.className = 'cmp-media__actions';
            if ('1' === multiple) {
                actions.append(
                    this.mediaButton('Monter', 'fr-icon-arrow-up-line', title, 'moveMedia', -1, 0 === index),
                    this.mediaButton('Descendre', 'fr-icon-arrow-down-line', title, 'moveMedia', 1, index === ids.length - 1),
                );
            }
            const fieldDefinition = this.fieldOf(input);
            if (fieldDefinition?.items) {
                const { toggle, box } = this.itemSettings(input, fieldDefinition, id, title);
                actions.append(toggle);
                item.append(actions, box);
                actions.append(this.mediaButton('Retirer', 'fr-icon-delete-line', title, 'removeMedia'));
                return item;
            }
            actions.append(this.mediaButton('Retirer', 'fr-icon-delete-line', title, 'removeMedia'));
            item.append(actions);
            return item;
        }));
        field.querySelector('[data-media-empty]').hidden = ids.length > 0;
        if (this.dialogState?.name) this.applyConditions(this.dialogState.name);
        if ('1' === multiple && !list.sortable) {
            list.sortable = Sortable.create(list, { animation: 150, handle: '.cmp-media__handle', onEnd: () => this.syncMediaField(input) });
        }
    }

    // --- Réglages par média (ComponentField items) -------------------------------------------------------------------

    fieldOf(input) {
        return this.definitions.get(this.dialogState?.name)?.fields.find((field) => field.attribute === input.dataset.field);
    }

    settingsInput(input) {
        return input.closest('[data-field-group]')?.querySelector('[data-settings-field]') ?? null;
    }

    itemsShown(input) {
        return !input.closest('[data-field-group]').querySelector('[data-media-field]').classList.contains('cmp-media--no-items');
    }

    readSettings(input) {
        try {
            const settings = JSON.parse(this.settingsInput(input)?.value || '{}');
            return settings && 'object' === typeof settings && !Array.isArray(settings) ? settings : {};
        } catch {
            return {};
        }
    }

    setItemSetting(input, id, name, value) {
        const settings = this.readSettings(input);
        const item = { ...(settings[id] ?? {}) };
        if (value) item[name] = value;
        else delete item[name];
        if (Object.keys(item).length) settings[id] = item;
        else delete settings[id];
        this.settingsInput(input).value = JSON.stringify(settings);
        return Object.keys(item).length;
    }

    // Bouton « Personnaliser » d'un média et ses réglages (repliés), qui priment sur ceux du composant.
    itemSettings(input, field, id, title) {
        const settings = this.readSettings(input)[id] ?? {};
        const key = `${field.attribute}:${id}`;
        const boxId = `${input.id}-item-${id}`;
        const open = this.openItems.has(key);
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'fr-btn fr-btn--tertiary-no-outline fr-btn--sm fr-btn--icon-left fr-icon-settings-5-line cmp-media__customize';
        const label = (count) => (count ? `Personnaliser (${count})` : 'Personnaliser');
        toggle.textContent = label(Object.keys(settings).length);
        toggle.setAttribute('aria-label', `Personnaliser « ${title} »`);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-controls', boxId);
        toggle.addEventListener('click', () => {
            if (this.openItems.has(key)) this.openItems.delete(key);
            else this.openItems.add(key);
            this.renderMediaField(input);
            this.settingsInput(input).closest('[data-field-group]').querySelector(`[aria-controls="${boxId}"]`)?.focus();
        });

        const box = document.createElement('div');
        box.id = boxId;
        box.className = 'cmp-media__settings';
        box.hidden = !open;
        const update = (name, value) => { toggle.textContent = label(this.setItemSetting(input, id, name, value)); };
        field.items.forEach((itemField) => box.append(this.itemControl(input, field, id, itemField, settings[itemField.name] ?? '', update)));
        return { toggle, box };
    }

    itemControl(input, field, id, itemField, value, update) {
        const controlId = `${input.id}-item-${id}-${itemField.name}`;
        const group = document.createElement('div');
        group.className = `${'choice' === itemField.type ? 'fr-select-group' : 'fr-input-group'} fr-mb-2v`;
        const artwork = 'icon' === itemField.type || 'pictogram' === itemField.type;
        const label = document.createElement(artwork ? 'p' : 'label');
        label.className = 'fr-label';
        if (artwork) label.id = `${controlId}-label`;
        else label.htmlFor = controlId;
        label.textContent = itemField.label;
        if (itemField.hint) label.append(Object.assign(document.createElement('span'), { className: 'fr-hint-text', textContent: itemField.hint }));
        group.append(label);

        if ('choice' === itemField.type) {
            const select = Object.assign(document.createElement('select'), { className: 'fr-select', id: controlId });
            select.append(new Option('Comme le composant', ''), ...Object.entries(itemField.options).map(([option, text]) => new Option(text, option)));
            select.value = value;
            select.addEventListener('change', () => update(itemField.name, select.value));
            group.append(select);
        } else if (!artwork) {
            const text = Object.assign(document.createElement('input'), { className: 'fr-input', type: 'text', id: controlId, value, maxLength: itemField.maxLength });
            text.addEventListener('change', () => update(itemField.name, text.value.trim()));
            group.append(text);
        } else {
            // Icône ou pictogramme du DSFR : choisi dans l'étape « Médiathèque » de la fenêtre.
            const media = value ? this.mediaOf(itemField.type, value) : null;
            const choice = document.createElement('p');
            choice.className = 'cmp-media__choice fr-mb-1v';
            if (media) choice.append(this.mediaVisual(itemField.type, media));
            choice.append(media ? media.title : 'Comme le composant');
            const open = this.mediaButton('Choisir', 'fr-icon-palette-line', itemField.label, 'openLibrary');
            open.setAttribute('aria-describedby', label.id);
            open.setAttribute(`data-${this.identifier}-field-param`, field.attribute);
            open.setAttribute(`data-${this.identifier}-item-param`, id);
            open.setAttribute(`data-${this.identifier}-setting-param`, itemField.name);
            open.dataset.itemOpen = `${id}:${itemField.name}`;
            group.append(choice, open);
            if (value) {
                const reset = this.mediaButton('Retirer', 'fr-icon-close-line', itemField.label, 'resetItemSetting');
                reset.setAttribute(`data-${this.identifier}-field-param`, field.attribute);
                reset.setAttribute(`data-${this.identifier}-item-param`, id);
                reset.setAttribute(`data-${this.identifier}-setting-param`, itemField.name);
                group.append(reset);
            }
        }
        return group;
    }

    resetItemSetting({ params: { field, item, setting } }) {
        const input = this.panelFor(this.dialogState.name).querySelector(`[data-field="${field}"]`);
        this.setItemSetting(input, String(item), setting, '');
        this.renderMediaField(input);
        input.closest('[data-field-group]').querySelector(`[data-item-open="${item}:${setting}"]`)?.focus();
    }

    mediaVisual(type, media) {
        if ('image' === type && media) {
            return Object.assign(document.createElement('img'), { className: 'cmp-media__thumb', src: media.url, alt: '' });
        }
        if ('icon' === type && media) {
            return Object.assign(document.createElement('span'), { className: `cmp-media__icon ${media.id} fr-icon--lg`, ariaHidden: 'true' });
        }
        if ('pictogram' === type && media) {
            // Pictogramme DSFR : couches de l'illustration (comme la macro Twig ui.pictogram).
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'fr-artwork cmp-media__pictogram');
            svg.setAttribute('viewBox', '0 0 80 80');
            svg.setAttribute('aria-hidden', 'true');
            ['decorative', 'minor', 'major'].forEach((layer) => {
                const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
                use.setAttribute('class', `fr-artwork-${layer}`);
                use.setAttribute('href', `${media.url}#artwork-${layer}`);
                svg.append(use);
            });
            return svg;
        }
        return Object.assign(document.createElement('span'), { className: `cmp-media__icon ${'image' === type ? 'fr-icon-image-line' : 'fr-icon-file-text-line'}`, ariaHidden: 'true' });
    }

    mediaButton(label, icon, title, action, direction = 0, disabled = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `fr-btn fr-btn--tertiary-no-outline fr-btn--sm fr-btn--icon-left ${icon}`;
        button.textContent = label;
        button.setAttribute('aria-label', `${label} « ${title} »`);
        button.dataset.action = `${this.identifier}#${action}`;
        button.setAttribute(`data-${this.identifier}-direction-param`, String(direction));
        button.disabled = disabled;
        return button;
    }

    syncMediaField(input) {
        const list = input.closest('[data-field-group]').querySelector('[data-media-list]');
        input.value = [...list.children].map((item) => item.dataset.id).join(',');
        this.renderMediaField(input);
    }

    moveMedia(event) {
        const item = event.currentTarget.closest('[data-id]');
        const input = item.closest('[data-field-group]').querySelector('[data-field]');
        const direction = Number(event.params.direction);
        const ids = input.value.split(',');
        const index = ids.indexOf(item.dataset.id);
        const target = index + direction;
        if (target < 0 || target >= ids.length) return;
        [ids[index], ids[target]] = [ids[target], ids[index]];
        input.value = ids.join(',');
        this.renderMediaField(input);
        // Focus gardé sur le même bouton de l'élément déplacé (annoncé par sa nouvelle position).
        const moved = input.closest('[data-field-group]').querySelector(`[data-id="${item.dataset.id}"]`);
        const buttons = [...moved.querySelectorAll('button')];
        (buttons.find((b) => b.textContent === event.currentTarget.textContent && !b.disabled) ?? buttons.at(-1)).focus();
    }

    removeMedia(event) {
        const item = event.currentTarget.closest('[data-id]');
        const group = item.closest('[data-field-group]');
        const input = group.querySelector('[data-field]');
        input.value = input.value.split(',').filter((id) => id !== item.dataset.id).join(',');
        this.renderMediaField(input);
        group.querySelector('[data-media-open]').focus();
    }

    // --- Étape « Médiathèque » -------------------------------------------------------------------------------------

    async openLibrary({ params: { field, item = null, setting = null } }) {
        const panel = this.panelFor(this.dialogState.name);
        const input = panel.querySelector(`[data-field="${field}"]`);
        let { mediaType: type, multiple } = input.closest('[data-field-group]').querySelector('[data-media-field]').dataset;
        let selected = input.value.split(',').filter(Boolean);
        if (null !== item) {
            // Réglage d'un média (icône ou pictogramme) : un seul choix, enregistré dans ses réglages.
            item = String(item);
            type = this.fieldOf(input).items.find((itemField) => itemField.name === setting).type;
            multiple = '0';
            const current = this.readSettings(input)[item]?.[setting];
            selected = current ? [current] : [];
        }
        this.libraryState = { input, item, setting, type, multiple: '1' === multiple, selected, title: this.titleTarget.textContent };

        panel.hidden = true;
        this.footerTarget.hidden = true;
        this.libraryTarget.hidden = false;
        this.titleTarget.textContent = { image: 'Médiathèque : images', document: 'Médiathèque : documents', icon: 'Icônes du DSFR', pictogram: 'Pictogrammes du DSFR' }[type];
        // Icônes et pictogrammes : liste du DSFR, pas d'ajout.
        this.libraryAddButtonTarget.hidden = 'icon' === type || 'pictogram' === type;
        this.librarySearchTarget.value = '';
        await this.loadMedia([type]);
        this.renderLibrary();
        this.librarySearchTarget.focus();
    }

    renderLibrary() {
        const { type, multiple, selected } = this.libraryState;
        const plain = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        const term = plain(this.librarySearchTarget.value.trim());
        const found = [...this.media[type].values()].filter((media) => !term || plain(`${media.title} ${media.detail}`).includes(term));
        // Plus de 1 000 icônes : affichage limité, la recherche affine.
        const medias = found.slice(0, LIBRARY_LIMIT);
        const name = `${this.dialogTarget.id}-library-choice`;

        this.libraryGridTarget.replaceChildren(...medias.map((media) => {
            const id = String(media.id);
            const item = document.createElement('li');
            const input = Object.assign(document.createElement('input'), {
                type: multiple ? 'checkbox' : 'radio', name, value: id, id: `${name}-${id}`, checked: selected.includes(id), className: 'cmp-library__input',
            });
            input.addEventListener('change', () => this.toggleLibrary(id, input.checked));
            const label = Object.assign(document.createElement('label'), { className: 'cmp-library__item', htmlFor: input.id });
            label.append(this.mediaVisual(type, media));
            label.append(Object.assign(document.createElement('span'), { className: 'cmp-library__title', textContent: media.title }));
            label.append(Object.assign(document.createElement('span'), { className: 'fr-hint-text', textContent: media.detail }));
            item.append(input, label);
            return item;
        }));
        if (!medias.length) {
            this.libraryGridTarget.append(Object.assign(document.createElement('li'), {
                className: 'fr-text--sm', textContent: term ? 'Aucun résultat pour cette recherche.' : 'La médiathèque ne contient encore aucun média de ce type.',
            }));
        } else if (found.length > medias.length) {
            this.libraryGridTarget.append(Object.assign(document.createElement('li'), {
                className: 'fr-text--sm cmp-library__more', textContent: `${medias.length} premiers résultats sur ${found.length} : précisez la recherche (ex. « alert », « mail », « arrow »).`,
            }));
        }
        this.updateLibraryStatus();
    }

    toggleLibrary(id, checked) {
        const state = this.libraryState;
        if (!state.multiple) state.selected = checked ? [id] : [];
        else if (checked && !state.selected.includes(id)) state.selected.push(id); // ajouté à la fin de l'ordre d'affichage
        else if (!checked) state.selected = state.selected.filter((item) => item !== id);
        this.updateLibraryStatus();
    }

    updateLibraryStatus() {
        const count = this.libraryState.selected.length;
        this.libraryStatusTarget.textContent = `${count} sélectionné${count > 1 ? 's' : ''}`;
        this.libraryValidateTarget.textContent = this.libraryState.multiple ? `Valider la sélection (${count})` : 'Valider la sélection';
    }

    // --- Ajout à la médiathèque depuis l'étape « Médiathèque » ----------------------------------------------------

    async addMedia() {
        const html = await this.fetchQuickForm();
        if (null === html) return;
        this.showQuickForm(html);
    }

    // Envoi sans validation du navigateur (contrôles faits par le serveur, erreurs affichées dans les champs).
    async saveMedia(event) {
        const button = event?.currentTarget;
        if (this.savingMedia) return;
        this.savingMedia = true;
        if (button) {
            button.disabled = true;
            button.dataset.label = button.textContent;
            button.textContent = 'Envoi en cours…';
        }
        this.libraryStatusTarget.textContent = 'Envoi du fichier à la médiathèque…';
        let response;
        try {
            response = await fetch(this.quickAddUrl, { method: 'POST', body: this.quickFormData(), headers: { Accept: 'application/json' } });
        } catch (error) {
            console.error('Médiathèque indisponible :', error);
            response = null;
        } finally {
            this.savingMedia = false;
            if (button?.isConnected) {
                button.disabled = false;
                button.textContent = button.dataset.label;
            }
        }
        if (response && 422 === response.status) {
            this.showQuickForm(await response.text());
            this.libraryStatusTarget.textContent = 'Le fichier n’a pas été ajouté : corrigez les champs signalés.';
            return;
        }
        if (!response?.ok) {
            this.libraryStatusTarget.textContent = 'L’ajout à la médiathèque a échoué. Réessayez.';
            return;
        }
        const media = await response.json();
        const { type } = this.libraryState;
        this.media[type].set(String(media.id), media);
        this.cancelMedia();
        this.toggleLibrary(String(media.id), true);
        this.renderLibrary();
        this.libraryStatusTarget.textContent = `« ${media.title} » a été ajouté à la médiathèque et sélectionné.`;
        this.libraryGridTarget.querySelector(`input[value="${media.id}"]`)?.focus();
    }

    // Champs du formulaire d'ajout (fichier compris), sans formulaire HTML.
    quickFormData() {
        const data = new FormData();
        this.libraryAddTarget.querySelectorAll('input, select, textarea').forEach((control) => {
            if (!control.name || control.disabled) return;
            if ('file' === control.type) {
                [...control.files].forEach((file) => data.append(control.name, file));
            } else if (!['checkbox', 'radio'].includes(control.type) || control.checked) {
                data.append(control.name, control.value);
            }
        });
        return data;
    }

    cancelMedia() {
        this.libraryAddTarget.replaceChildren();
        this.libraryAddTarget.hidden = true;
        this.libraryAddButtonTarget.hidden = false;
    }

    async fetchQuickForm() {
        try {
            const response = await fetch(this.quickAddUrl, { headers: { Accept: 'text/html' } });
            if (response.ok) return response.text();
        } catch (error) {
            console.error('Médiathèque indisponible :', error);
        }
        this.libraryStatusTarget.textContent = 'Le formulaire d’ajout n’a pas pu être chargé. Réessayez.';
        return null;
    }

    // Champs rendus par le serveur, détachés du formulaire de la page (attribut form vers un formulaire inexistant) :
    // ils ne partent pas avec la page et ne bloquent pas son envoi.
    showQuickForm(html) {
        // Champs réaffichés avec des erreurs : le fichier déjà choisi est conservé.
        const files = this.libraryAddTarget.querySelector('input[type="file"]')?.files;
        this.libraryAddTarget.innerHTML = html;
        const fileInput = this.libraryAddTarget.querySelector('input[type="file"]');
        if (files?.length && fileInput) fileInput.files = files;
        const controls = this.libraryAddTarget.querySelectorAll('input, select, textarea');
        controls.forEach((control) => control.setAttribute('form', `${this.dialogTarget.id}-detached`));
        // Fichier choisi : titre proposé d'après le nom du fichier, bouton d'enregistrement montré.
        this.libraryAddTarget.querySelector('input[type="file"]')?.addEventListener('change', (event) => {
            const file = event.target.files[0];
            const title = this.libraryAddTarget.querySelector('input[name$="[title]"]');
            if (file && title && '' === title.value.trim()) {
                title.value = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
            }
            if (file) {
                this.libraryStatusTarget.textContent = `Fichier « ${file.name} » prêt : complétez si besoin, puis « Enregistrer et sélectionner ».`;
                this.libraryAddTarget.querySelector(`[data-action~="${this.identifier}#saveMedia"]`)?.scrollIntoView({ block: 'nearest' });
            }
        });
        this.libraryAddTarget.hidden = false;
        this.libraryAddButtonTarget.hidden = true;
        (this.libraryAddTarget.querySelector('[aria-invalid="true"]') ?? this.libraryAddTarget.querySelector('input:not([type=hidden])'))?.focus();
    }

    get quickAddUrl() {
        return this.quickAddUrlValue.replace('__TYPE__', this.libraryState.type);
    }

    validateLibrary() {
        const { input, selected, item, setting } = this.libraryState;
        if (null !== item) {
            this.setItemSetting(input, item, setting, selected[0] ?? '');
            this.renderMediaField(input);
            this.closeLibrary();
            return;
        }
        input.value = selected.join(',');
        this.renderMediaField(input);
        this.setError(input, '');
        this.closeLibrary();
    }

    closeLibrary() {
        this.cancelMedia();
        const { input, title, item, setting } = this.libraryState;
        this.libraryState = null;
        this.libraryTarget.hidden = true;
        this.footerTarget.hidden = false;
        this.titleTarget.textContent = title;
        this.panelFor(this.dialogState.name).hidden = false;
        const group = input.closest('[data-field-group]');
        ((null !== item ? group.querySelector(`[data-item-open="${item}:${setting}"]`) : null) ?? group.querySelector('[data-media-open]')).focus();
    }

    // --- Aperçus dans l'éditeur (texte uniquement : aucune saisie n'est interprétée comme du HTML) ------------------

    renderPreview(dom, definition, values) {
        const title = document.createElement('p');
        title.className = 'cmp__label';
        const icon = document.createElement('span');
        icon.className = `${definition.icon} fr-icon--sm`;
        icon.setAttribute('aria-hidden', 'true');
        title.append(icon, ` ${definition.label}`);
        const details = document.createElement('ul');
        details.className = 'cmp__details';

        definition.fields.forEach((field) => {
            const value = values[field.attribute];
            if (!value) return;
            let text = value;
            if ('choice' === field.type) text = field.options?.[value] ?? value;
            if ('medias' === field.type) {
                const titles = value.split(',').map((id) => this.mediaOf(field.mediaType, id)?.title ?? `média n° ${id}`);
                text = `${titles.length} : ${titles.join(', ')}`;
            }
            if ('media' === field.type) {
                const media = this.mediaOf(field.mediaType, value);
                text = media ? `${media.title} (${media.detail})` : `média n° ${value} (introuvable ou en cours de chargement)`;
                if (media && 'image' === field.mediaType) {
                    const image = document.createElement('img');
                    image.className = `cmp__thumb fr-ratio-${values['data-ratio'] ?? '16x9'}`;
                    image.src = media.url;
                    image.alt = '';
                    dom.append(image);
                }
            }
            const item = document.createElement('li');
            const label = document.createElement('span');
            label.className = 'cmp__field';
            label.textContent = `${field.label} : `;
            item.append(label, text.length > 140 ? `${text.slice(0, 140)}…` : text);
            details.append(item);
        });

        dom.prepend(title);
        if (details.childElementCount) dom.append(details);
    }
}
