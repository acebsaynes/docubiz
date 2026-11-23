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

// Contact form (client-side validation only; wire `send()` to your backend)
const form = document.getElementById('contact-form');
const status = form.querySelector('.form-status');
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validateField(input) {
  const field = input.closest('.field');
  const error = field.querySelector('.error');
  const value = input.value.trim();
  let message = '';

  if (!value) message = 'This field is required.';
  else if (input.type === 'email' && !EMAIL_RE.test(value)) message = 'Enter a valid email address.';

  field.classList.toggle('invalid', Boolean(message));
  error.textContent = message;
  input.setAttribute('aria-invalid', message ? 'true' : 'false');
  return !message;
}

form.querySelectorAll('input, textarea').forEach((input) => {
  input.addEventListener('blur', () => validateField(input));
  input.addEventListener('input', () => {
    if (input.closest('.field').classList.contains('invalid')) validateField(input);
  });
});

async function send(data) {
  // TODO: replace with a real request, e.g.
  // await fetch('/api/contact', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
  console.log('Contact form submission', data);
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  status.textContent = '';

  const inputs = [...form.querySelectorAll('input, textarea')];
  const results = inputs.map(validateField);
  if (results.includes(false)) {
    inputs[results.indexOf(false)].focus();
    return;
  }

  const submit = form.querySelector('.btn-submit');
  submit.disabled = true;
  try {
    await send(Object.fromEntries(new FormData(form)));
    status.textContent = 'Thanks! Your message has been sent.';
    form.reset();
  } catch {
    status.textContent = 'Something went wrong. Please try again.';
  } finally {
    submit.disabled = false;
  }
});

form.addEventListener('reset', () => {
  form.querySelectorAll('.field').forEach((f) => {
    f.classList.remove('invalid');
    f.querySelector('.error').textContent = '';
  });
  status.textContent = '';
});
