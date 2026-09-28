/**
 * Scenario classes management functionality.
 *
 * @module     local_rangeos/scenario_classes
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {confirmAction} from 'local_rangeos/confirm';
import {get_string as getString, get_strings as getStrings} from 'core/str';

let envId = 0;

/**
 * The strings this page renders, resolved once before anything is drawn.
 *
 * Only the ones with no {$a} live here; a string that takes a parameter is fetched at
 * the point of use, where the value is known.
 */
const STRINGS = {};

/** @type {Array<{key: string, component: string}>} */
const STRING_REQUESTS = [
    // Seats table.
    'loading', 'seatsloadfailed', 'noinstances', 'unassignedseat', 'unknownuser', 'removeseat',
    // Confirmations and their outcomes.
    'deleteseat_confirmtitle', 'deleteseat_confirm', 'deleteseat', 'seatdeleted',
    'deleteclass_confirmtitle', 'deleteclass_notimplemented',
    // Create class dialog.
    'createclass', 'classid', 'classid_placeholder', 'activityscenario', 'loadingactivities',
    'selectactivityscenario', 'noactivityscenarios', 'activitiesloadfailed', 'numberofseats',
    'enddate', 'enddate_default', 'creating', 'createclass_required',
    // Add seats dialog.
    'addseats', 'additionalseats', 'adding', 'addseats_noscenario', 'addseats_countminimum',
].map(key => ({key, component: 'local_rangeos'}));

/** Core strings the dialogs share with the rest of Moodle. */
const CORE_STRING_REQUESTS = ['cancel', 'create', 'add', 'closebuttontitle', 'delete']
    .map(key => ({key, component: 'core'}));

/**
 * Initialize the scenario classes page.
 *
 * @returns {Promise<void>}
 */
export const init = async() => {
    const requests = [...STRING_REQUESTS, ...CORE_STRING_REQUESTS];
    const values = await getStrings(requests);
    requests.forEach((request, i) => {
        STRINGS[request.key] = values[i];
    });

    const container = document.querySelector('[data-envid]');
    if (container) {
        envId = parseInt(container.dataset.envid, 10) || 0;
    }

    const envSelect = document.getElementById('rangeos-env-select');
    if (envSelect) {
        envId = parseInt(envSelect.value, 10);
        envSelect.addEventListener('change', (e) => {
            envId = parseInt(e.target.value, 10);
            const baseUrl = container?.dataset.baseurl;
            if (baseUrl) {
                const sep = baseUrl.includes('?') ? '&' : '?';
                window.location.href = baseUrl + sep + 'envid=' + envId;
            }
        });
    }

    // Load instance counts for each class row.
    document.querySelectorAll('.instance-count[data-classname]').forEach((el) => {
        loadInstanceCount(el.dataset.classname, el);
    });

    // Event delegation.
    document.addEventListener('click', (e) => {
        const createBtn = e.target.closest('[data-action="create-class"]');
        if (createBtn) {
            e.preventDefault();
            showCreateClassModal();
            return;
        }

        const viewBtn = e.target.closest('[data-action="view-instances"]');
        if (viewBtn) {
            e.preventDefault();
            showClassInstances(viewBtn.dataset.classname, viewBtn.dataset.rangeid || '');
            return;
        }

        const deleteBtn = e.target.closest('[data-action="delete-class"]');
        if (deleteBtn) {
            e.preventDefault();
            deleteClass(deleteBtn.dataset.classname, deleteBtn);
            return;
        }

        const addSeatsBtn = e.target.closest('[data-action="add-seats"]');
        if (addSeatsBtn) {
            e.preventDefault();
            const row = addSeatsBtn.closest('tr[data-classname]');
            const scenarioId = row ? row.dataset.scenarioid || '' : '';
            showAddSeatsModal(addSeatsBtn.dataset.classname, scenarioId);
            return;
        }

        const deleteInstBtn = e.target.closest('[data-action="delete-instance"]');
        if (deleteInstBtn) {
            e.preventDefault();
            deleteScenarioInstance(
                deleteInstBtn.dataset.rangeid || '',
                deleteInstBtn.dataset.scenarioid,
                deleteInstBtn.dataset.classname,
                deleteInstBtn
            );
            return;
        }
    });
};

