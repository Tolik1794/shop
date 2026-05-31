// Usage: https://github.com/Grsmto/simplebar
import SimpleBar from "simplebar";

const storageKey = "admin.sidebar.collapsed";
const desktopQuery = "(min-width: 992px)";
const sidebarToggleReadyKey = "adminSidebarToggleReady";
const sidebarRailReadyKey = "adminSidebarRailReady";
const sidebarLabelReadyKey = "adminSidebarLabelReady";
const submenuOpenClass = "sidebar-submenu-open";
const labelClass = "sidebar-hover-label";

const initialize = () => {
  restoreSidebarState();
  initializeSimplebar();
  initializeSidebarCollapse();
}

const isDesktop = () => window.matchMedia(desktopQuery).matches;

const readSidebarState = () => {
  try {
    return window.localStorage.getItem(storageKey);
  } catch (error) {
    return null;
  }
}

const writeSidebarState = collapsed => {
  try {
    window.localStorage.setItem(storageKey, collapsed ? "collapsed" : "expanded");
  } catch (error) {
  }
}

const restoreSidebarState = () => {
  const sidebarElement = document.getElementsByClassName("js-sidebar")[0];

  if(!sidebarElement || !isDesktop()) {
    return;
  }

  sidebarElement.classList.toggle("collapsed", readSidebarState() === "collapsed");
  document.documentElement.classList.remove("sidebar-collapsed");
}

const closeSidebarSubmenus = () => {
  document.querySelectorAll(`.js-sidebar .${submenuOpenClass}`).forEach(item => {
    item.classList.remove(submenuOpenClass);

    const link = item.querySelector('[data-bs-toggle="collapse"]');
    if(link) {
      link.setAttribute("aria-expanded", "false");
    }
  });
}

const getSidebarLabelElement = () => {
  let labelElement = document.getElementsByClassName(labelClass)[0];

  if(!labelElement) {
    labelElement = document.createElement("div");
    labelElement.className = labelClass;
    labelElement.setAttribute("role", "tooltip");
    document.body.appendChild(labelElement);
  }

  return labelElement;
}

const hideSidebarLabel = () => {
  const labelElement = document.getElementsByClassName(labelClass)[0];

  if(labelElement) {
    labelElement.classList.remove("show");
  }
}

const showSidebarLabel = link => {
  const sidebarElement = document.getElementsByClassName("js-sidebar")[0];
  const label = link.dataset.sidebarLabel;

  if(!sidebarElement || !isDesktop() || !sidebarElement.classList.contains("collapsed") || !label) {
    hideSidebarLabel();
    return;
  }

  const linkBounds = link.getBoundingClientRect();
  const labelElement = getSidebarLabelElement();

  labelElement.textContent = label;
  labelElement.style.left = `${linkBounds.right + 10}px`;
  labelElement.style.top = `${linkBounds.top + (linkBounds.height / 2)}px`;
  labelElement.classList.add("show");
}

const initializeSimplebar = () => {
  const simplebarElement = document.getElementsByClassName("js-simplebar")[0];

  if(simplebarElement){
    const simplebarInstance = window.adminSidebarSimplebar || new SimpleBar(simplebarElement);
    window.adminSidebarSimplebar = simplebarInstance;
    simplebarInstance.recalculate();

    /* Recalculate simplebar on sidebar dropdown toggle */
    const sidebarDropdowns = document.querySelectorAll(".js-sidebar [data-bs-parent]");
    
    sidebarDropdowns.forEach(link => {
      if(link.dataset.sidebarCollapseReady === "1") {
        return;
      }

      link.dataset.sidebarCollapseReady = "1";
      link.addEventListener("shown.bs.collapse", () => {
        simplebarInstance.recalculate();
      });
      link.addEventListener("hidden.bs.collapse", () => {
        simplebarInstance.recalculate();
      });
    });
  }
}

const initializeSidebarCollapse = () => {
  if(document.documentElement.dataset[sidebarToggleReadyKey] !== "1") {
    document.documentElement.dataset[sidebarToggleReadyKey] = "1";
    document.addEventListener("click", event => {
      if(!event.target.closest || !event.target.closest(".js-sidebar-toggle")) {
        return;
      }

      const sidebarElement = document.getElementsByClassName("js-sidebar")[0];

      if(!sidebarElement) {
        return;
      }

      closeSidebarSubmenus();
      sidebarElement.classList.toggle("collapsed");

      if(isDesktop()) {
        writeSidebarState(sidebarElement.classList.contains("collapsed"));
      }

      sidebarElement.addEventListener("transitionend", () => {
        window.dispatchEvent(new Event("resize"));
      }, { once: true });
    });
  }

  if(document.documentElement.dataset[sidebarRailReadyKey] !== "1") {
    document.documentElement.dataset[sidebarRailReadyKey] = "1";
    document.addEventListener("click", event => {
      const link = event.target.closest && event.target.closest('.js-sidebar [data-bs-toggle="collapse"]');
      const sidebarElement = document.getElementsByClassName("js-sidebar")[0];

      if(!link || !sidebarElement || !isDesktop() || !sidebarElement.classList.contains("collapsed")) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      const item = link.closest(".sidebar-item");

      if(!item) {
        return;
      }

      const wasOpen = item.classList.contains(submenuOpenClass);
      closeSidebarSubmenus();

      if(wasOpen) {
        return;
      }

      item.classList.add(submenuOpenClass);
      link.setAttribute("aria-expanded", "true");
    }, true);

    document.addEventListener("click", event => {
      if(event.target.closest && event.target.closest(".js-sidebar")) {
        return;
      }

      closeSidebarSubmenus();
    });

    document.addEventListener("keydown", event => {
      if(event.key === "Escape") {
        closeSidebarSubmenus();
      }
    });

    window.addEventListener("resize", closeSidebarSubmenus);
    window.addEventListener("scroll", closeSidebarSubmenus, true);
  }

  if(document.documentElement.dataset[sidebarLabelReadyKey] !== "1") {
    document.documentElement.dataset[sidebarLabelReadyKey] = "1";
    document.addEventListener("mouseover", event => {
      const link = event.target.closest && event.target.closest(".js-sidebar [data-sidebar-label]");

      if(link) {
        showSidebarLabel(link);
      }
    });
    document.addEventListener("focusin", event => {
      const link = event.target.closest && event.target.closest(".js-sidebar [data-sidebar-label]");

      if(link) {
        showSidebarLabel(link);
      }
    });
    document.addEventListener("mouseout", event => {
      if(event.target.closest && event.target.closest(".js-sidebar [data-sidebar-label]")) {
        hideSidebarLabel();
      }
    });
    document.addEventListener("focusout", event => {
      if(event.target.closest && event.target.closest(".js-sidebar [data-sidebar-label]")) {
        hideSidebarLabel();
      }
    });
    document.addEventListener("click", hideSidebarLabel);
    window.addEventListener("resize", hideSidebarLabel);
    window.addEventListener("scroll", hideSidebarLabel, true);
  }
}

// Wait until page is loaded
document.addEventListener("DOMContentLoaded", () => initialize());
document.addEventListener("admin:shell-refreshed", () => {
  hideSidebarLabel();
  closeSidebarSubmenus();
  restoreSidebarState();
  initializeSimplebar();
  initializeSidebarCollapse();
});
