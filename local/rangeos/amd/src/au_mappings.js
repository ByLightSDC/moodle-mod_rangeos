/**
 * AU mapping CRUD functionality for local_rangeos.
 *
 * @module     local_rangeos/au_mappings
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString, get_strings as getStrings} from 'core/str';

let envId = 0;
let versionId = 0;

/**
 * The strings the mapping dialog renders, resolved once before it is built.
 *
 * Only the ones with no {$a} live here; a string that takes a parameter is fetched at
 * the point of use, where the value is known.
 */
const STRINGS = {};

/** @type {string[]} */
const STRING_KEYS = [
    'mappingname', 'auidlabel', 'defaultscenario_fromconfig', 'scenarios',
    'searchscenarios_label', 'searchscenarios_placeholder', 'searching',
    'noscenariosfound', 'scenariosearchfailed', 'noscenariosselected', 'selectascenario',
    'classmodeupdated',
];

/** Core strings the dialog shares with the rest of Moodle. */
const CORE_STRING_KEYS = ['cancel', 'save', 'search', 'previous', 'next'];

/**
 * Resolve the strings above into STRINGS.
 *
 * @returns {Promise<void>}
 */
const loadStrings = async() => {
    const requests = [
        ...STRING_KEYS.map(key => ({key, component: 'local_rangeos'})),
        ...CORE_STRING_KEYS.map(key => ({key, component: 'core'})),
    ];
    const values = await getStrings(requests);
    requests.forEach((request, i) => {
        STRINGS[request.key] = values[i];
    });
};

/**
 * Initialize the AU mappings page.
 */
export const init = async() => {
    await loadStrings();

    const envSelect = document.getElementById('rangeos-env-select');
    if (envSelect) {
        envId = parseInt(envSelect.value, 10);
        envSelect.addEventListener('change', (e) => {
            envId = parseInt(e.target.value, 10);
            const baseUrl = document.querySelector('[data-baseurl]')?.dataset.baseurl;
            if (baseUrl) {
                const sep = baseUrl.includes('?') ? '&' : '?';
                window.location.href = baseUrl + sep + 'envid=' + envId;
            }
        });
    }

    const container = document.querySelector('[data-versionid]');
    if (container) {
        versionId = parseInt(container.dataset.versionid, 10) || 0;
    }

    // Class mode toggle handlers.
    document.addEventListener('change', (e) => {
        const toggle = e.target.closest('[data-action="toggle-classmode"]');
        if (toggle) {
            patchAuConfig(toggle.dataset.auid, toggle.checked, '');
        }
    });

    // Default class ID blur handler (save on focus loss).
    document.addEventListener('focusout', (e) => {
        const input = e.target.closest('[data-action="defaultclassid-input"]');
        if (input) {
            const auId = input.dataset.auid;
            const toggle = document.querySelector(`[data-action="toggle-classmode"][data-auid="${auId}"]`);
            const classMode = toggle ? toggle.checked : false;
            patchAuConfig(auId, classMode, input.value.trim());
        }
    });

    // Row expanders. The detail lives in its own <tr>, which the row's toggle
    // names through aria-controls, so the two stay tied together without the
    // markup having to encode an AU IRI into a selector.
    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-action="toggle-au-detail"]');
        if (!toggle) {
            return;
        }
        const detail = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!detail) {
            return;
        }
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        detail.hidden = open;
    });

    // Delegate click handlers for mapping actions.
    document.addEventListener('click', (e) => {
        const createBtn = e.target.closest('[data-action="create-mapping"]');
        if (createBtn) {
            e.preventDefault();
            showMappingForm(
                createBtn.dataset.auid || '',
                createBtn.dataset.autitle || '',
                createBtn.dataset.scenarios || '[]',
                false
            );
        }

        const editBtn = e.target.closest('[data-action="edit-mapping"]');
        if (editBtn) {
            e.preventDefault();
            showMappingForm(
                editBtn.dataset.auid || '',
                editBtn.dataset.autitle || '',
                editBtn.dataset.scenarios || '[]',
                true
            );
        }

        const defaultBtn = e.target.closest('[data-action="create-default-mapping"]');
        if (defaultBtn) {
            e.preventDefault();
            createDefaultMapping(
                defaultBtn.dataset.auid || '',
                defaultBtn.dataset.autitle || '',
                defaultBtn.dataset.defaultscenarioname || ''
            );
        }

    });
};

/**
 * Look up a scenario by its exact name and open the mapping form pre-filled with its UUID.
 * Shows an inline warning if the scenario is not found in the current environment.
 *
 * @param {string} auId AU IRI.
 * @param {string} auTitle AU title/name for display.
 * @param {string} defaultScenarioName Scenario name from the course RC5.yaml config.
 */