/**
 * Load instance count for a class.
 *
 * @param {string} className The class name.
 * @param {HTMLElement} el The element to update.
 */
const loadInstanceCount = (className, el) => {
    if (!envId) {
        el.textContent = '-';
        return;
    }

    Ajax.call([{
        methodname: 'local_rangeos_get_class_instances',
        args: {envid: envId, classid: className},
    }])[0].then(async(result) => {
        const assigned = result.instances.filter(inst => inst.assigned).length;
        el.textContent = await getString('seatssummary', 'local_rangeos', {
            assigned,
            total: result.total,
        });
        // Store the scenarioId from the first instance on the class row for add-seats.
        if (result.instances.length > 0 && result.instances[0].scenarioid) {
            const row = el.closest('tr[data-classname]');
            if (row) {
                row.dataset.scenarioid = result.instances[0].scenarioid;
            }
        }
        return null;
    }).catch(() => {
        el.textContent = STRINGS.seatsloadfailed;
    });
};

/**
 * Show instances for a class in the detail area.
 *
 * @param {string} className The class name.
 * @param {string} rangeId The range UUID.
 */
const showClassInstances = async(className, rangeId) => {
    const detail = document.getElementById('class-instances-detail');
    const title = document.getElementById('class-instances-title');
    const body = document.getElementById('class-instances-body');

    // Store rangeId on the detail element for refresh use.
    detail.dataset.rangeid = rangeId || '';
    detail.dataset.classname = className;

    title.textContent = await getString('classtitle', 'local_rangeos', className);
    body.innerHTML = `<tr><td colspan="6" class="text-muted">${escapeHtml(STRINGS.loading)}</td></tr>`;
    detail.hidden = false;

    try {
        const result = await Ajax.call([{
            methodname: 'local_rangeos_get_class_instances',
            args: {envid: envId, classid: className},
        }])[0];

        if (!result.instances.length) {
            body.innerHTML = `<tr><td colspan="6" class="text-muted">${escapeHtml(STRINGS.noinstances)}</td></tr>`;
            return;
        }

        const rows = await Promise.all(result.instances.map(inst => buildInstanceRow(inst, className, rangeId)));
        body.innerHTML = '';
        rows.forEach(row => body.appendChild(row));
    } catch (err) {
        Notification.exception(err);
    }
};

/**
 * Build one row of the seats table.
 *
 * @param {object} inst The scenario instance as the web service returned it.
 * @param {string} className The class the seat belongs to.
 * @param {string} rangeId The range UUID.
 * @returns {Promise<HTMLTableRowElement>} The row.
 */
const buildInstanceRow = async(inst, className, rangeId) => {
    const tr = document.createElement('tr');
    const statusClass = inst.status === 'Ready' ? ' rangeos-pill-ok'
        : inst.status === 'NotReady' ? ' rangeos-pill-warn' : '';
    const seatLabel = inst.studentid
        ? escapeHtml(await getString('seatnumber', 'local_rangeos', inst.studentid))
        : '';

    let assignedLabel;
    if (inst.assigned) {
        const name = escapeHtml(inst.displayname || inst.username || STRINGS.unknownuser);
        const email = inst.email ? '<br><small class="text-muted">' + escapeHtml(inst.email) + '</small>' : '';
        assignedLabel = name + email;
    } else {
        assignedLabel = `<span class="text-muted">${escapeHtml(STRINGS.unassignedseat)}</span>`;
    }

    tr.innerHTML = `
        <td>${seatLabel}</td>
        <td>${escapeHtml(inst.scenarioname)}</td>
        <td><span class="rangeos-pill${statusClass}">${escapeHtml(inst.status)}</span></td>
        <td>${assignedLabel}</td>
        <td><code class="small">${escapeHtml(inst.id)}</code></td>
        <td>
            <button class="btn btn-sm btn-outline-danger rangeos-icon-btn"
                    data-action="delete-instance"
                    data-rangeid="${escapeHtml(rangeId || '')}"
                    data-scenarioid="${escapeHtml(inst.id)}"
                    data-classname="${escapeHtml(className)}"
                    title="${escapeHtml(STRINGS.removeseat)}"
                    aria-label="${escapeHtml(STRINGS.removeseat)}">
                &times;
            </button>
        </td>
    `;
    return tr;
};

