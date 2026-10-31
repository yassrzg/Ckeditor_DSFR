import { ButtonView, Command, Plugin, TableUtils, Widget, WidgetToolbarRepository, toWidget, toWidgetEditable } from 'ckeditor5';

/*
 * Plugin CKEditor 5 des composants DSFR, décrit par la liste unique côté serveur (Yassrzg\CkeditorDsfrBundle\Editor\ComponentRegistry).
 *
 * HTML enregistré (le rendu DSFR est fait à l'affichage par le serveur) :
 *   composant « atomique » : <div data-component="image" data-media-id="12" data-ratio="16x9"></div>
 *   composant à zones      : <div data-component="accordion"><div data-slot="title">…</div><div data-slot="body"><p>…</p></div></div>
 *
 *   bloc « Colonnes »      : <div data-component="columns" data-columns="4-8"><div data-slot="column">…</div><div data-slot="column">…</div></div>
 *
 * Modèle : cmpAtom (sans zone), cmpBox (avec zones cmpSlotInline / cmpSlotBlock), cmpLayout (bloc de mise en page,
 * une cmpColumn par largeur de sa disposition). Une zone de contenu et une colonne acceptent tous les composants (colonnes
 * dans un accordéon, tableau dans un onglet…) ; aucun composant dans un titre (zone sur une ligne) ni dans une cellule.
 * Un tableau (plugin Table de CKEditor) ne se place que dans une zone « tables » (composant Tableau) ; aucun composant
 * dans une cellule. Réglages par média (champ medias avec items) : attribut data-<champ>-settings (JSON).
 * L'interface (fenêtre de réglages, aperçus) est fournie par le contrôleur Stimulus ckeditor-dsfr (paramètre ui).
 */
export const ATOM = 'cmpAtom';
export const BOX = 'cmpBox';
export const LAYOUT = 'cmpLayout';
const COLUMN = 'cmpColumn';
const SLOT_INLINE = 'cmpSlotInline';
const SLOT_BLOCK = 'cmpSlotBlock';
const GRID = 12;
const COMPONENTS = [ATOM, BOX, LAYOUT];