const createDefaultMapping = async(auId, auTitle, defaultScenarioName) => {
    const btn = document.querySelector(
        `[data-action="create-default-mapping"][data-auid="${CSS.escape(auId)}"]`
    );
    const originalHtml = btn ? btn.innerHTML : '';

    if (btn) {
        btn.disabled = true;
        btn.textContent = STRINGS.searching;
    }

    try {
        const result = await Ajax.call([{
            methodname: 'local_rangeos_list_scenarios',
            args: {
                envid: envId,
                search: defaultScenarioName,
                page: 0,
                pagesize: 100,
            },
        }])[0];

        const scenario = (result.scenarios || []).find(s => s.name === defaultScenarioName);

        if (scenario) {
            const selected = JSON.stringify([{id: scenario.id, name: scenario.name}]);
            showMappingForm(auId, auTitle, selected, false, defaultScenarioName);
        } else {
            Notification.addNotification({
                message: await getString(
                    'defaultscenario_notfound',
                    'local_rangeos',
                    escapeHtml(defaultScenarioName)
                ),
                type: 'warning',
            });
        }
    } catch (err) {
        Notification.exception(err);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
};

/**
 * Show the mapping create/edit modal form.
 *
 * @param {string} auId AU IRI.
 * @param {string} auTitle AU title/name for display.
 * @param {string} existingScenarios JSON string of current scenarios.
 * @param {boolean} isEdit Whether this is an edit operation.
 * @param {string} defaultScenarioName Optional scenario name shown as a hint when pre-filled.
 */
const showMappingForm = async(auId, auTitle, existingScenarios, isEdit, defaultScenarioName = '') => {
    const title = isEdit
        ? await getString('editmapping', 'local_rangeos')
        : await getString('createmapping', 'local_rangeos');

    const defaultHint = defaultScenarioName
        ? `<p class="rangeos-modal-hint">${escapeHtml(STRINGS.defaultscenario_fromconfig)}
               <strong>${escapeHtml(defaultScenarioName)}</strong></p>`
        : '';

    const selectedScenarios = parseSelectedScenarios(existingScenarios);

    // Create a container div for the form.
    const container = document.createElement('div');
    container.className = 'rangeos-modal-form';
    container.innerHTML = `
        <div class="rangeos-modal-field">
            <label for="mapping-name">${escapeHtml(STRINGS.mappingname)}</label>
            <input type="text" class="form-control" id="mapping-name"
                   value="${escapeAttr(auTitle)}">
            <p class="rangeos-modal-auid">
                <span class="rangeos-modal-auid-label">${escapeHtml(STRINGS.auidlabel)}</span>
                <code>${escapeHtml(auId)}</code>
            </p>
        </div>
        <div class="rangeos-modal-field rangeos-modal-field-grow">
            ${defaultHint}
            <div class="rangeos-modal-selectedrow">
                <span class="rangeos-modal-label">${escapeHtml(STRINGS.scenarios)}</span>
                <div id="mapping-selected-scenarios" class="rangeos-modal-selected"></div>
            </div>
            <div class="rangeos-modal-search">
                <label class="sr-only visually-hidden"
                       for="mapping-scenario-search">${escapeHtml(STRINGS.searchscenarios_label)}</label>
                <input type="search" class="form-control" id="mapping-scenario-search"
                       placeholder="${escapeAttr(STRINGS.searchscenarios_placeholder)}" autocomplete="off">
                <button type="button" class="btn btn-outline-secondary" id="mapping-scenario-search-btn">
                    ${escapeHtml(STRINGS.search)}
                </button>
            </div>
            <div id="mapping-scenario-results" class="list-group rangeos-scenario-results"></div>
            <div id="mapping-scenario-pagination" class="rangeos-scenario-paging"></div>
        </div>
    `;

    // Use a simple Bootstrap modal since ModalFactory can be finicky with dynamic content.
    const modalId = 'rangeos-mapping-modal-' + Date.now();
    const modalHtml = `
        <div class="modal fade rangeos-modal mod-cmi5-library" id="${modalId}" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${escapeHtml(title)}</h5>
                    </div>
                    <div class="modal-body" id="${modalId}-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                                data-dismiss="modal" id="${modalId}-cancel">${escapeHtml(STRINGS.cancel)}</button>
                        <button type="button" class="btn btn-primary" id="${modalId}-save">${escapeHtml(STRINGS.save)}</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Append modal to document.
    const wrapper = document.createElement('div');
    wrapper.innerHTML = modalHtml;
    document.body.appendChild(wrapper);

    const modalEl = document.getElementById(modalId);
    const modalBody = document.getElementById(modalId + '-body');
    modalBody.appendChild(container);

    // Show modal using jQuery (Moodle ships Bootstrap 4 with jQuery).
    $(modalEl).modal('show');
    initScenarioPicker(modalEl, selectedScenarios);

    // Cancel handler (data-dismiss alone isn't reliably wired up on every Moodle page).
    document.getElementById(modalId + '-cancel').addEventListener('click', () => {
        $(modalEl).modal('hide');
    });

    // Save handler.
    document.getElementById(modalId + '-save').addEventListener('click', () => {
        const mappingName = modalEl.querySelector('#mapping-name').value.trim();

        if (selectedScenarios.length === 0) {
            Notification.addNotification({message: STRINGS.selectascenario, type: 'error'});
            return;
        }

        const wsFunction = isEdit
            ? 'local_rangeos_update_au_mapping'
            : 'local_rangeos_create_au_mapping';

        Ajax.call([{
            methodname: wsFunction,
            args: {
                envid: envId,
                auid: auId,
                name: mappingName,
                scenarios_json: JSON.stringify(selectedScenarios.map(scenario => scenario.id)),
            },
        }])[0].then(() => {
            $(modalEl).modal('hide');
            window.location.reload();
        }).catch(Notification.exception);
    });

    // Cleanup on close.
    $(modalEl).on('hidden.bs.modal', () => {
        wrapper.remove();
    });
};

/**
 * Read the scenarios already mapped to this AU.
 *
 * The page supplies {id, name} objects, while the mapping API stores bare UUIDs, so both
 * shapes are accepted and anything without a UUID is dropped.
 *
 * @param {string} scenariosJson JSON array of scenario UUIDs or objects.
 * @returns {Array} Selected scenarios as {id, name} objects.
 */
const parseSelectedScenarios = (scenariosJson) => {
    let values;
    try {
        values = JSON.parse(scenariosJson || '[]');
    } catch {
        return [];
    }
    if (!Array.isArray(values)) {
        return [];
    }

    return values.map((scenario) => {
        if (typeof scenario === 'string') {
            return {id: scenario, name: scenario};
        }
        const id = scenario?.uuid || scenario?.scenarioId || scenario?.id || '';
        return {id, name: scenario?.name || id};
    }).filter(scenario => scenario.id);
};

/**
 * Wire up the scenario picker and load its first page of results.
 *
 * @param {HTMLElement} modalEl Mapping modal.
 * @param {Array} selectedScenarios Mutable selection, updated as the user picks scenarios.
 */
const initScenarioPicker = (modalEl, selectedScenarios) => {
    const searchInput = modalEl.querySelector('#mapping-scenario-search');
    const searchState = {query: '', page: 0, pagesize: 10, requestId: 0};
    const search = () => {
        searchState.query = searchInput.value.trim();
        searchState.page = 0;
        searchScenarios(modalEl, searchState, selectedScenarios);
    };

    modalEl.querySelector('#mapping-scenario-search-btn').addEventListener('click', search);
    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            search();
        }
    });

    renderSelectedScenarios(modalEl, selectedScenarios);
    searchScenarios(modalEl, searchState, selectedScenarios);
};

/**
 * Load the current page of search results and render them as selectable rows.
 *
 * @param {HTMLElement} modalEl Mapping modal.
 * @param {Object} searchState Current search term, page, page size, and request counter.
 * @param {Array} selectedScenarios Mutable selection.
 */
const searchScenarios = (modalEl, searchState, selectedScenarios) => {
    const results = modalEl.querySelector('#mapping-scenario-results');
    const pagination = modalEl.querySelector('#mapping-scenario-pagination');
    const requestId = ++searchState.requestId;
    results.innerHTML = `<div class="p-2 text-muted">${escapeHtml(STRINGS.searching)}</div>`;
    pagination.innerHTML = '';

    Ajax.call([{
        methodname: 'local_rangeos_list_scenarios',
        args: {
            envid: envId,
            search: searchState.query,
            page: searchState.page,
            pagesize: searchState.pagesize,
        },
    }])[0].then((response) => {
        // Ignore a response another search or page change has already superseded.
        if (requestId !== searchState.requestId) {
            return;
        }

        const scenarios = response.scenarios || [];
        if (scenarios.length === 0) {
            results.innerHTML = `<div class="p-2 text-muted">${escapeHtml(STRINGS.noscenariosfound)}</div>`;
            return;
        }

        return Promise.all(
            scenarios.map(scenario => buildScenarioResult(scenario, modalEl, selectedScenarios))
        ).then((rows) => {
            // Another search may have superseded this one while the labels resolved.
            if (requestId !== searchState.requestId) {
                return null;
            }
            results.innerHTML = '';
            rows.forEach(row => results.appendChild(row));
            // Result names are the freshest ones available, so redraw the badges with them.
            renderSelectedScenarios(modalEl, selectedScenarios);
            return renderScenarioPagination(modalEl, searchState, response.total || 0, selectedScenarios);
        });
    }).catch((error) => {
        if (requestId === searchState.requestId) {
            results.innerHTML = `<div class="p-2 text-danger">${escapeHtml(STRINGS.scenariosearchfailed)}</div>`;
        }
        Notification.exception(error);
    });
};

/**
 * Build one selectable search result.
 *
 * @param {Object} scenario Scenario returned by the web service.
 * @param {HTMLElement} modalEl Mapping modal.
 * @param {Array} selectedScenarios Mutable selection.
 * @returns {HTMLElement} Result row.
 */
const buildScenarioResult = async(scenario, modalEl, selectedScenarios) => {
    const selected = selectedScenarios.find(item => item.id === scenario.id);
    if (selected) {
        // Mappings store UUIDs only, so take the display name from the catalog.
        selected.name = scenario.name;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'list-group-item list-group-item-action';
    button.dataset.scenarioId = scenario.id;
    button.appendChild(createResultLine('strong', scenario.name));

    if (scenario.description) {
        button.appendChild(createResultLine('small', scenario.description, 'd-block'));
    }

    const details = (await Promise.all([
        scenario.author
            ? getString('scenarioauthor', 'local_rangeos', scenario.author)
            : '',
        scenario.createdat
            ? getString('scenariocreated', 'local_rangeos', formatScenarioDate(scenario.createdat))
            : '',
        scenario.updatedat
            ? getString('scenarioupdated', 'local_rangeos', formatScenarioDate(scenario.updatedat))
            : '',
    ])).filter(Boolean);
    if (details.length > 0) {
        button.appendChild(createResultLine('small', details.join(' · '), 'd-block mt-1'));
    }

    setResultSelected(button, Boolean(selected));
    button.addEventListener('click', () => {
        toggleScenario(selectedScenarios, scenario);
        setResultSelected(button, selectedScenarios.some(item => item.id === scenario.id));
        renderSelectedScenarios(modalEl, selectedScenarios);
    });

    return button;
};

/**
 * Create one line of text inside a search result.
 *
 * @param {string} tag Element tag name.
 * @param {string} text Line text.
 * @param {string} classes Extra classes for the line.
 * @returns {HTMLElement} The line element.
 */
const createResultLine = (tag, text, classes = '') => {
    const line = document.createElement(tag);
    line.className = classes;
    line.textContent = text;
    return line;
};

/**
 * Mark a result as selected, keeping its secondary lines legible on the active background.
 *
 * @param {HTMLElement} button Result row.
 * @param {boolean} isSelected Whether the scenario is in the selection.
 */
const setResultSelected = (button, isSelected) => {
    button.classList.toggle('active', isSelected);
    button.querySelectorAll('small').forEach((line) => {
        line.classList.toggle('text-muted', !isSelected);
    });
};

/**
 * Add a scenario to the selection, or remove it when it is already selected.
 *
 * @param {Array} selectedScenarios Mutable selection.
 * @param {Object} scenario Scenario to toggle.
 */
const toggleScenario = (selectedScenarios, scenario) => {
    if (!removeScenario(selectedScenarios, scenario.id)) {
        selectedScenarios.push({id: scenario.id, name: scenario.name});
    }
};

/**
 * Remove a scenario from the selection.
 *
 * @param {Array} selectedScenarios Mutable selection.
 * @param {string} scenarioId Scenario UUID.
 * @returns {boolean} Whether the scenario was selected.
 */
const removeScenario = (selectedScenarios, scenarioId) => {
    const index = selectedScenarios.findIndex(item => item.id === scenarioId);
    if (index >= 0) {
        selectedScenarios.splice(index, 1);
    }
    return index >= 0;
};

/**
 * Render paging controls for the scenario search results.
 *
 * @param {HTMLElement} modalEl Mapping modal.
 * @param {Object} searchState Current search term, page, page size, and request counter.
 * @param {number} total Total matching scenarios.
 * @param {Array} selectedScenarios Mutable selection.
 */
const renderScenarioPagination = async(modalEl, searchState, total, selectedScenarios) => {
    const container = modalEl.querySelector('#mapping-scenario-pagination');
    container.innerHTML = '';
    if (total === 0) {
        return;
    }

    const pageCount = Math.ceil(total / searchState.pagesize);
    const goToPage = (page) => {
        searchState.page = page;
        searchScenarios(modalEl, searchState, selectedScenarios);
    };

    const summary = document.createElement('span');
    summary.className = 'rangeos-page-summary';
    summary.textContent = await getString('scenariopage', 'local_rangeos', {
        page: searchState.page + 1,
        pages: pageCount,
        total,
    });

    container.appendChild(
        createPageButton(STRINGS.previous, searchState.page === 0, () => goToPage(searchState.page - 1))
    );
    container.appendChild(summary);
    container.appendChild(
        createPageButton(STRINGS.next, searchState.page >= pageCount - 1, () => goToPage(searchState.page + 1))
    );
};

/**
 * Create one paging button.
 *
 * @param {string} label Button label.
 * @param {boolean} disabled Whether the button is unavailable.
 * @param {Function} onClick Click handler.
 * @returns {HTMLElement} The button.
 */
const createPageButton = (label, disabled, onClick) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-secondary rangeos-page-btn';
    button.textContent = label;
    button.disabled = disabled;
    button.addEventListener('click', onClick);
    return button;
};

/**
 * Format an API date for the user's locale, keeping unrecognized values as-is.
 *
 * @param {string} value API date value, as seconds, milliseconds or a date string.
 * @returns {string} Display date.
 */
const formatScenarioDate = (value) => {
    const seconds = /^\d+$/.test(value) ? Number(value) : null;
    // Anything beyond the year 2286 in seconds is already milliseconds.
    const timestamp = seconds === null ? value : (seconds > 9999999999 ? seconds : seconds * 1000);
    const date = new Date(timestamp);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

/**
 * Render the scenarios currently selected for the mapping.
 *
 * @param {HTMLElement} modalEl Mapping modal.
 * @param {Array} selectedScenarios Mutable selection.
 */
const renderSelectedScenarios = (modalEl, selectedScenarios) => {
    const selectedContainer = modalEl.querySelector('#mapping-selected-scenarios');
    selectedContainer.innerHTML = '';
    if (selectedScenarios.length === 0) {
        selectedContainer.innerHTML =
            `<span class="rangeos-modal-empty">${escapeHtml(STRINGS.noscenariosselected)}</span>`;
        return;
    }

    const chips = document.createElement('div');
    chips.className = 'rangeos-chip-list';
    selectedContainer.appendChild(chips);

    selectedScenarios.forEach((scenario) => {
        const badge = document.createElement('span');
        badge.className = 'rangeos-chip';
        badge.appendChild(document.createTextNode(scenario.name));

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'rangeos-chip-remove';
        getString('removescenario', 'local_rangeos', scenario.name).then((label) => {
            remove.setAttribute('aria-label', label);
            return null;
        }).catch(() => {
            // An unlabelled control is better than a broken dialog; the glyph still works.
        });
        remove.innerHTML = '<span aria-hidden="true">&times;</span>';
        remove.addEventListener('click', () => {
            removeScenario(selectedScenarios, scenario.id);
            renderSelectedScenarios(modalEl, selectedScenarios);
            const result = modalEl.querySelector(
                `#mapping-scenario-results button[data-scenario-id="${CSS.escape(scenario.id)}"]`
            );
            if (result) {
                setResultSelected(result, false);
            }
        });

        badge.appendChild(remove);
        chips.appendChild(badge);
    });
};

/**
 * Patch an AU's config.json via AJAX to toggle class mode.
 *
 * @param {string} auId AU IRI.
 * @param {boolean} classMode Whether class mode is enabled.
 * @param {string} defaultClassId Default class ID string.
 */
const patchAuConfig = (auId, classMode, defaultClassId) => {
    if (!versionId) {
        return;
    }

    Ajax.call([{
        methodname: 'local_rangeos_patch_au_config',
        args: {
            versionid: versionId,
            auid: auId,
            classmode: classMode,
            defaultclassid: defaultClassId,
        },
    }])[0].then(() => {
        Notification.addNotification({
            message: STRINGS.classmodeupdated,
            type: 'success',
        });
    }).catch(Notification.exception);
};

/**
 * Escape HTML entities for display.
 *
 * @param {string} str Input string.
 * @returns {string} Escaped string.
 */
const escapeHtml = (str) => {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
};

/**
 * Escape for use in HTML attribute values.
 *
 * @param {string} str Input string.
 * @returns {string} Escaped string.
 */
const escapeAttr = (str) => {
    return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
};
