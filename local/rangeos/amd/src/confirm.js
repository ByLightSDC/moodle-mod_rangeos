/**
 * Confirmation dialogs for local_rangeos.
 *
 * Every action that cannot be undone asks in a Moodle modal rather than a native
 * window.confirm() box, so the question carries the plugin's own furniture
 * (.rangeos-modal), traps focus and reads correctly to a screen reader.
 *
 * Two ways in:
 *  - confirmAction() for JavaScript-driven actions, which awaits a boolean.
 *  - init() for plain links, which name their strings in data-rangeos-confirm
 *    attributes so a template needs no inline onclick.
 *
 * The declarative form carries language string *keys*, not the strings themselves:
 * several of the questions contain double quotes, which no attribute would survive.
 *
 * @module     local_rangeos/confirm
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalDeleteCancel from 'core/modal_delete_cancel';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';
import {get_string as getString} from 'core/str';

/**
 * Ask the user to confirm an action.
 *
 * A destructive action gets the delete/cancel dialog, whose confirm button is drawn
 * in the danger palette and already reads "Delete"; anything else gets save/cancel.
 *
 * @param {object}      options
 * @param {string}      options.title            The dialog's heading.
 * @param {string}      options.message          The question, stating what the action does.
 * @param {string}      [options.actionLabel]    Label for the confirming button. Defaults to the modal's own.
 * @param {boolean}     [options.destructive]    Whether the action cannot be undone.
 * @param {HTMLElement} [options.triggerElement] Element to return focus to once the dialog closes.
 * @returns {Promise<boolean>} Whether the user confirmed.
 */
export const confirmAction = async({title, message, actionLabel, destructive = false, triggerElement = null}) => {
    const ModalClass = destructive ? ModalDeleteCancel : ModalSaveCancel;
    const modal = await ModalClass.create({
        title,
        body: message,
        removeOnClose: true,
        returnElement: triggerElement,
    });

    // The dialog is appended to the body, outside .rangeos-dashboard, so it carries the
    // RangeOS token scope itself - the same way this plugin's other dialogs do.
    // .mod-cmi5-library is what puts the --c5-* palette those tokens read in scope;
    // see the dialog block in styles.css.
    modal.getRoot().addClass('rangeos-modal mod-cmi5-library');

    if (actionLabel) {
        await (destructive ? modal.setDeleteButtonText(actionLabel) : modal.setSaveButtonText(actionLabel));
    }

    await modal.show();

    return new Promise((resolve) => {
        const root = modal.getRoot();
        root.on(destructive ? ModalEvents.delete : ModalEvents.save, () => resolve(true));
        // Cancelling, dismissing and pressing Escape all land on hidden. It fires after the
        // confirm event too, where the already-settled promise ignores it.
        root.on(ModalEvents.hidden, () => resolve(false));
    });
};

/**
 * Wire up any link or button that declares its own confirmation.
 *
 * Recognised attributes, all naming language string keys rather than text:
 *  - data-rangeos-confirm            key of the question (required; presence opts the element in)
 *  - data-rangeos-confirm-title      key of the dialog's heading; defaults to core "confirm"
 *  - data-rangeos-confirm-action     key of the confirming button's label; defaults to the modal's own
 *  - data-rangeos-confirm-param      {$a} for the question, when it takes one
 *  - data-rangeos-confirm-component  component for the keys above; defaults to local_rangeos
 *  - data-rangeos-confirm-destructive present when the action cannot be undone
 */
export const init = () => {
    document.addEventListener('click', async(e) => {
        const trigger = e.target.closest('[data-rangeos-confirm]');
        if (!trigger) {
            return;
        }

        // Already confirmed: let the second, synthesised click through to the link.
        if (trigger.dataset.rangeosConfirmed) {
            delete trigger.dataset.rangeosConfirmed;
            return;
        }

        e.preventDefault();

        const data = trigger.dataset;
        const component = data.rangeosConfirmComponent || 'local_rangeos';
        // The dialog renders its body as HTML, so the parameter - a name someone typed -
        // is escaped before it is substituted into the question.
        const param = 'rangeosConfirmParam' in data ? escapeHtml(data.rangeosConfirmParam) : undefined;

        const confirmed = await confirmAction({
            title: data.rangeosConfirmTitle
                ? await getString(data.rangeosConfirmTitle, component)
                : await getString('confirm'),
            message: await getString(data.rangeosConfirm, component, param),
            actionLabel: data.rangeosConfirmAction
                ? await getString(data.rangeosConfirmAction, component)
                : null,
            destructive: 'rangeosConfirmDestructive' in data,
            triggerElement: trigger,
        });

        if (confirmed) {
            trigger.dataset.rangeosConfirmed = '1';
            trigger.click();
        }
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
    div.textContent = str ?? '';
    return div.innerHTML;
};