/**
 * Delete a single scenario instance.
 *
 * @param {string} rangeId The range UUID.
 * @param {string} scenarioId The scenario instance UUID.
 * @param {string} className The class name (to refresh the view).
 * @param {HTMLElement} trigger The button that asked for the deletion.
 */
const deleteScenarioInstance = async(rangeId, scenarioId, className, trigger) => {
    const confirmed = await confirmAction({
        title: STRINGS.deleteseat_confirmtitle,
        message: STRINGS.deleteseat_confirm,
        actionLabel: STRINGS.deleteseat,
        destructive: true,
        triggerElement: trigger,
    });
    if (!confirmed) {
        return;
    }

    try {
        await Ajax.call([{
            methodname: 'local_rangeos_delete_scenario_instance',
            args: {envid: envId, rangeid: rangeId, scenarioid: scenarioId},
        }])[0];

        Notification.addNotification({message: STRINGS.seatdeleted, type: 'success'});
        // Refresh the instances view.
        showClassInstances(className, rangeId);
        // Refresh instance counts.
        document.querySelectorAll('.instance-count[data-classname="' + className + '"]').forEach((el) => {
            loadInstanceCount(className, el);
        });
    } catch (err) {
        Notification.exception(err);
    }
};

/**
 * Show the create class modal.
 * Loads local cmi5 activities that have AU mappings with scenarios.
 */
