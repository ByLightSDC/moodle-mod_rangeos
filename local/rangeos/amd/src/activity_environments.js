/**
 * Activity environment assignment for local_rangeos.
 *
 * @module     local_rangeos/activity_environments
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Show a brief save indicator next to the dropdown.
 *
 * @param {HTMLElement} select  The select element that changed.
 * @param {boolean}     success Whether the save succeeded.
 */
const showIndicator = (select, success) => {
    const indicator = select.parentElement.querySelector('.rangeos-save-indicator');
    if (!indicator) {
        return;
    }
    indicator.textContent = success ? '✓' : '✗';
    indicator.className = 'rangeos-save-indicator ml-2 ' + (success ? 'text-success' : 'text-danger');
    indicator.style.display = '';
    setTimeout(() => {
        indicator.style.display = 'none';
    }, 2500);
};

/**
 * Handle an environment dropdown change for a single activity.
 *
 * @param {HTMLSelectElement} select The changed dropdown.
 */
const assignEnvironment = async(select) => {
    const cmi5id = parseInt(select.dataset.cmi5id, 10);
    const envid = parseInt(select.value, 10);

    if (!cmi5id) {
        return;
    }

    // envid === 0 means "None" — nothing to assign, just reflect the choice.
    if (envid === 0) {
        showIndicator(select, true);
        return;
    }

    select.disabled = true;
    try {
        await Ajax.call([{
            methodname: 'local_rangeos_apply_environment_profile',
            args: {envid, cmi5ids: [cmi5id]},
        }])[0];
        showIndicator(select, true);
        const row = select.closest('tr');
        if (row) {
            row.dataset.currentEnvid = envid;
        }
    } catch (err) {
        showIndicator(select, false);
        Notification.exception(err);
        // Revert to previous value.
        const row = select.closest('tr');
        if (row) {
            select.value = row.dataset.currentEnvid || '0';
        }
    } finally {
        select.disabled = false;
    }
};

/**
 * Initialize the activity environments page.
 */
export const init = () => {
    // Course filter — reload page with courseid param.
    const courseFilter = document.querySelector('[data-action="filter-course"]');
    if (courseFilter) {
        courseFilter.addEventListener('change', (e) => {
            const baseUrl = document.querySelector('[data-baseurl]')?.dataset.baseurl;
            if (baseUrl) {
                const sep = baseUrl.includes('?') ? '&' : '?';
                window.location.href = `${baseUrl}${sep}courseid=${e.target.value}`;
            }
        });
    }

    // Environment dropdowns — delegate via document so it works after any DOM updates.
    document.addEventListener('change', (e) => {
        const select = e.target.closest('[data-action="assign-environment"]');
        if (select) {
            assignEnvironment(select);
        }
    });
};
