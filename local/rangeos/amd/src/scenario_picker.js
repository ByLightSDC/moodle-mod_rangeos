/**
 * Scenario search/select widget for local_rangeos.
 *
 * @module     local_rangeos/scenario_picker
 * @copyright  2026 David Ropte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Initialize the scenario picker in a container.
 *
 * @param {string} containerSelector CSS selector for the container element.
 * @param {number} envId Environment ID.
 * @param {Function} onSelect Callback when scenarios are selected. Receives array of scenario objects.
 */
export const init = (containerSelector, envId, onSelect) => {
    const container = document.querySelector(containerSelector);
    if (!container) {
        return;
    }

    container.innerHTML = `
        <div class="scenario-picker">
            <div class="input-group mb-2">
                <input type="text" class="form-control" id="scenario-search"
                       placeholder="Search scenarios...">
                <select class="custom-select" id="scenario-class-filter" style="max-width: 200px;">
                    <option value="">All classes</option>
                </select>
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" id="scenario-search-btn" type="button">
                        Search
                    </button>
                </div>
            </div>
            <div id="scenario-results" class="list-group mb-2" style="max-height: 300px; overflow-y: auto;"></div>
            <div id="scenario-selected" class="mb-2"></div>
        </div>
    `;

    const selectedScenarios = [];

    // Load classes.
    loadClasses(envId);

    // Search handler.
    const searchBtn = container.querySelector('#scenario-search-btn');
    const searchInput = container.querySelector('#scenario-search');

    const doSearch = () => {
        const search = searchInput.value.trim();
        const classFilter = container.querySelector('#scenario-class-filter').value;
        searchScenarios(envId, search, classFilter, container, selectedScenarios, onSelect);
    };

    searchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            doSearch();
        }
    });
};

/**
 * Load scenario classes into the filter dropdown.
 *
 * @param {number} envId Environment ID.
 */
const loadClasses = (envId) => {
    Ajax.call([{
        methodname: 'local_rangeos_list_scenario_classes',
        args: {envid: envId},
    }])[0].then((response) => {
        const select = document.getElementById('scenario-class-filter');
        if (!select) {
            return;
        }
        response.classes.forEach((cls) => {
            const option = document.createElement('option');
            option.value = cls.name;
            option.textContent = cls.name;
            select.appendChild(option);
        });
    }).catch(Notification.exception);
};

/**
 * Search scenarios and display results.
 *
 * @param {number} envId Environment ID.
 * @param {string} search Search term.
 * @param {string} classFilter Class filter.
 * @param {HTMLElement} container Container element.
 * @param {Array} selectedScenarios Currently selected scenarios.
 * @param {Function} onSelect Selection callback.
 */
const searchScenarios = (envId, search, classFilter, container, selectedScenarios, onSelect) => {
    const resultsDiv = container.querySelector('#scenario-results');
    resultsDiv.innerHTML = '<div class="p-2 text-muted">Loading...</div>';

    Ajax.call([{
        methodname: 'local_rangeos_list_scenarios',
        args: {
            envid: envId,
            search: search,
            classfilter: classFilter,
            page: 0,
            pagesize: 50,
        },
    }])[0].then((response) => {
        resultsDiv.innerHTML = '';
        if (response.scenarios.length === 0) {
            resultsDiv.innerHTML = '<div class="p-2 text-muted">No scenarios found.</div>';
            return;
        }
        response.scenarios.forEach((scenario) => {
            const isSelected = selectedScenarios.some(s => s.id === scenario.id);
            const item = document.createElement('a');
            item.href = '#';
            item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            if (isSelected) {
                item.classList.add('active');
            }
            item.innerHTML = `
                <div>
                    <strong>${escapeHtml(scenario.name)}</strong>
                    <small class="text-muted ml-2">${escapeHtml(scenario.class)}</small>
                </div>
            `;
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const idx = selectedScenarios.findIndex(s => s.id === scenario.id);
                if (idx >= 0) {
                    selectedScenarios.splice(idx, 1);
                    item.classList.remove('active');
                } else {
                    selectedScenarios.push({id: scenario.id, name: scenario.name});
                    item.classList.add('active');
                }
                updateSelectedDisplay(container, selectedScenarios);
                updateScenariosTextarea(selectedScenarios);
                if (onSelect) {
                    onSelect(selectedScenarios);
                }
            });
            resultsDiv.appendChild(item);
        });
    }).catch(Notification.exception);
};

/**
 * Update the selected scenarios display.
 *
 * @param {HTMLElement} container Container element.
 * @param {Array} selectedScenarios Selected scenarios.
 */
const updateSelectedDisplay = (container, selectedScenarios) => {
    const selectedDiv = container.querySelector('#scenario-selected');
    if (selectedScenarios.length === 0) {
        selectedDiv.innerHTML = '';
        return;
    }
    selectedDiv.innerHTML = '<strong>Selected:</strong> ' +
        selectedScenarios.map(s => `<span class="badge badge-primary mr-1">${escapeHtml(s.name)}</span>`).join('');
};

/**
 * Update the scenarios textarea in the mapping form.
 *
 * @param {Array} selectedScenarios Selected scenarios.
 */
const updateScenariosTextarea = (selectedScenarios) => {
    const textarea = document.getElementById('mapping-scenarios');
    if (textarea) {
        textarea.value = JSON.stringify(selectedScenarios, null, 2);
    }
};

/**
 * Escape HTML entities.
 *
 * @param {string} str Input string.
 * @returns {string} Escaped string.
 */
const escapeHtml = (str) => {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
};
