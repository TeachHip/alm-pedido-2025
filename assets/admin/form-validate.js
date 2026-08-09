// Generic required-field validator for admin forms: blocks submit until every
// listed rule passes, shows a message under each bad field (a
// [data-error-for="<name>"] element must exist next to it) plus a summary
// banner (#form-error-summary), and marks bad fields with .field-invalid.
//
// Usage:
//   adminValidateForm(formEl, [
//       { name: 'section', message: 'Selecciona una sección.' },
//       { name: 'price_member', type: 'number', message: 'Debe ser mayor que 0.' }
//   ], function(form) {
//       // optional: return extra { fieldName: message } errors from custom checks
//       return {};
//   });
function adminValidateForm(form, rules, customValidator) {
    form.addEventListener('submit', function(e) {
        const errors = {};

        rules.forEach(function(rule) {
            const field = form.querySelector('[name="' + rule.name + '"]');
            const value = field ? field.value.trim() : '';
            const invalid = rule.type === 'number' ? !(parseFloat(value) > 0) : value === '';
            if (invalid) errors[rule.name] = rule.message;
        });

        if (customValidator) {
            Object.assign(errors, customValidator(form) || {});
        }

        adminShowFormErrors(form, errors);
        if (Object.keys(errors).length > 0) {
            e.preventDefault();
        }
    });
}

function adminShowFormErrors(form, errors) {
    form.querySelectorAll('.field-error').forEach(function(el) { el.textContent = ''; });
    form.querySelectorAll('.field-invalid').forEach(function(el) { el.classList.remove('field-invalid'); });

    const summary = document.getElementById('form-error-summary');
    const fieldNames = Object.keys(errors);

    if (fieldNames.length === 0) {
        if (summary) summary.style.display = 'none';
        return;
    }

    if (summary) {
        const count = fieldNames.length;
        const plural = count === 1 ? '1 campo' : count + ' campos';
        summary.textContent = '❌ Corrige ' + plural + ' antes de guardar (señalados en rojo abajo).';
        summary.style.display = 'block';
        summary.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    fieldNames.forEach(function(key) {
        const errEl = form.querySelector('[data-error-for="' + key + '"]');
        if (errEl) errEl.textContent = errors[key];
        const fieldEl = form.querySelector('[name="' + key + '"]');
        if (fieldEl) fieldEl.classList.add('field-invalid');
    });
}
