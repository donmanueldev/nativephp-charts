// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

const site = 'https://donmanueldev.github.io';
const base = '/nativephp-charts';
const ogImage = `${site}${base}/og.png`;
const ogImageAlt = 'NativePHP Charts: native charts for NativePHP Mobile, rendered with Swift Charts on iOS and Jetpack Compose on Android.';

export default defineConfig({
  site,
  base,
  integrations: [
    starlight({
      title: 'NativePHP Charts',
      description: 'Documentation for NativePHP Charts: native line, bar, area, scatter, pie, donut, candlestick, radar, progress and heatmap charts for NativePHP Mobile on iOS and Android.',
      disable404Route: true,
      logo: { src: './public/brand/nativephp-charts-mark.svg', replacesTitle: false },
      favicon: "/brand/favicon.svg",
      social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/donmanueldev/nativephp-charts' }],
      customCss: ['./src/styles/docs.css'],
      head: [
        { tag: 'script', attrs: { src: `${base}/site.js`, defer: true } },
        { tag: 'meta', attrs: { property: 'og:image', content: ogImage } },
        { tag: 'meta', attrs: { property: 'og:image:alt', content: ogImageAlt } },
        { tag: 'meta', attrs: { property: 'og:image:width', content: '1200' } },
        { tag: 'meta', attrs: { property: 'og:image:height', content: '630' } },
        { tag: 'meta', attrs: { name: 'twitter:card', content: 'summary_large_image' } },
        { tag: 'meta', attrs: { name: 'twitter:image', content: ogImage } },
        { tag: 'meta', attrs: { name: 'twitter:image:alt', content: ogImageAlt } },
      ],
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
        { label: 'Reference', items: [{ label: 'API reference', slug: 'reference/api' }, { label: 'Blade and PHP equivalence', slug: 'reference/blade-php' }] },
        { label: 'Showcase', items: [{ label: 'Apps using NativePHP Charts', slug: 'apps' }] },
      ],
    }),
  ],
});
