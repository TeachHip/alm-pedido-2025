// Generic "click to toggle a boolean, swap the check/cross indicator + label" widget.
// Shared by product visibility, section visibility, and global settings toggles.
//
// Usage: onclick="return adminToggle(url, this, { valueKey, trueLabel, falseLabel, errorMessage })"
// - valueKey: JSON key holding the new boolean in the endpoint's response (e.g. 'visible', 'value').
//   Omit it for legacy endpoints that just redirect/return 200 with no body — the widget then
//   flips whatever state is currently shown instead of trusting a server value.
function adminToggle(url, element, opts) {
    opts = opts || {};
    const trueLabel = opts.trueLabel || 'Visible';
    const falseLabel = opts.falseLabel || 'Oculto';
    const errorMessage = opts.errorMessage || 'Error al cambiar el estado';
    const valueKey = opts.valueKey || null;

    fetch(url)
        .then(function(response) {
            if (valueKey) {
                return response.json().then(function(data) {
                    if (!data.success) throw new Error(errorMessage);
                    return !!data[valueKey];
                });
            }
            if (!response.ok) throw new Error(errorMessage);
            return element.querySelector('.visible-indicator') === null;
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
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert(errorMessage);
        });

    return false;
}
