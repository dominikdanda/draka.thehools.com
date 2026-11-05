const sidebar = document.querySelector("#sidebar");
const overlay = document.querySelector("#overlay");
const openMenu = document.querySelector("#openMenu");
const closeMenu = document.querySelector("#closeMenu");
const bottomMenu = document.querySelector("#bottomMenu");

function showMenu() {
    sidebar.classList.add("open");
    overlay.classList.add("show");
    document.body.style.overflow = "hidden";
}

function hideMenu() {
    sidebar.classList.remove("open");
    overlay.classList.remove("show");
    document.body.style.overflow = "";
}

openMenu?.addEventListener("click", showMenu);
bottomMenu?.addEventListener("click", (event) => {
    event.preventDefault();
    showMenu();
});
closeMenu?.addEventListener("click", hideMenu);
overlay?.addEventListener("click", hideMenu);

document.querySelectorAll(".bottom-nav a").forEach((item) => {
    item.addEventListener("click", () => {
        document.querySelectorAll(".bottom-nav a").forEach((link) => {
            link.classList.remove("active");
        });

        item.classList.add("active");
    });
});

document.querySelectorAll(".action-card").forEach((card) => {
    card.addEventListener("click", () => {
        const title = card.querySelector("strong")?.textContent || "Akcja";
        toast(`Wybrano: ${title}`);
    });
});

function toast(message) {
    const oldToast = document.querySelector(".toast");
    oldToast?.remove();

    const element = document.createElement("div");
    element.className = "toast";
    element.textContent = message;

    Object.assign(element.style, {
        position: "fixed",
        left: "50%",
        bottom: "92px",
        transform: "translateX(-50%)",
        background: "rgba(20,20,23,.96)",
        color: "#fff",
        border: "1px solid rgba(255,255,255,.12)",
        padding: "12px 16px",
        borderRadius: "999px",
        zIndex: "100",
        boxShadow: "0 20px 50px rgba(0,0,0,.4)",
        fontWeight: "700"
    });

    document.body.appendChild(element);

    setTimeout(() => {
        element.remove();
    }, 2200);
}
