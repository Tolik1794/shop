import * as bootstrap from "bootstrap";

// Bootstrap
// Note: If you want to make bootstrap globally available, e.g. for using `bootstrap.modal`
window.bootstrap = bootstrap;

document.addEventListener("click", event => {
  if (!event.target.closest) {
    return;
  }

  const trigger = event.target.closest('[data-bs-toggle="modal"]');

  if (!trigger) {
    return;
  }

  const selector = trigger.getAttribute("data-bs-target") || trigger.getAttribute("href");

  if (!selector || selector.charAt(0) !== "#") {
    return;
  }

  const modalId = selector.slice(1);
  const modal = document.getElementById(modalId);

  if (!modal || !modal.classList.contains("modal") || modal.parentElement === document.body) {
    return;
  }

  const existingModal = Array.prototype.find.call(
    document.body.querySelectorAll(".modal"),
    candidate => candidate.id === modalId
  );

  if (existingModal && existingModal !== modal) {
    const existingInstance = bootstrap.Modal.getInstance(existingModal);

    if (existingInstance) {
      existingInstance.dispose();
    }

    existingModal.remove();
  }

  document.body.appendChild(modal);
}, true);
