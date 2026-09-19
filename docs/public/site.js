document.addEventListener('DOMContentLoaded', () => {
  const codeResizeObserver = new ResizeObserver((entries) => {
    entries.forEach(({ target }) => {
      target.tabIndex = target.scrollWidth > target.clientWidth || target.scrollHeight > target.clientHeight ? 0 : -1;
    });
  });
  document.querySelectorAll('pre').forEach((block) => codeResizeObserver.observe(block));

  document.querySelectorAll('[data-code-tabs]').forEach((container) => {
    const tabs = [...container.querySelectorAll('[role="tab"]')];
    const panels = [...container.querySelectorAll('[role="tabpanel"]')];
    const copyButton = container.querySelector('[data-copy-current]');
    const copyStatus = container.querySelector('[data-copy-status]');

    const activate = (selected, moveFocus = false) => {
      tabs.forEach((tab) => {
        const active = tab === selected;
        tab.setAttribute('aria-selected', String(active));
        tab.tabIndex = active ? 0 : -1;
      });
      panels.forEach((panel) => {
        panel.hidden = panel.id !== selected.getAttribute('aria-controls');
      });
      copyStatus.textContent = '';
      if (moveFocus) selected.focus();
    };

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => activate(tab));
      tab.addEventListener('keydown', (event) => {
        let targetIndex;
        if (event.key === 'ArrowRight') targetIndex = (index + 1) % tabs.length;
        if (event.key === 'ArrowLeft') targetIndex = (index - 1 + tabs.length) % tabs.length;
        if (event.key === 'Home') targetIndex = 0;
        if (event.key === 'End') targetIndex = tabs.length - 1;
        if (targetIndex === undefined) return;
        event.preventDefault();
        activate(tabs[targetIndex], true);
      });
    });

    copyButton.addEventListener('click', async () => {
      const selected = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true');
      const panel = panels.find((candidate) => candidate.id === selected.getAttribute('aria-controls'));
      const code = panel.querySelector('pre code').textContent;
      try {
        await navigator.clipboard.writeText(code);
        copyStatus.textContent = 'Copied';
      } catch {
        copyStatus.textContent = 'Copy unavailable. Select the code to copy it.';
      }
    });
  });
});
