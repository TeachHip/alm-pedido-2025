// Generic "click to toggle a boolean, swap the check/cross indicator + label" widget.
// Shared by product visibility, section visibility, member active, and global settings toggles.
//
// Usage: onclick="return adminToggle(url, this, { valueKey, trueLabel, falseLabel, errorMessage, dataAttr })"
// - valueKey: JSON key holding the new boolean in the endpoint's response (e.g. 'visible', 'value').
//   Required -- every endpoint this widget talks to returns {success, [valueKey]: bool}.
// - dataAttr: if the row (closest <tr>) carries a data-* flag a filter-toggle.js
//   filter reads (e.g. 'data-active', 'data-visible'), pass its name here so it gets
//   kept in sync -- otherwise a filtered list silently goes stale until the next
//   full page reload, since this function only updates the visual ✓/✗ indicator.
function adminToggle(url, element, opts) {
    opts = opts || {};
    const trueLabel = opts.trueLabel || 'Visible';
    const falseLabel = opts.falseLabel || 'Oculto';
    const errorMessage = opts.errorMessage || 'Error al cambiar el estado';
    const valueKey = opts.valueKey;
    const dataAttr = opts.dataAttr || null;

    fetch(url)
        .then(function(response) {
            return response.json().then(function(data) {
                if (!data.success) throw new Error(errorMessage);
                return !!data[valueKey];
            });
        })
        .then(function(newState) {
            const indicator = element.querySelector('.visible-indicator, .hidden-indicator');
            const text = element.querySelector('small');
            if (newState) {
                indicator.classList.remove('hidden-indicator');
                indicator.classList.add('visible-indicator');
                indicator.textContent = '✓';
                text.textContent = trueLabel;
            } else {
                indicator.classList.remove('visible-indicator');
                indicator.classList.add('hidden-indicator');
                indicator.textContent = '✗';
                text.textContent = falseLabel;
            }

            if (dataAttr) {
                const row = element.closest('tr');
                if (row) {
                    row.setAttribute(dataAttr, newState ? '1' : '0');
                    row.dispatchEvent(new CustomEvent('admintoggle:changed', {
                        bubbles: true,
                        detail: { dataAttr: dataAttr }
                    }));
                }
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert(errorMessage);
        });

    return false;
}
