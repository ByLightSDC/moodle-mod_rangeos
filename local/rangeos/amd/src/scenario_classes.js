/**
 * Scenario classes management functionality.
 *
 * @module     local_rangeos/scenario_classes
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

let envId = 0;

/**
 * Initialize the scenario classes page.
 */
export const init = () => {
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
            deleteClass(deleteBtn.dataset.classname);
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
                deleteInstBtn.dataset.classname
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
    }])[0].then((result) => {
        el.textContent = result.total + ' instances';
        // Store the scenarioId from the first instance on the class row for add-seats.
        if (result.instances.length > 0 && result.instances[0].scenarioid) {
            const row = el.closest('tr[data-classname]');
            if (row) {
                row.dataset.scenarioid = result.instances[0].scenarioid;
            }
        }
    }).catch(() => {
        el.textContent = 'Error loading';
    });
};

/**
 * Show instances for a class in the detail area.
 *
 * @param {string} className The class name.
 * @param {string} rangeId The range UUID.
 */
const showClassInstances = (className, rangeId) => {
    const detail = document.getElementById('class-instances-detail');
    const title = document.getElementById('class-instances-title');
    const body = document.getElementById('class-instances-body');

    // Store rangeId on the detail element for refresh use.
    detail.dataset.rangeid = rangeId || '';
    detail.dataset.classname = className;

    title.textContent = 'Class: ' + className;
    body.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
    detail.style.display = '';

    Ajax.call([{
        methodname: 'local_rangeos_get_class_instances',
        args: {envid: envId, classid: className},
    }])[0].then((result) => {
        if (!result.instances.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-muted">No instances found.</td></tr>';
            return;
        }

        body.innerHTML = '';
        result.instances.forEach((inst) => {
            const tr = document.createElement('tr');
            const statusClass = inst.status === 'Ready' ? 'success'
                : inst.status === 'NotReady' ? 'warning' : 'secondary';
            const seatLabel = inst.studentid ? 'Seat ' + escapeHtml(inst.studentid) : '';
            let assignedLabel;
            if (inst.assigned) {
                const name = escapeHtml(inst.displayname || inst.username || 'Unknown');
                const email = inst.email ? '<br><small class="text-muted">' + escapeHtml(inst.email) + '</small>' : '';
                assignedLabel = name + email;
            } else {
                assignedLabel = '<span class="text-muted">Unassigned</span>';
            }
            tr.innerHTML = `
                <td>${seatLabel}</td>
                <td>${escapeHtml(inst.scenarioname)}</td>
                <td><span class="badge badge-${statusClass}">${escapeHtml(inst.status)}</span></td>
                <td>${assignedLabel}</td>
                <td><code class="small">${escapeHtml(inst.id)}</code></td>
                <td>
                    <button class="btn btn-sm btn-outline-danger"
                            data-action="delete-instance"
                            data-rangeid="${escapeHtml(rangeId || '')}"
                            data-scenarioid="${escapeHtml(inst.id)}"
                            data-classname="${escapeHtml(className)}"
                            title="Remove this seat.">
                        &times;
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
    }).catch(Notification.exception);
};

/**
 * Delete a single scenario instance.
 *
 * @param {string} rangeId The range UUID.
 * @param {string} scenarioId The scenario instance UUID.
 * @param {string} className The class name (to refresh the view).
 */
const deleteScenarioInstance = (rangeId, scenarioId, className) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm('Delete this seat? This cannot be undone.')) {
        return;
    }

    Ajax.call([{
        methodname: 'local_rangeos_delete_scenario_instance',
        args: {envid: envId, rangeid: rangeId, scenarioid: scenarioId},
    }])[0].then(() => {
        Notification.addNotification({message: 'Scenario instance deleted.', type: 'success'});
        // Refresh the instances view.
        showClassInstances(className, rangeId);
        // Refresh instance counts.
        document.querySelectorAll('.instance-count[data-classname="' + className + '"]').forEach((el) => {
            loadInstanceCount(className, el);
        });
    }).catch(Notification.exception);
};

/**
 * Show the create class modal.
 * Loads local cmi5 activities that have AU mappings with scenarios.
 */
const showCreateClassModal = () => {
    const modalId = 'rangeos-create-class-modal-' + Date.now();
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Class</h5>
                        <button type="button" class="close" aria-label="Close"
                                id="${modalId}-close"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="${modalId}-classid">Class ID</label>
                            <input type="text" class="form-control" id="${modalId}-classid"
                                   placeholder="e.g. cyber-101-spring-2026">
                        </div>
                        <div class="form-group">
                            <label for="${modalId}-scenario">Activity / Scenario</label>
                            <select class="custom-select" id="${modalId}-scenario">
                                <option value="">Loading local activities...</option>
                            </select>
                            <small class="form-text text-muted" id="${modalId}-scenario-detail"></small>
                        </div>
                        <div class="form-group">
                            <label for="${modalId}-count">Number of Seats</label>
                            <input type="number" class="form-control" id="${modalId}-count"
                                   value="20" min="1" max="200">
                        </div>
                        <div class="form-group">
                            <label for="${modalId}-enddate">End Date</label>
                            <input type="date" class="form-control" id="${modalId}-enddate">
                            <small class="form-text text-muted">Defaults to 6 months from today if not set.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="${modalId}-cancel">Cancel</button>
                        <button type="button" class="btn btn-primary" id="${modalId}-save">Create</button>
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
        scenarioSelect.innerHTML = '<option value="">-- Select an activity scenario --</option>';
        const activities = result.activities || [];
        if (!activities.length) {
            scenarioSelect.innerHTML = '<option value="">No activities with scenario mappings found</option>';
            return;
        }

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
        scenarioSelect.innerHTML = '<option value="">Error loading activities</option>';
    });

    // Show UUID detail when selection changes.
    scenarioSelect.addEventListener('change', () => {
        const opt = scenarioSelect.selectedOptions[0];
        scenarioDetail.textContent = (opt && opt.value) ? 'UUID: ' + opt.value : '';
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
            Notification.addNotification({message: 'Class ID, scenario, and seat count are required.', type: 'error'});
            return;
        }

        const saveBtn = document.getElementById(modalId + '-save');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Creating...';

        Ajax.call([{
            methodname: 'local_rangeos_create_class',
            args: {
                envid: envId,
                scenarioid: scenarioId,
                classid: classId,
                count: count,
                enddate: enddate,
            },
        }])[0].then(() => {
            jq(modalEl).modal('hide');
            Notification.addNotification({
                message: 'Class "' + classId + '" has been queued for deployment with ' + count
                    + ' seats. Deployment may take several minutes — refresh the page to check progress.',
                type: 'success',
            });
        }).catch((err) => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Create';
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
const showAddSeatsModal = (className, knownScenarioId) => {
    const modalId = 'rangeos-add-seats-modal-' + Date.now();
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Seats: ${escapeHtml(className)}</h5>
                        <button type="button" class="close" aria-label="Close"
                                id="${modalId}-close"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="${modalId}-count">Additional Seats</label>
                            <input type="number" class="form-control" id="${modalId}-count"
                                   value="10" min="1" max="200">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="${modalId}-cancel">Cancel</button>
                        <button type="button" class="btn btn-primary" id="${modalId}-save">Add Seats</button>
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
            Notification.addNotification({
                message: 'Could not determine the scenario for this class. Try viewing instances first.',
                type: 'error',
            });
            return;
        }
        if (!count || count < 1) {
            Notification.addNotification({message: 'Count must be at least 1.', type: 'error'});
            return;
        }

        const saveBtn = document.getElementById(modalId + '-save');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Adding...';

        Ajax.call([{
            methodname: 'local_rangeos_create_class',
            args: {
                envid: envId,
                scenarioid: knownScenarioId,
                classid: className,
                count: count,
            },
        }])[0].then(() => {
            jq(modalEl).modal('hide');
            Notification.addNotification({
                message: count + ' seats queued for ' + className
                    + '. Deployment may take several minutes — refresh to check progress.',
                type: 'success',
            });
            // Refresh instance counts.
            document.querySelectorAll('.instance-count[data-classname="' + className + '"]').forEach((el) => {
                loadInstanceCount(className, el);
            });
            // Refresh detail view if open.
            const detail = document.getElementById('class-instances-detail');
            if (detail && detail.style.display !== 'none') {
                showClassInstances(className, detail.dataset.rangeid || '');
            }
        }).catch((err) => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Add Seats';
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
 */
const deleteClass = (className) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm('Are you sure you want to delete class "' + className + '"? This will end all prestaged scenarios.')) {
        return;
    }

    Notification.addNotification({
        message: 'Class deletion is not yet implemented via this UI. Use the devops-api directly.',
        type: 'warning',
    });
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
