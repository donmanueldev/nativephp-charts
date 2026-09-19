// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

const base = '/nativephp-charts';

export default defineConfig({
  site: 'https://donmanueldev.github.io',
  base,
  integrations: [
    starlight({
      title: 'NativePHP Charts',
      description: 'Compiled-native charts for NativePHP Mobile.',
      disable404Route: true,
      logo: { src: './public/brand/nativephp-charts-mark.svg', replacesTitle: false },
      favicon: "/brand/favicon.svg",
      social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/donmanueldev/nativephp-charts' }],
      customCss: ['./src/styles/docs.css'],
      head: [{ tag: 'script', attrs: { src: `${base}/site.js`, defer: true } }],
      editLink: { baseUrl: 'https://github.com/donmanueldev/nativephp-charts/edit/main/docs/' },
      sidebar: [
        {
          label: 'Getting started', items: [
            { label: 'Overview', slug: 'getting-started/overview' },
            { label: 'Installation', slug: 'getting-started/installation' },
          ]
        },
        { label: 'Charts', items: [{ autogenerate: { directory: 'charts' } }] },
        { label: 'Guides', items: [{ autogenerate: { directory: 'guides' } }] },
        { label: 'Reference', items: [{ label: 'API reference', slug: 'reference/api' }] },
        { label: 'Showcase', items: [{ label: 'Apps using NativePHP Charts', slug: 'apps' }] },
      ],
    }),
  ],
});
