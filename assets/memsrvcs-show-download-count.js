document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('uploads_use_yearmonth_folders');
  const button = document.getElementById('reset-download-count');

  if (button) {
    if (input) {
      input.closest('td').setAttribute('colspan', 2);
    }

    button.addEventListener('click', (event) => {
      event.preventDefault();
      const xhr = new XMLHttpRequest();
      xhr.open('GET', ajaxurl + '?action=reset-download-count');
      xhr.send();
    });
  }
});
