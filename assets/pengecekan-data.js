(() => {
  document.querySelectorAll('[data-dc-staff-detail]').forEach(button => {
    button.addEventListener('click', () => {
      const template = document.getElementById(`dc-staff-detail-${button.dataset.dcStaffDetail}`);
      if (!template) return;
      document.getElementById('dcStaffDetailTitle').textContent = `Rincian SubSLS — ${button.dataset.staffName}`;
      document.getElementById('dcStaffDetailBody').replaceChildren(template.content.cloneNode(true));
      window.jQuery('#dcStaffDetailModal').modal('show');
    });
  });
  document.querySelectorAll('[data-dc-group]').forEach(group => {
    let countyCollapsed = false;
    const collapsed = new Set();
    const refresh = () => {
      group.querySelector('.dc-subtotal').hidden = !countyCollapsed;
      group.querySelectorAll('.dc-kec-subtotal').forEach(row => {
        row.hidden = countyCollapsed || !collapsed.has(row.dataset.kec);
      });
      group.querySelectorAll('.dc-detail').forEach(row => {
        row.hidden = countyCollapsed || collapsed.has(row.dataset.kec);
      });
    };
    group.querySelectorAll('[data-dc-toggle]').forEach(button => button.addEventListener('click', () => {
      const key = button.dataset.dcToggle;
      if (key === 'kab') countyCollapsed = !countyCollapsed;
      else if (collapsed.has(key)) collapsed.delete(key);
      else collapsed.add(key);
      refresh();
      const target = Array.from(group.querySelectorAll('[data-dc-toggle]')).find(candidate =>
        candidate.dataset.dcToggle === key && !candidate.closest('tr').hidden);
      if (target) target.focus({ preventScroll: true });
    }));
  });
  const form = document.getElementById('dc-filter-form');
  if (!form) return;
  form.querySelectorAll('[data-auto-submit]').forEach(select => select.addEventListener('change', () => form.submit()));
  form.querySelectorAll('[data-area]').forEach(select => select.addEventListener('change', () => {
    if (select.dataset.area === 'kab_id') {
      form.elements.kec_id.value = '';
      form.elements.desa_id.value = '';
    } else if (select.dataset.area === 'kec_id') {
      form.elements.desa_id.value = '';
    }
    form.submit();
  }));
})();
