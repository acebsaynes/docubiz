
// Mobile navigation toggle
const toggle = document.querySelector('.nav-toggle');
const nav = document.getElementById('site-nav');

toggle.addEventListener('click', () => {
  const open = nav.classList.toggle('open');
  toggle.setAttribute('aria-expanded', String(open));
});

nav.addEventListener('click', (e) => {
  if (e.target.closest('a')) {
    nav.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
  }
});

// Contact form
const form = document.getElementById('contact-form');
const status = form.querySelector('.form-status');
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validateField(input) {
  const field = input.closest('.field');
  const error = field.querySelector('.error');
  const value = input.value.trim();
  let message = '';

  if (!value) {
    message = 'This field is required.';
  } else if (input.type === 'email' && !EMAIL_RE.test(value)) {
    message = 'Enter a valid email address.';
  }

  field.classList.toggle('invalid', Boolean(message));
  error.textContent = message;
  input.setAttribute('aria-invalid', message ? 'true' : 'false');

  return !message;
}

// Field validation
form.querySelectorAll('input, textarea').forEach((input) => {
  input.addEventListener('blur', () => validateField(input));

  input.addEventListener('input', () => {
    if (input.closest('.field').classList.contains('invalid')) {
      validateField(input);
    }
  });
});

// Send form data to PHP backend
async function send(data) {
  const response = await fetch('contact-submit.php', {
    method: 'POST',
    body: data
  });

  const result = await response.json();

  if (!response.ok || !result.success) {
    throw new Error(result.message || 'Unable to send your inquiry.');
  }

  return result;
}

// Submit handler
form.addEventListener('submit', async (e) => {
  e.preventDefault();

  status.textContent = '';
  status.style.color = '';

  const inputs = [...form.querySelectorAll('input, textarea')];
  const results = inputs.map(validateField);

  if (results.includes(false)) {
    inputs[results.indexOf(false)].focus();
    return;
  }

  const submit = form.querySelector('.btn-submit');
  const originalText = submit.textContent;

  submit.disabled = true;
  submit.textContent = 'Sending...';

  try {
    const formData = new FormData(form);
    const result = await send(formData);

    status.textContent = result.message;
    status.style.color = '#238636';

    form.reset();

  } catch (error) {
    status.textContent =
      error.message || 'Something went wrong. Please try again.';

    status.style.color = '#d93025';

  } finally {
    submit.disabled = false;
    submit.textContent = originalText;
  }
});

// Reset handler
form.addEventListener('reset', () => {
  form.querySelectorAll('.field').forEach((field) => {
    field.classList.remove('invalid');
    field.querySelector('.error').textContent = '';

    const input = field.querySelector('input, textarea');
    input.removeAttribute('aria-invalid');
  });

  status.textContent = '';
  status.style.color = '';
});
