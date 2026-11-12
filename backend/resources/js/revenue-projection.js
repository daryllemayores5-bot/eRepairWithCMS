// Delegation also handles Statamic's mounted Blade content. No inline scripts,
// payment SDK or external service is involved. Money uses integer centavos.
document.addEventListener('input', function (event) {
    if (!(event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement) || !['projection-count', 'projection-plan'].includes(event.target.id)) return;
    const form = event.target.closest('#revenue-projection');
    if (!form) return;
    const count = form.querySelector('#projection-count');
    const plan = form.querySelector('#projection-plan');
    const option = plan?.selectedOptions[0];
    if (!count || !count.validity.valid || !/^\d+$/.test(count.value)) return;
    if (option) form.dataset.priceCents = option.dataset.priceCents;
    const monthly = BigInt(count.value) * BigInt(form.dataset.priceCents);
    const peso = function (cents) {
        return '₱' + (cents / 100n).toLocaleString('en-PH') + '.' + (cents % 100n).toString().padStart(2, '0');
    };
    form.querySelector('#projection-monthly').textContent = peso(monthly);
    form.querySelector('#projection-annual').textContent = peso(monthly * 12n);
    if (option) {
        form.querySelector('#projection-plan-name').textContent = option.dataset.name;
        form.querySelector('#projection-price').textContent = peso(BigInt(option.dataset.priceCents));
    }
});