export function createComponentsPlugin(definitions, ui) {
    const byName = new Map(definitions.map((definition) => [definition.name, definition]));
    // Attributs enregistrés d'un composant : ses réglages et, pour un champ medias avec items, les réglages par média.
    const fieldAttributes = (definition) => definition.fields.flatMap((field) => [field.attribute, field.settingsAttribute].filter(Boolean));
    const attributes = [...new Set(definitions.flatMap(fieldAttributes))];
    // Zones qui reçoivent un tableau : « composant/zone ».
    const tableSlots = new Set(definitions.flatMap((definition) => definition.slots.filter((slot) => slot.tables).map((slot) => `${definition.name}/${slot.name}`)));
    const inlineSlots = new Set(definitions.flatMap((definition) => definition.slots.filter((slot) => slot.inline).map((slot) => slot.name)));

    const valuesOf = (element) => Object.fromEntries(attributes.filter((name) => element.hasAttribute(name)).map((name) => [name, element.getAttribute(name)]));
    const dataAttributes = (element) => ({ 'data-component': element.getAttribute('cmpName'), ...valuesOf(element) });
    const modelName = (definition) => {
        if (definition.layout) return LAYOUT;
        return definition.slots.length ? BOX : ATOM;
    };
    // Largeurs des colonnes d'un bloc « Colonnes » (« 4-8 » → [4, 8]), disposition par défaut sinon.
    const spansOf = (definition, value) => {
        const layouts = definition.fields.find((field) => 'data-columns' === field.attribute)?.options ?? {};
        return (value in layouts ? value : Object.keys(layouts)[0] ?? '6-6').split('-').map(Number);
    };
    const isLayoutSlot = (view) => Boolean(byName.get(view.parent?.getAttribute?.('data-component'))?.layout);
    const slotLabel = (slot) => {
        const definition = byName.get(slot.parent?.getAttribute?.('cmpName'));
        return definition?.slots.find((s) => s.name === slot.getAttribute('cmpSlot'))?.label ?? 'Contenu';
    };

    class InsertComponentCommand extends Command {
        refresh() {
            const position = this.editor.model.document.selection.getFirstPosition();
            // Pas de composant dans un titre ni dans une cellule de tableau ; tous ailleurs.
            this.isEnabled = !position?.findAncestor(SLOT_INLINE) && !position?.findAncestor('tableCell');
        }

        execute({ name, values }) {
            const definition = byName.get(name);
            const model = this.editor.model;
            model.change((writer) => {
                const element = writer.createElement(modelName(definition), { cmpName: name, ...values });
                if (definition.layout) {
                    spansOf(definition, values['data-columns']).forEach(() => appendColumn(writer, element));
                }
                let firstCell = null;
                definition.slots.filter(() => !definition.layout).forEach((slot) => {
                    const zone = writer.createElement(slot.inline ? SLOT_INLINE : SLOT_BLOCK, { cmpSlot: slot.name });
                    if (slot.tables) {
                        // Composant Tableau : tableau de 3 × 3 prêt à remplir, ligne d'en-tête comprise.
                        const table = this.editor.plugins.get(TableUtils).createTable(writer, { rows: 3, columns: 3, headingRows: 1 });
                        writer.append(table, zone);
                        firstCell ??= table.getNodeByPath([0, 0, 0]);
                    } else if (!slot.inline) {
                        writer.appendElement('paragraph', zone);
                    }
                    writer.append(zone, element);
                });
                model.insertObject(element, null, null, { setSelection: 'on' });
                if (firstCell) {
                    writer.setSelection(writer.createPositionAt(firstCell, 0));
                    return;
                }
                if (definition.slots.length) {
                    writer.setSelection(writer.createPositionAt(element.getChild(0), 0));
                    return;
                }
                // Curseur dans le paragraphe suivant (créé au besoin) : le composant suivant ne remplace pas celui-ci.
                let next = element.nextSibling;
                if (!next?.is('element', 'paragraph')) {
                    next = writer.createElement('paragraph');
                    writer.insert(next, element, 'after');
                }
                writer.setSelection(next, 0);
            });
        }
    }

    const appendColumn = (writer, layout) => {
        const column = writer.createElement(COLUMN, { cmpSlot: 'column' });
        writer.appendElement('paragraph', column);
        writer.append(column, layout);
    };

    class UpdateComponentCommand extends Command {
        execute({ element, values }) {
            const definition = byName.get(element.getAttribute('cmpName'));
            this.editor.model.change((writer) => {
                fieldAttributes(definition).forEach((attribute) => {
                    if (values[attribute]) writer.setAttribute(attribute, values[attribute], element);
                    else writer.removeAttribute(attribute, element);
                });
                if (definition.layout) fitColumns(writer, element, spansOf(definition, values['data-columns']).length);
            });
            // Largeurs et libellés des colonnes redessinés (la vue d'une colonne dépend de la disposition du bloc).
            if (definition.layout) this.editor.editing.reconvertItem(element);
        }
    }

    // Nombre de colonnes ajusté à la disposition : colonnes vides ajoutées, ou contenu des colonnes retirées regroupé
    // à la fin de la dernière colonne gardée (rien n'est perdu).
    const fitColumns = (writer, layout, count) => {
        while (layout.childCount < count) appendColumn(writer, layout);
        const last = layout.getChild(count - 1);
        while (layout.childCount > count) {
            const extra = layout.getChild(count);
            writer.move(writer.createRangeIn(extra), writer.createPositionAt(last, 'end'));
            writer.remove(extra);
        }
    };

    /*
     * Largeur exigée par le contenu d'une colonne (réglage width des composants, grille de 12) : un composant trop
     * large (ex. 4 cartes à la suite = 12 colonnes) ne tient pas dans une colonne plus étroite.
     */
    const rowWidth = (width, count, horizontal) => Math.min(count, horizontal ? 2 : 4) * width.min * (horizontal ? 2 : 1);
    const isHorizontal = (element) => String(element.getAttribute('data-orientation') ?? '').startsWith('horizontal');
    const valueOf = (element, definition, name) => element.getAttribute(`data-${name}`) ?? definition.fields.find((field) => field.name === name)?.default;
    const requiredWidth = (container) => {
        let need = 0;
        let run = null;
        const close = () => {
            if (run) need = Math.max(need, rowWidth(run.width, run.count, run.horizontal));
            run = null;
        };
        [...container.getChildren()].forEach((child) => {
            const definition = child.is('element') ? byName.get(child.getAttribute('cmpName')) : null;
            const width = definition?.width;
            if (!width?.grouped || run?.group !== definition.group) close();
            if (!definition) return;
            if (width?.grouped) {
                run ??= { group: definition.group, width, count: 0, horizontal: false };
                run.count += 1;
                run.horizontal ||= isHorizontal(child);
            } else if (width?.perMedia) {
                const shown = !width.when || width.when.values.includes(valueOf(child, definition, width.when.field));
                const count = String(valueOf(child, definition, width.perMedia) ?? '').split(',').filter(Boolean).length;
                if (shown && count) need = Math.max(need, rowWidth(width, count, isHorizontal(child)));
            } else if (width) {
                need = Math.max(need, width.min);
            }
            // Composants placés dans les zones d'un composant (ex. cartes dans un accordéon) : même largeur disponible.
            [...child.getChildren()].filter((zone) => zone.is('element', SLOT_BLOCK))
                .forEach((zone) => { need = Math.max(need, requiredWidth(zone)); });
        });
        close();

        return need;
    };

    return class Components extends Plugin {
        static get requires() {
            return [Widget, WidgetToolbarRepository];
        }

        static get pluginName() {
            return 'DsfrComponents';
        }

        init() {
            const editor = this.editor;
            this.defineSchema();
            this.defineConverters();
            editor.commands.add('insertComponent', new InsertComponentCommand(editor));
            editor.commands.add('updateComponent', new UpdateComponentCommand(editor));
            this.defineUi();
        }

        /** Composant sélectionné (widget) ou contenant le curseur. */
        selectedComponent() {
            const selection = this.editor.model.document.selection;
            const selected = selection.getSelectedElement();
            if (COMPONENTS.some((name) => selected?.is('element', name))) return selected;

            return selection.getFirstPosition()?.getAncestors({ parentFirst: true })
                .find((node) => node.is('element', BOX) || node.is('element', LAYOUT)) ?? null;
        }

        /**
         * Colonnes trop étroites pour leur contenu.
         *
         * @returns {{column: number, need: number, span: number}[]}
         */
        widthViolations() {
            const model = this.editor.model;
            return [...model.createRangeIn(model.document.getRoot()).getItems()]
                .filter((item) => item.is('element', LAYOUT))
                .flatMap((layout) => {
                    const spans = spansOf(byName.get(layout.getAttribute('cmpName')), layout.getAttribute('data-columns'));
                    return [...layout.getChildren()].map((column, index) => ({ column: index + 1, span: spans[index] ?? GRID, need: requiredWidth(column) }))
                        .filter(({ need, span }) => need > span);
                });
        }

        defineSchema() {
            const schema = this.editor.model.schema;
            schema.register(ATOM, { inheritAllFrom: '$blockObject', allowAttributes: ['cmpName', ...attributes] });
            schema.register(BOX, { inheritAllFrom: '$blockObject', allowAttributes: ['cmpName', ...attributes] });
            schema.register(SLOT_INLINE, { isLimit: true, allowIn: BOX, allowContentOf: '$block', allowAttributes: ['cmpSlot'] });
            schema.register(SLOT_BLOCK, { isLimit: true, allowIn: BOX, allowContentOf: '$root', allowAttributes: ['cmpSlot'] });
            schema.register(LAYOUT, { inheritAllFrom: '$blockObject', allowAttributes: ['cmpName', ...attributes] });
            schema.register(COLUMN, { isLimit: true, allowIn: LAYOUT, allowContentOf: '$root', allowAttributes: ['cmpSlot'] });
            schema.addChildCheck((context, child) => {
                const names = [...context.getNames()];
                // Aucun composant dans une cellule de tableau.
                if (COMPONENTS.includes(child.name) && names.includes('tableCell')) return false;
                // Tableau : seulement dans la zone « tables » d'un composant (Tableau).
                if ('table' === child.name) {
                    if (names.includes('tableCell')) return false;
                    const items = [...context];
                    const index = names.lastIndexOf(SLOT_BLOCK);
                    if (index < 1 || !tableSlots.has(`${items[index - 1].getAttribute('cmpName')}/${items[index].getAttribute('cmpSlot')}`)) return false;
                }
            });
        }

        defineConverters() {
            const conversion = this.editor.conversion;

            // HTML enregistré → modèle
            conversion.for('upcast').elementToElement({
                view: { name: 'div', attributes: { 'data-component': true } },
                model: (view, { writer }) => {
                    const definition = byName.get(view.getAttribute('data-component'));
                    if (!definition) return null;
                    // Ancien composant « Téléchargement » à un seul document : data-media-id repris dans data-media-ids.
                    const read = ({ attribute, type }) => view.getAttribute(attribute) ?? ('medias' === type ? view.getAttribute('data-media-id') : undefined);
                    const values = Object.fromEntries([
                        ...definition.fields.map((field) => [field.attribute, read(field)]),
                        ...definition.fields.filter((field) => field.settingsAttribute).map((field) => [field.settingsAttribute, view.getAttribute(field.settingsAttribute)]),
                    ].filter(([, value]) => value));

                    return writer.createElement(modelName(definition), { cmpName: definition.name, ...values });
                },
                converterPriority: 'high',
            });
            conversion.for('upcast').elementToElement({
                view: { name: 'div', attributes: { 'data-slot': true } },
                model: (view, { writer }) => {
                    const name = view.getAttribute('data-slot');
                    if (isLayoutSlot(view)) return writer.createElement(COLUMN, { cmpSlot: name });
                    return writer.createElement(inlineSlots.has(name) ? SLOT_INLINE : SLOT_BLOCK, { cmpSlot: name });
                },
                converterPriority: 'high',
            });

            // Modèle → HTML enregistré
            // Composant atomique : élément vide (pas d'espace insécable ajouté par l'éditeur).
            conversion.for('dataDowncast').elementToElement({
                model: ATOM,
                view: (element, { writer }) => writer.createEmptyElement('div', dataAttributes(element)),
            });
            [BOX, LAYOUT].forEach((name) => conversion.for('dataDowncast').elementToElement({
                model: name,
                view: (element, { writer }) => writer.createContainerElement('div', dataAttributes(element)),
            }));
            [SLOT_INLINE, SLOT_BLOCK, COLUMN].forEach((name) => conversion.for('dataDowncast').elementToElement({
                model: name,
                view: (element, { writer }) => writer.createContainerElement('div', { 'data-slot': element.getAttribute('cmpSlot') }),
            }));

            // Modèle → éditeur : aperçu non modifiable (atomique) ou cadre avec ses zones éditables
            conversion.for('editingDowncast').elementToElement({
                model: { name: ATOM, attributes },
                view: (element, { writer }) => {
                    const definition = byName.get(element.getAttribute('cmpName'));
                    const preview = writer.createRawElement('div', { class: 'cmp__preview' }, (dom) => ui.renderPreview(dom, definition, valuesOf(element)));
                    const container = writer.createContainerElement('div', { class: `cmp cmp--atom cmp--${definition.name}` }, [preview]);
                    writer.setCustomProperty('cmp', true, container);

                    // Poignée : glisser-déposer natif de CKEditor (vers une autre colonne, un autre endroit du texte).
                    return toWidget(container, writer, { label: definition.label, hasSelectionHandle: true });
                },
            });
            conversion.for('editingDowncast').elementToStructure({
                model: { name: BOX, attributes },
                view: (element, { writer }) => {
                    const definition = byName.get(element.getAttribute('cmpName'));
                    const header = writer.createRawElement('div', { class: 'cmp__header' }, (dom) => ui.renderPreview(dom, definition, valuesOf(element)));
                    const container = writer.createContainerElement('div', { class: `cmp cmp--box cmp--${definition.name}` }, [header, writer.createSlot()]);
                    writer.setCustomProperty('cmp', true, container);

                    return toWidget(container, writer, { label: definition.label, hasSelectionHandle: true });
                },
            });
            // Bloc « Colonnes » : colonnes côte à côte dans l'éditeur, proportionnées à la disposition.
            conversion.for('editingDowncast').elementToStructure({
                model: { name: LAYOUT, attributes },
                view: (element, { writer }) => {
                    const definition = byName.get(element.getAttribute('cmpName'));
                    const spans = spansOf(definition, element.getAttribute('data-columns'));
                    const header = writer.createRawElement('div', { class: 'cmp__header' }, (dom) => ui.renderPreview(dom, definition, valuesOf(element)));
                    const grid = writer.createContainerElement('div', { class: 'cmp__columns', style: `grid-template-columns: ${spans.map((span) => `${span}fr`).join(' ')}` }, [writer.createSlot()]);
                    const container = writer.createContainerElement('div', { class: `cmp cmp--box cmp--layout cmp--${definition.name}` }, [header, grid]);
                    writer.setCustomProperty('cmp', true, container);

                    return toWidget(container, writer, { label: definition.label, hasSelectionHandle: true });
                },
            });
            conversion.for('editingDowncast').elementToElement({
                model: COLUMN,
                view: (element, { writer }) => {
                    const layout = element.parent;
                    const spans = spansOf(byName.get(layout.getAttribute('cmpName')), layout.getAttribute('data-columns'));
                    const label = `Colonne ${element.index + 1} (${spans[element.index] ?? GRID} sur ${GRID})`;
                    const zone = writer.createEditableElement('div', { class: 'cmp__slot cmp__slot--block cmp__column', 'data-label': label });

                    return toWidgetEditable(zone, writer, { label });
                },
            });
            [[SLOT_INLINE, 'inline'], [SLOT_BLOCK, 'block']].forEach(([name, kind]) => conversion.for('editingDowncast').elementToElement({
                model: name,
                view: (element, { writer }) => {
                    const label = slotLabel(element);
                    const zone = writer.createEditableElement('div', { class: `cmp__slot cmp__slot--${kind}`, 'data-label': label });

                    return toWidgetEditable(zone, writer, { label });
                },
            }));
        }

        defineUi() {
            const editor = this.editor;

            editor.ui.componentFactory.add('insertComponent', (locale) => {
                const button = new ButtonView(locale);
                button.set({ label: 'Insérer un composant', withText: true, tooltip: 'Image, téléchargement, carte, bouton, accordéon…' });
                button.bind('isEnabled').to(editor.commands.get('insertComponent'));
                button.on('execute', () => ui.open({ mode: 'insert' }));

                return button;
            });

            editor.ui.componentFactory.add('editComponent', (locale) => {
                const button = new ButtonView(locale);
                button.set({ label: 'Modifier les réglages', withText: true });
                const refresh = () => {
                    const element = this.selectedComponent();
                    button.isEnabled = Boolean(element && byName.get(element.getAttribute('cmpName'))?.fields.length);
                };
                editor.model.document.selection.on('change:range', refresh);
                refresh();
                button.on('execute', () => {
                    const element = this.selectedComponent();
                    if (element) ui.open({ mode: 'edit', element, name: element.getAttribute('cmpName'), values: valuesOf(element) });
                });

                return button;
            });

            editor.ui.componentFactory.add('removeComponent', (locale) => {
                const button = new ButtonView(locale);
                button.set({ label: 'Supprimer le composant', withText: true });
                button.on('execute', () => {
                    const element = this.selectedComponent();
                    if (element) editor.model.change((writer) => writer.remove(element));
                    editor.editing.view.focus();
                });

                return button;
            });

            // Barre du composant sélectionné (ou contenant le curseur)
            editor.plugins.get(WidgetToolbarRepository).register('dsfrComponent', {
                ariaLabel: 'Composant',
                items: ['editComponent', 'removeComponent'],
                getRelatedElement: (selection) => {
                    const selected = selection.getSelectedElement();
                    if (selected?.getCustomProperty('cmp')) return selected;
                    let node = selection.getFirstPosition()?.parent;
                    while (node && !(node.is('element') && node.getCustomProperty('cmp'))) node = node.parent;

                    return node ?? null;
                },
            });
        }

        /** Réaffiche les aperçus (ex. titres des médias chargés après l'ouverture de l'éditeur). */
        refreshPreviews() {
            const editor = this.editor;
            const items = [...editor.model.createRangeIn(editor.model.document.getRoot()).getItems()]
                .filter((item) => COMPONENTS.some((name) => item.is('element', name)));
            items.forEach((item) => editor.editing.reconvertItem(item));
        }
    };
}
