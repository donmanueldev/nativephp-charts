const menuToggle = document.querySelector('[data-menu-toggle]');
const navigation = document.querySelector('[data-navigation]');

const menuLinks = Array.from(navigation?.querySelectorAll('a') ?? []);

const closeMenu = ({ restoreFocus = false } = {}) => {
  if (!menuToggle || !navigation) {
    return;
  }

  const wasOpen = menuToggle.getAttribute('aria-expanded') === 'true';

  menuToggle.setAttribute('aria-expanded', 'false');
  navigation.removeAttribute('data-open');
  document.body.classList.remove('menu-open');

  if (restoreFocus && wasOpen) {
    menuToggle.focus();
  }
};

menuToggle?.addEventListener('click', () => {
  const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';

  if (isOpen) {
    closeMenu();
    return;
  }

  menuToggle.setAttribute('aria-expanded', 'true');
  navigation?.setAttribute('data-open', 'true');
  document.body.classList.add('menu-open');
  window.requestAnimationFrame(() => menuLinks[0]?.focus());
});

menuLinks.forEach((link) => {
  link.addEventListener('click', closeMenu);
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closeMenu({ restoreFocus: true });
    return;
  }

  if (
    event.key !== 'Tab'
    || menuToggle?.getAttribute('aria-expanded') !== 'true'
    || menuLinks.length === 0
  ) {
    return;
  }

  const firstLink = menuLinks[0];
  const lastLink = menuLinks[menuLinks.length - 1];

  if (event.shiftKey && document.activeElement === firstLink) {
    event.preventDefault();
    lastLink.focus();
  } else if (!event.shiftKey && document.activeElement === lastLink) {
    event.preventDefault();
    firstLink.focus();
  }
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 860) {
    closeMenu();
  }
});

const tabs = Array.from(document.querySelectorAll('[data-code-tab]'));
const panels = Array.from(document.querySelectorAll('[data-code-panel]'));
const demoPanels = Array.from(document.querySelectorAll('[data-demo-panel]'));
const apiShowcase = document.querySelector('.api-showcase');
const apiPreview = document.querySelector('.api-preview');
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const loadDemoMedia = (video, shouldLoadVideo) => {
  if (!video.poster && video.dataset.poster) {
    video.poster = video.dataset.poster;
  }

  if (!shouldLoadVideo) {
    return;
  }

  const source = video.querySelector('source');

  if (source.src) {
    return;
  }

  source.src = source.dataset.src;
  video.load();
};

const syncDemo = (selectedName) => {
  const selectedPanel = demoPanels.find((panel) => panel.dataset.demoPanel === selectedName);

  if (apiShowcase && apiPreview) {
    const hasPreview = Boolean(selectedPanel);
    apiShowcase.dataset.hasPreview = String(hasPreview);
    apiPreview.hidden = !hasPreview;
  }

  demoPanels.forEach((panel) => {
    panel.hidden = panel.dataset.demoPanel !== selectedName;
    const isSelected = !panel.hidden;

    panel.querySelectorAll('video').forEach((video) => {
      video.pause();

      if (isSelected) {
        loadDemoMedia(video, !prefersReducedMotion.matches);
      }

      if (isSelected && !prefersReducedMotion.matches) {
        video.currentTime = 0;
        video.play().catch(() => {});
      }
    });
  });
};

const selectTab = (selectedTab, moveFocus = true) => {
  const selectedName = selectedTab.dataset.codeTab;

  tabs.forEach((tab) => {
    const isSelected = tab === selectedTab;
    tab.setAttribute('aria-selected', String(isSelected));
    tab.tabIndex = isSelected ? 0 : -1;
  });

  panels.forEach((panel) => {
    panel.hidden = panel.dataset.codePanel !== selectedName;
  });

  syncDemo(selectedName);

  if (moveFocus) {
    selectedTab.focus();
  }
};

tabs.forEach((tab, index) => {
  tab.addEventListener('click', () => selectTab(tab, false));
  tab.addEventListener('keydown', (event) => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
      return;
    }

    event.preventDefault();

    if (event.key === 'Home') {
      selectTab(tabs[0]);
      return;
    }

    if (event.key === 'End') {
      selectTab(tabs[tabs.length - 1]);
      return;
    }

    const direction = event.key === 'ArrowRight' ? 1 : -1;
    const nextIndex = (index + direction + tabs.length) % tabs.length;
    selectTab(tabs[nextIndex]);
  });
});

syncDemo(tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.codeTab);
prefersReducedMotion.addEventListener('change', () => {
  syncDemo(tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.codeTab);
});

document.querySelectorAll('[data-copy-target]').forEach((button) => {
  button.addEventListener('click', async () => {
    const target = document.getElementById(button.dataset.copyTarget);

    if (!target) {
      return;
    }

    const originalLabel = button.textContent;

    try {
      await navigator.clipboard.writeText(target.textContent.trim());
      button.textContent = 'Copied';
    } catch {
      button.textContent = 'Copy unavailable';
    }

    window.setTimeout(() => {
      button.textContent = originalLabel;
    }, 4000);
  });
});
