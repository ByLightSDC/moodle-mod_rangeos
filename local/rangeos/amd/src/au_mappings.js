/**
 * AU mapping CRUD functionality for local_rangeos.
 *
 * @module     local_rangeos/au_mappings
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

let envId = 0;
let versionId = 0;

/**
 * Initialize the AU mappings page.
 */
export const init = () => {
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

        const classBtn = e.target.closest('[data-action="create-class"]');
        if (classBtn) {
            e.preventDefault();
            showCreateClassForm(
                classBtn.dataset.scenariouuid || '',
                classBtn.dataset.autitle || ''
            );
        }
    });
};

/**
 * Show the mapping create/edit modal form.
 *
 * @param {string} auId AU IRI.
 * @param {string} auTitle AU title/name for display.
 * @param {string} existingScenarios JSON string of current scenarios.
 * @param {boolean} isEdit Whether this is an edit operation.
 */
const showMappingForm = async(auId, auTitle, existingScenarios, isEdit) => {
    const title = isEdit
        ? await getString('editmapping', 'local_rangeos')
        : await getString('createmapping', 'local_rangeos');

    // Create a container div for the form.
    const container = document.createElement('div');
    container.innerHTML = `
        <div class="form-group">
            <label for="mapping-auid">AU IRI</label>
            <input type="text" class="form-control" id="mapping-auid"
                   value="${escapeAttr(auId)}" ${auId ? 'readonly' : ''}>
        </div>
        <div class="form-group">
            <label for="mapping-name">Name</label>
            <input type="text" class="form-control" id="mapping-name"
                   value="${escapeAttr(auTitle)}">
        </div>
        <div class="form-group">
            <label for="mapping-scenarios">Scenarios (JSON array of UUIDs)</label>
            <textarea class="form-control" id="mapping-scenarios"
                      rows="4">${escapeHtml(existingScenarios)}</textarea>
            <small class="form-text text-muted">
                Enter scenario UUIDs as a JSON array, e.g. ["uuid1", "uuid2"]
            </small>
        </div>
    `;

    // Use a simple Bootstrap modal since ModalFactory can be finicky with dynamic content.
    const modalId = 'rangeos-mapping-modal-' + Date.now();
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${escapeHtml(title)}</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="${modalId}-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="${modalId}-save">Save</button>
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
    // eslint-disable-next-line no-undef
    $(modalEl).modal('show');

    // Save handler.
    document.getElementById(modalId + '-save').addEventListener('click', () => {
        const mappingAuId = modalEl.querySelector('#mapping-auid').value.trim();
        const mappingName = modalEl.querySelector('#mapping-name').value.trim();
        const scenariosJson = modalEl.querySelector('#mapping-scenarios').value.trim();

        if (!mappingAuId) {
            Notification.addNotification({message: 'AU IRI is required.', type: 'error'});
            return;
        }

        const wsFunction = isEdit
            ? 'local_rangeos_update_au_mapping'
            : 'local_rangeos_create_au_mapping';

        Ajax.call([{
            methodname: wsFunction,
            args: {
                envid: envId,
                auid: mappingAuId,
                name: mappingName,
                scenarios_json: scenariosJson || '[]',
            },
        }])[0].then(() => {
            // eslint-disable-next-line no-undef
            $(modalEl).modal('hide');
            window.location.reload();
        }).catch(Notification.exception);
    });

    // Cleanup on close.
    // eslint-disable-next-line no-undef
    $(modalEl).on('hidden.bs.modal', () => {
        wrapper.remove();
    });
};

/**
 * Show modal form to create a class for a scenario.
 *
 * @param {string} scenarioUuid Content scenario UUID.
 * @param {string} auTitle AU title for display.
 */
const showCreateClassForm = async(scenarioUuid, auTitle) => {
    const title = await getString('createclass', 'local_rangeos');

    const container = document.createElement('div');
    container.innerHTML = `
        <p class="text-muted mb-3">Create a class for: <strong>${escapeHtml(auTitle)}</strong></p>
        <div class="form-group">
            <label for="class-id-input">Class ID</label>
            <input type="text" class="form-control" id="class-id-input"
                   placeholder="e.g. cyber-101-spring-2026">
            <small class="form-text text-muted">
                A unique identifier for this class batch.
            </small>
        </div>
        <div class="form-group">
            <label for="class-count-input">Number of seats</label>
            <input type="number" class="form-control" id="class-count-input"
                   value="10" min="1" max="500">
            <small class="form-text text-muted">
                Number of scenario instances to pre-deploy.
            </small>
        </div>
    `;

    const modalId = 'rangeos-class-modal-' + Date.now();
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${escapeHtml(title)}</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="${modalId}-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="${modalId}-create">Create</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = modalHtml;
    document.body.appendChild(wrapper);

    const modalEl = document.getElementById(modalId);
    const modalBody = document.getElementById(modalId + '-body');
    modalBody.appendChild(container);

    // eslint-disable-next-line no-undef
    $(modalEl).modal('show');

    document.getElementById(modalId + '-create').addEventListener('click', () => {
        const classId = modalEl.querySelector('#class-id-input').value.trim();
        const count = parseInt(modalEl.querySelector('#class-count-input').value, 10);

        if (!classId) {
            Notification.addNotification({message: 'Class ID is required.', type: 'error'});
            return;
        }
        if (!count || count < 1) {
            Notification.addNotification({message: 'Count must be at least 1.', type: 'error'});
            return;
        }

        const createBtn = document.getElementById(modalId + '-create');
        createBtn.disabled = true;
        createBtn.textContent = 'Creating...';

        Ajax.call([{
            methodname: 'local_rangeos_create_class',
            args: {
                envid: envId,
                scenarioid: scenarioUuid,
                classid: classId,
                count: count,
            },
        }])[0].then(() => {
            // eslint-disable-next-line no-undef
            $(modalEl).modal('hide');
            Notification.addNotification({
                message: `Class "${classId}" created with ${count} seats.`,
                type: 'success',
            });
        }).catch((err) => {
            createBtn.disabled = false;
            createBtn.textContent = 'Create';
            Notification.exception(err);
        });
    });

    // eslint-disable-next-line no-undef
    $(modalEl).on('hidden.bs.modal', () => {
        wrapper.remove();
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
            message: 'Class mode updated.',
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
