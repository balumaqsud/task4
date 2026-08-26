import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

function initializeTooltips() {
    if (!window.bootstrap) {
        return;
    }

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        window.bootstrap.Tooltip.getOrCreateInstance(element);
    });
}

function initializeUserSelection() {
    const selectAll = document.getElementById('select-all');
    const selections = [...document.querySelectorAll('.user-selection')];
    const actions = [...document.querySelectorAll('.bulk-action')];

    if (!selectAll || selectAll.dataset.selectionInitialized === 'true') {
        return;
    }

    selectAll.dataset.selectionInitialized = 'true';

    const updateState = () => {
        const selectedCount = selections.filter((checkbox) => checkbox.checked).length;

        selectAll.checked = selections.length > 0 && selectedCount === selections.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < selections.length;
        actions.forEach((button) => button.disabled = selectedCount === 0);
    };

    selectAll.addEventListener('change', () => {
        selections.forEach((checkbox) => checkbox.checked = selectAll.checked);
        updateState();
    });

    selections.forEach((checkbox) => checkbox.addEventListener('change', updateState));
    updateState();
}

function initializePage() {
    initializeTooltips();
    initializeUserSelection();
}

document.addEventListener('DOMContentLoaded', initializePage);
document.addEventListener('turbo:load', initializePage);
