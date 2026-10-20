'use strict';

const booking = document.querySelector('[data-booking]');

document.querySelectorAll('[data-confirm-cancel]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm('¿Deseas cancelar esta cita?')) event.preventDefault();
  });
});

const carousel = document.querySelector('[data-carousel]');

if (carousel) {
  const slides = [...carousel.querySelectorAll('[data-slide]')];
  const dots = [...carousel.querySelectorAll('[data-slide-dot]')];
  const toggle = carousel.querySelector('[data-carousel-toggle]');
  let currentSlide = 0;
  let timer = null;

  function showSlide(index) {
    slides[currentSlide].classList.remove('is-active');
    dots[currentSlide].classList.remove('is-active');
    dots[currentSlide].removeAttribute('aria-current');
    slides[currentSlide].setAttribute('aria-hidden', 'true');
    currentSlide = index;
    slides[currentSlide].classList.add('is-active');
    dots[currentSlide].classList.add('is-active');
    dots[currentSlide].setAttribute('aria-current', 'true');
    slides[currentSlide].setAttribute('aria-hidden', 'false');
  }

  function startCarousel() {
    if (timer || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = window.setInterval(() => showSlide((currentSlide + 1) % slides.length), 5000);
    toggle.textContent = 'Pausar';
  }

  function stopCarousel() {
    window.clearInterval(timer);
    timer = null;
    toggle.textContent = 'Reproducir';
  }

  dots.forEach((dot, index) => dot.addEventListener('click', () => showSlide(index)));

  toggle.addEventListener('click', () => timer ? stopCarousel() : startCarousel());
  carousel.addEventListener('mouseenter', stopCarousel);
  carousel.addEventListener('mouseleave', startCarousel);
  carousel.addEventListener('focusin', stopCarousel);
  startCarousel();
}

const revealItems = document.querySelectorAll('.reveal-on-scroll');
revealItems.forEach(item => item.classList.add('is-pending'));

if ('IntersectionObserver' in window) {
  const revealObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        entry.target.classList.remove('is-pending');
        revealObserver.unobserve(entry.target);
      }
    });
  }, {threshold: 0.15});

  revealItems.forEach(item => revealObserver.observe(item));
} else {
  revealItems.forEach(item => item.classList.remove('is-pending'));
}

if (booking) {
  const doctor = booking.querySelector('[data-doctor]');
  const date = booking.querySelector('[data-date]');
  const slots = booking.querySelector('[data-slots]');
  const time = booking.querySelector('[data-time]');
  const submit = booking.querySelector('[data-submit]');
  const serviceId = booking.dataset.serviceId;
  let requestNumber = 0;

  const today = new Date();
  const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  date.min = localToday;

  async function loadSlots() {
    const currentRequest = ++requestNumber;
    time.value = '';
    submit.disabled = true;
    if (!doctor.value || !date.value) {
      slots.innerHTML = '<p class="empty-state">Selecciona doctor y fecha para ver los horarios.</p>';
      return;
    }

    const selectedDate = new Date(`${date.value}T12:00:00`);
    if ([0, 6].includes(selectedDate.getDay())) {
      slots.innerHTML = '<p class="empty-state">El centro medico no atiende fines de semana.</p>';
      return;
    }

    slots.innerHTML = '<p class="empty-state">Consultando disponibilidad...</p>';
    try {
      const params = new URLSearchParams({servicio: serviceId, doctor: doctor.value, fecha: date.value});
      const response = await fetch(`/api/disponibilidad?${params}`, {headers: {'Accept': 'application/json'}});
      if (!response.ok) throw new Error('No disponible');
      const data = await response.json();
      if (currentRequest !== requestNumber) return;
      if (!data.slots.length) {
        slots.innerHTML = '<p class="empty-state">No hay horarios disponibles para esta fecha.</p>';
        return;
      }
      slots.innerHTML = data.slots.map(slot => `<button type="button" class="slot" data-slot="${slot}">${slot}</button>`).join('');
    } catch (_) {
      slots.innerHTML = '<p class="empty-state error-text">No fue posible consultar los horarios. Intenta nuevamente.</p>';
    }
  }

  doctor.addEventListener('change', loadSlots);
  date.addEventListener('change', loadSlots);
  slots.addEventListener('click', event => {
    const button = event.target.closest('[data-slot]');
    if (!button) return;
    slots.querySelectorAll('.slot').forEach(item => item.classList.remove('selected'));
    button.classList.add('selected');
    time.value = button.dataset.slot;
    submit.disabled = false;
  });
}