const showCreateClassModal = () => {
    const modalId = 'rangeos-create-class-modal-' + Date.now();
    const modalHtml = `
        <div class="modal fade rangeos-modal mod-cmi5-library" id="${modalId}" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${escapeHtml(STRINGS.createclass)}</h5>
                        <button type="button" class="close" aria-label="${escapeHtml(STRINGS.closebuttontitle)}"
                                id="${modalId}-close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="${modalId}-classid">${escapeHtml(STRINGS.classid)}</label>
                            <input type="text" class="form-control" id="${modalId}-classid"
                                   placeholder="${escapeHtml(STRINGS.classid_placeholder)}">
                        </div>
                        <div class="form-group">
                            <label for="${modalId}-scenario">${escapeHtml(STRINGS.activityscenario)}</label>
                            <select class="custom-select" id="${modalId}-scenario">
                                <option value="">${escapeHtml(STRINGS.loadingactivities)}</option>
                            </select>
                            <small class="form-text text-muted" id="${modalId}-scenario-detail"></small>
                        </div>
                        <div class="form-group">
                            <label for="${modalId}-count">${escapeHtml(STRINGS.numberofseats)}</label>
                            <input type="number" class="form-control" id="${modalId}-count"
                                   value="20" min="1" max="200">
                        </div>
                        <div class="form-group">
                            <label for="${modalId}-enddate">${escapeHtml(STRINGS.enddate)}</label>
                            <input type="date" class="form-control" id="${modalId}-enddate">
                            <small class="form-text text-muted">${escapeHtml(STRINGS.enddate_default)}</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                                id="${modalId}-cancel">${escapeHtml(STRINGS.cancel)}</button>
                        <button type="button" class="btn btn-primary"
                                id="${modalId}-save">${escapeHtml(STRINGS.create)}</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = modalHtml;
    document.body.appendChild(wrapper);

    const modalEl = document.getElementById(modalId);
    const scenarioSelect = document.getElementById(modalId + '-scenario');
    const scenarioDetail = document.getElementById(modalId + '-scenario-detail');
    // eslint-disable-next-line no-undef
    const jq = window.jQuery;

    // Load local activities with mapped scenarios.
    Ajax.call([{
        methodname: 'local_rangeos_get_local_activity_scenarios',
        args: {envid: envId},
    }])[0].then((result) => {
        scenarioSelect.innerHTML = '';
        const activities = result.activities || [];
        if (!activities.length) {
            scenarioSelect.appendChild(placeholderOption(STRINGS.noactivityscenarios));
            return;
        }
        scenarioSelect.appendChild(placeholderOption(STRINGS.selectactivityscenario));

        // Group by course for cleaner display using optgroups.
        let lastCourse = '';
        let optgroup = null;
        activities.forEach((a) => {
            if (a.coursename !== lastCourse) {
                optgroup = document.createElement('optgroup');
                optgroup.label = a.coursename;
                scenarioSelect.appendChild(optgroup);
                lastCourse = a.coursename;
            }

            const parts = [a.activityname];
            if (a.autitle && a.autitle !== a.activityname) {
                parts.push(a.autitle);
            }
            if (a.scenarioname) {
                parts.push(a.scenarioname);
            }
            const label = parts.join(' — ');
            const opt = document.createElement('option');
            opt.value = a.scenariouuid;
            opt.textContent = label;
            (optgroup || scenarioSelect).appendChild(opt);
        });
    }).catch(() => {
        scenarioSelect.innerHTML = '';
        scenarioSelect.appendChild(placeholderOption(STRINGS.activitiesloadfailed));
    });

    // Show UUID detail when selection changes.
    scenarioSelect.addEventListener('change', async() => {
        const opt = scenarioSelect.selectedOptions[0];
        scenarioDetail.textContent = (opt && opt.value)
            ? await getString('scenariouuid', 'local_rangeos', opt.value)
            : '';
    });

    jq(modalEl).modal('show');

    document.getElementById(modalId + '-close').addEventListener('click', () => {
        jq(modalEl).modal('hide');
    });
    document.getElementById(modalId + '-cancel').addEventListener('click', () => {
        jq(modalEl).modal('hide');
    });

    document.getElementById(modalId + '-save').addEventListener('click', () => {
        const classId = modalEl.querySelector('#' + modalId + '-classid').value.trim();
        const scenarioId = scenarioSelect.value;
        const count = parseInt(modalEl.querySelector('#' + modalId + '-count').value, 10);
        const enddateVal = modalEl.querySelector('#' + modalId + '-enddate').value;
        const enddate = enddateVal ? new Date(enddateVal + 'T23:59:59Z').toISOString() : '';

        if (!classId || !scenarioId || !count) {
            Notification.addNotification({message: STRINGS.createclass_required, type: 'error'});
            return;
        }

        const saveBtn = document.getElementById(modalId + '-save');
        saveBtn.disabled = true;
        saveBtn.textContent = STRINGS.creating;

        Ajax.call([{
            methodname: 'local_rangeos_create_class',
            args: {
                envid: envId,
                scenarioid: scenarioId,
                classid: classId,
                count: count,
                enddate: enddate,
            },
        }])[0].then(async() => {
            jq(modalEl).modal('hide');
            Notification.addNotification({
                message: await getString('classqueued', 'local_rangeos', {classid: escapeHtml(classId), count}),
                type: 'success',
            });
            return null;
        }).catch((err) => {
            saveBtn.disabled = false;
            saveBtn.textContent = STRINGS.create;
            Notification.exception(err);
        });
    });

    // Cleanup on close.
    jq(modalEl).on('hidden.bs.modal', () => {
        wrapper.remove();
    });
};

/**
 * Show the add seats modal for an existing class.
 *
 * @param {string} className The class name.
 * @param {string} knownScenarioId Scenario UUID if already known from instances.
 */
const showAddSeatsModal = async(className, knownScenarioId) => {
    const modalId = 'rangeos-add-seats-modal-' + Date.now();
    const heading = await getString('addseats_title', 'local_rangeos', className);
    const modalHtml = `
        <div class="modal fade rangeos-modal mod-cmi5-library" id="${modalId}" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${escapeHtml(heading)}</h5>
                        <button type="button" class="close" aria-label="${escapeHtml(STRINGS.closebuttontitle)}"
                                id="${modalId}-close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="${modalId}-count">${escapeHtml(STRINGS.additionalseats)}</label>
                            <input type="number" class="form-control" id="${modalId}-count"
                                   value="10" min="1" max="200">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                                id="${modalId}-cancel">${escapeHtml(STRINGS.cancel)}</button>
                        <button type="button" class="btn btn-primary"
                                id="${modalId}-save">${escapeHtml(STRINGS.addseats)}</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = modalHtml;
    document.body.appendChild(wrapper);

    const modalEl = document.getElementById(modalId);
    // eslint-disable-next-line no-undef
    const jq = window.jQuery;

    jq(modalEl).modal('show');

    document.getElementById(modalId + '-close').addEventListener('click', () => {
        jq(modalEl).modal('hide');
    });
    document.getElementById(modalId + '-cancel').addEventListener('click', () => {
        jq(modalEl).modal('hide');
    });

    document.getElementById(modalId + '-save').addEventListener('click', () => {
        const count = parseInt(document.getElementById(modalId + '-count').value, 10);

        if (!knownScenarioId) {
            Notification.addNotification({message: STRINGS.addseats_noscenario, type: 'error'});
            return;
        }
        if (!count || count < 1) {
            Notification.addNotification({message: STRINGS.addseats_countminimum, type: 'error'});
            return;
        }

        const saveBtn = document.getElementById(modalId + '-save');
        saveBtn.disabled = true;
        saveBtn.textContent = STRINGS.adding;

        Ajax.call([{
            methodname: 'local_rangeos_create_class',
            args: {
                envid: envId,
                scenarioid: knownScenarioId,
                classid: className,
                count: count,
            },
        }])[0].then(async() => {
            jq(modalEl).modal('hide');
            Notification.addNotification({
                message: await getString('seatsqueued', 'local_rangeos', {classid: escapeHtml(className), count}),
                type: 'success',
            });
            // Refresh instance counts.
            document.querySelectorAll('.instance-count[data-classname="' + className + '"]').forEach((el) => {
                loadInstanceCount(className, el);
            });
            // Refresh detail view if open.
            const detail = document.getElementById('class-instances-detail');
            if (detail && !detail.hidden) {
                showClassInstances(className, detail.dataset.rangeid || '');
            }
            return null;
        }).catch((err) => {
            saveBtn.disabled = false;
            saveBtn.textContent = STRINGS.addseats;
            Notification.exception(err);
        });
    });

    jq(modalEl).on('hidden.bs.modal', () => {
        wrapper.remove();
    });
};

/**
 * Delete a class after confirmation.
 *
 * @param {string} className The class name to delete.
 * @param {HTMLElement} trigger The button that asked for the deletion.
 */
const deleteClass = async(className, trigger) => {
    const confirmed = await confirmAction({
        title: STRINGS.deleteclass_confirmtitle,
        message: await getString('deleteclass_confirm', 'local_rangeos', escapeHtml(className)),
        actionLabel: STRINGS.delete,
        destructive: true,
        triggerElement: trigger,
    });
    if (!confirmed) {
        return;
    }

    Notification.addNotification({message: STRINGS.deleteclass_notimplemented, type: 'warning'});
};

/**
 * Build a non-selectable first option for a dropdown.
 *
 * @param {string} label The option's text.
 * @returns {HTMLOptionElement} The option.
 */
const placeholderOption = (label) => {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = label;
    return opt;
};

/**
 * Escape HTML entities.
 *
 * @param {string} str Input string.
 * @returns {string} Escaped string.
 */
const escapeHtml = (str) => {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
};
