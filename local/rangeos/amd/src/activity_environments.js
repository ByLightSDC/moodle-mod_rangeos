/**
 * Activity environment assignment for local_rangeos.
 *
 * @module     local_rangeos/activity_environments
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

/** How long the outcome stays beside the dropdown, in milliseconds. */
const INDICATOR_TIMEOUT = 2500;

/** Timers keyed by indicator, so a second save cancels the first one's fade. */
const indicatorTimers = new WeakMap();

/**
 * Show a brief save indicator next to the dropdown.
 *
 * The outcome is stated in words rather than a tick or a cross: the indicator carries
 * aria-live, so this is the only confirmation a screen reader gets that the change
 * was saved.
 *
 * @param {HTMLElement} select  The select element that changed.
 * @param {boolean}     success Whether the save succeeded.
 * @returns {Promise<void>}
 */
const showIndicator = async(select, success) => {
    const indicator = select.parentElement.querySelector('.rangeos-save-indicator');
    if (!indicator) {
        return;
    }

    clearTimeout(indicatorTimers.get(indicator));

    indicator.textContent = await getString(
        success ? 'environmentassigned' : 'environmentassignfailed',
        'local_rangeos'
    );
    indicator.className = 'rangeos-save-indicator is-shown '
        + (success ? 'rangeos-save-indicator-ok' : 'rangeos-save-indicator-bad');

    indicatorTimers.set(indicator, setTimeout(() => {
        indicator.classList.remove('is-shown');
        // Emptied only once it has faded, so the text doesn't vanish mid-transition.
        indicatorTimers.set(indicator, setTimeout(() => {
            indicator.textContent = '';
            indicator.className = 'rangeos-save-indicator';
        }, 150));
    }, INDICATOR_TIMEOUT));
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
        await showIndicator(select, true);
        return;
    }

    select.disabled = true;
    try {
        await Ajax.call([{
            methodname: 'local_rangeos_apply_environment_profile',
            args: {envid, cmi5ids: [cmi5id]},
        }])[0];
        await showIndicator(select, true);
        const row = select.closest('tr');
        if (row) {
            row.dataset.currentEnvid = envid;
        }
    } catch (err) {
        await showIndicator(select, false);
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
    const envFilter = document.getElementById('rangeos-env-filter');
    if (envFilter) {
        envFilter.addEventListener('change', () => envFilter.form.requestSubmit());
    }

    // Environment dropdowns — delegate via document so it works after any DOM updates.
    document.addEventListener('change', (e) => {
        const select = e.target.closest('[data-action="assign-environment"]');
        if (select) {
            assignEnvironment(select);
        }
    });
};
