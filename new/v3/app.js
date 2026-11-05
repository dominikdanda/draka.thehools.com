const mobileButton = document.querySelector(".mobile-menu-button");
const sidebar = document.querySelector("#sidebar");

if (mobileButton && sidebar) {
  mobileButton.addEventListener("click", () => {
    const isOpen = sidebar.classList.toggle("is-open");
    mobileButton.setAttribute("aria-expanded", String(isOpen));
  });
}

document.querySelectorAll(".side-link").forEach((link) => {
  link.addEventListener("click", (event) => {
    event.preventDefault();

    document.querySelectorAll(".side-link").forEach((item) => {
      item.classList.remove("is-active");
    });

    link.classList.add("is-active");
  });
});

document.querySelectorAll(".quick-action, .outline-button, .text-button").forEach((button) => {
  button.addEventListener("click", () => {
    button.animate(
      [
        { transform: "scale(1)" },
        { transform: "scale(0.975)" },
        { transform: "scale(1)" }
      ],
      {
        duration: 160,
        easing: "ease-out"
      }
    );
  });
});

const timer = document.querySelector("[data-countdown]");

if (timer) {
  let remainingSeconds = 2 * 60 * 60 + 45 * 60 + 18;

  window.setInterval(() => {
    remainingSeconds = Math.max(0, remainingSeconds - 1);

    const hours = String(Math.floor(remainingSeconds / 3600)).padStart(2, "0");
    const minutes = String(Math.floor((remainingSeconds % 3600) / 60)).padStart(2, "0");
    const seconds = String(remainingSeconds % 60).padStart(2, "0");

    timer.textContent = `${hours}:${minutes}:${seconds}`;
  }, 1000);
}
