import './stimulus_bootstrap.js';
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
    const filter = document.getElementById('user-filter');
    const rows = [...document.querySelectorAll('[data-user-row]')];
    const noMatches = document.getElementById('no-matching-users');

    if (!selectAll || selectAll.dataset.selectionInitialized === 'true') {
        return;
    }

    selectAll.dataset.selectionInitialized = 'true';

    const updateState = () => {
        const visibleSelections = selections.filter((checkbox) => !checkbox.closest('[data-user-row]').hidden);
        const selectedCount = selections.filter((checkbox) => checkbox.checked).length;
        const visibleSelectedCount = visibleSelections.filter((checkbox) => checkbox.checked).length;

        selectAll.disabled = visibleSelections.length === 0;
        selectAll.checked = visibleSelections.length > 0 && visibleSelectedCount === visibleSelections.length;
        selectAll.indeterminate = visibleSelectedCount > 0 && visibleSelectedCount < visibleSelections.length;
        actions.forEach((button) => button.disabled = selectedCount === 0);
    };

    selectAll.addEventListener('change', () => {
        selections
            .filter((checkbox) => !checkbox.closest('[data-user-row]').hidden)
            .forEach((checkbox) => checkbox.checked = selectAll.checked);
        updateState();
    });

    selections.forEach((checkbox) => checkbox.addEventListener('change', updateState));

    filter?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    filter?.addEventListener('input', () => {
        const query = filter.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        rows.forEach((row) => {
            const visible = row.dataset.searchValue.includes(query);
            row.hidden = !visible;
            row.querySelector('.user-selection').checked = false;
            visibleCount += visible ? 1 : 0;
        });

        if (noMatches) {
            noMatches.hidden = rows.length === 0 || visibleCount > 0;
        }

        updateState();
    });

    updateState();
}

function initializeRelativeTimes() {
    document.querySelectorAll('.last-seen[data-timestamp]:not([data-relative-initialized])').forEach((element) => {
        const timestamp = new Date(element.dataset.timestamp);
        if (Number.isNaN(timestamp.getTime())) {
            return;
        }

        const elapsedSeconds = Math.max(0, Math.floor((Date.now() - timestamp.getTime()) / 1000));
        let value;

        if (elapsedSeconds < 60) {
            value = 'less than a minute ago';
        } else if (elapsedSeconds < 3600) {
            const minutes = Math.floor(elapsedSeconds / 60);
            value = `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
        } else if (elapsedSeconds < 86400) {
            const hours = Math.floor(elapsedSeconds / 3600);
            value = `${hours} hour${hours === 1 ? '' : 's'} ago`;
        } else if (elapsedSeconds < 604800) {
            const days = Math.floor(elapsedSeconds / 86400);
            value = `${days} day${days === 1 ? '' : 's'} ago`;
        } else if (elapsedSeconds < 2629800) {
            const weeks = Math.floor(elapsedSeconds / 604800);
            value = `${weeks} week${weeks === 1 ? '' : 's'} ago`;
        } else if (elapsedSeconds < 31557600) {
            const months = Math.floor(elapsedSeconds / 2629800);
            value = `${months} month${months === 1 ? '' : 's'} ago`;
        } else {
            const years = Math.floor(elapsedSeconds / 31557600);
            value = `${years} year${years === 1 ? '' : 's'} ago`;
        }

        element.textContent = value;
        element.title = timestamp.toLocaleString();
        element.dataset.relativeInitialized = 'true';
    });
}

function initializePage() {
    initializeRelativeTimes();
    initializeUserSelection();
    initializeTooltips();
}

document.addEventListener('DOMContentLoaded', initializePage);
document.addEventListener('turbo:load', initializePage);
