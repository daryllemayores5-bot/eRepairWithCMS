import { cleanup, render, screen } from '@testing-library/vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, expect, it } from 'vitest';
import ServiceHighlights from './ServiceHighlights.vue';

afterEach(cleanup);

const services = [{ slug: 'laptop-repair', title: 'Laptop diagnosis', summary: 'Compare repair specialists.', content: '<script>alert("unsafe")</script>\nDiscuss the estimate first.' }];

async function show(items = services) {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }] });
  await router.push('/');
  await router.isReady();
  return render(ServiceHighlights, { props: { services: items }, global: { plugins: [router] } });
}

it('renders CMS descriptions as text and links customers to the existing finder', async () => {
  const { container } = await show();
  expect(screen.getByRole('heading', { name: 'Laptop diagnosis' })).toBeInTheDocument();
  expect(screen.getByText('Compare repair specialists.')).toBeInTheDocument();
  expect(screen.getByText(/<script>alert/)).toHaveTextContent('Discuss the estimate first.');
  expect(container.querySelector('script')).toBeNull();
  expect(screen.getByRole('link', { name: 'Find a technician for Laptop diagnosis' })).toHaveAttribute('href', '/technicians');
});

it('handles unpublished or empty services without an empty section and reacts to publication changes', async () => {
  const view = await show([]);
  expect(screen.queryByRole('article')).not.toBeInTheDocument();
  await view.rerender({ services });
  expect(screen.getByRole('heading', { name: 'Laptop diagnosis' })).toBeInTheDocument();
  await view.rerender({ services: [] });
  expect(screen.queryByRole('article')).not.toBeInTheDocument();
});
