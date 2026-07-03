(function () {
  const radios    = document.querySelectorAll('input[name="attending"]');
  const mealGroup = document.getElementById('meal-group');
  const dietGroup = document.getElementById('dietary-group');
  const noteGroup = document.getElementById('notes-group');
  const plusGroup = document.getElementById('plus-one-group');

  function toggle(attending) {
    const show = attending === 'yes';
    [mealGroup, dietGroup, noteGroup, plusGroup].forEach(function (el) {
      if (el) el.classList.toggle('hidden', !show);
    });
    if (!show && mealGroup) {
      mealGroup.querySelector('select').required = false;
    } else if (show && mealGroup) {
      mealGroup.querySelector('select').required = true;
    }
  }

  const checked = document.querySelector('input[name="attending"]:checked');
  if (checked) toggle(checked.value);

  radios.forEach(function (r) {
    r.addEventListener('change', function () { toggle(r.value); });
  });
}());
