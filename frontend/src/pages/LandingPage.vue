<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink as Link, useRouter } from 'vue-router';
import { useApiQuery } from '@/composables/query';
import { useAuthStore } from '@/stores/auth';
import { useLookups, useWebsiteContent } from '@/composables/content';
import { api } from '@/services/api';
import { fieldClass } from '@/components/ui/fields';
import type { Technician, Page, Review } from '@/types';
import { BadgeCheck, Wrench, ArrowRight, ClipboardList, MapPin, Search, CalendarCheck, GitCompareArrows, Laptop, ShieldCheck, Smartphone, CheckCircle2, Zap, Star, MessageCircle, ChevronRight } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import SectionHeading from '@/components/common/SectionHeading.vue';
import RepairJourneyCard from '@/components/common/RepairJourneyCard.vue';
import TechnicianCard from '@/components/technician/TechnicianCard.vue';
import ServiceHighlights from '@/components/common/ServiceHighlights.vue';
import TechnicianPlans from '@/components/technician/TechnicianPlans.vue';

const router = useRouter();
const auth = useAuthStore();
const user = computed(() => auth.user);
const lookups = useLookups();
const content = useWebsiteContent();
const heroImage = '/images/repair-technician.webp';
const device = ref(''), problem = ref('');
const steps = [
  { title: 'Search a technician', text: 'Find approved technicians near your location.', icon: Search },
  { title: 'Request your repair', text: 'Describe the problem and arrange an appointment.', icon: CalendarCheck },
  { title: 'Track the repair', text: 'Approve your estimate and follow every update.', icon: ClipboardList },
  { title: 'Get your device', text: 'Confirm completion and share your experience.', icon: CheckCircle2 },
];
const benefits = [
  { title: 'Find trusted technicians', text: 'Compare approved profiles, experience and customer reviews.', icon: BadgeCheck },
  { title: 'Book & manage repairs', text: 'Keep requests, appointments and updates in one workspace.', icon: CalendarCheck },
  { title: 'Clear repair estimates', text: 'Review the diagnosis and approve the cost before work begins.', icon: ShieldCheck },
  { title: 'Stay connected', text: 'Follow your repair from the first request to pickup.', icon: Zap },
];
const fallbackFaqs = [
  { question: 'How do I choose a technician?', answer: 'Search your device and location, compare profiles and reviews, then submit a request to the professional you prefer.' },
  { question: 'When does repair work begin?', answer: 'Your technician diagnoses the device and submits an estimate. You approve that estimate before the repair moves forward.' },
  { question: 'Is my home address visible on the map?', answer: 'No. Public maps show approximate service areas. Appointment locations and repair details are private to authorized participants.' },
  { question: 'Do customers need a subscription?', answer: 'No. Customers can search and request repairs without subscribing. Free, Pro and Premium are optional technician plans. Your technician quotes repair costs separately.' },
];
const featured = useApiQuery<Page<Technician>>(() => ({
  queryKey: ['featured-technicians'],
  queryFn: async ({ signal }) => (await api.get<Page<Technician>>('/technicians?availability=available&sort=rating', { signal })).data,
}));
const featuredProfiles = computed(() => featured.data?.data.slice(0, 3) ?? []);
type PublicFeedback = Review & { technicianName: string; technicianId: number };
const feedback = useApiQuery<PublicFeedback[]>(() => ({
  queryKey: ['landing-feedback', featuredProfiles.value.map(profile => profile.id).join(',')],
  enabled: featuredProfiles.value.length > 0,
  queryFn: async ({ signal }) => {
    const pages = await Promise.all(featuredProfiles.value.map(async profile => {
      const response = await api.get<Page<Review>>('/technicians/' + profile.id + '/reviews', { signal });
      return response.data.data.map(review => ({ ...review, technicianName: profile.name, technicianId: profile.id }));
    }));
    return pages.flat().sort((a, b) => b.created_at.localeCompare(a.created_at)).slice(0, 3);
  },
}));
const popularRepairs = computed(() => [
  ...(lookups.data?.device_types.slice(0, 2).map(item => ({ name: item.name + ' repair', to: '/technicians?device_type=' + item.id, icon: item.name.toLowerCase().includes('laptop') ? Laptop : Smartphone })) ?? []),
  ...(lookups.data?.specializations.slice(0, 2).map(item => ({ name: item.name, to: '/technicians?specialization=' + item.id, icon: Wrench })) ?? []),
]);
const FormData = globalThis.FormData;
const URLSearchParams = globalThis.URLSearchParams;
function search(event: Event) {
  event.preventDefault();
  const query = new URLSearchParams();
  new FormData(event.currentTarget as HTMLFormElement).forEach((value, key) => {
    if (value) query.set(key, String(value));
  });
  void router.push('/technicians?' + query);
}
</script>

<template>
<div class="reference-landing">
  <section class="landing-hero">
    <div class="landing-container">
      <div class="hero-grid">
        <div class="hero-copy">
          <p class="landing-eyebrow"><Laptop class="size-3.5" aria-hidden="true" />Technician finder for laptops & mobile devices</p>
          <h1 v-if="content.data?.hero_title">{{ content.data.hero_title }}</h1>
          <h1 v-else>Find the Right Repair <span>Technician</span> for Your Device</h1>
          <p class="hero-description">{{ content.data?.hero_subtitle || 'Connect with local repair technicians for your laptop and mobile devices. Find your specialist, arrange a repair, and follow every update in one place.' }}</p>
          <div class="hero-actions">
            <Button as-child size="lg"><Link to="/technicians"><Search aria-hidden="true" />Find a technician<ArrowRight aria-hidden="true" /></Link></Button>
            <Button as-child size="lg" variant="outline"><Link to="/dashboard"><ClipboardList aria-hidden="true" />Manage a repair</Link></Button>
          </div>
          <div class="hero-trust">
            <span><BadgeCheck aria-hidden="true" />Approved profiles</span>
            <span><MapPin aria-hidden="true" />Local service areas</span>
            <span><ShieldCheck aria-hidden="true" />Private repair details</span>
          </div>
        </div>
        <div class="hero-scene">
          <div class="hero-photo-wrap"><img :src="heroImage" alt="Illustrative scene of a technician carefully repairing a laptop" width="1448" height="1086" fetchpriority="high" class="hero-photo" /></div>
          <div class="hero-services">
            <h2>Popular repairs</h2>
            <Link v-for="item in popularRepairs" :key="item.to" :to="item.to"><span class="hero-service-icon"><component :is="item.icon" aria-hidden="true" /></span><span>{{ item.name }}</span><ChevronRight aria-hidden="true" /></Link>
            <Link v-if="!popularRepairs.length" to="/technicians"><Wrench aria-hidden="true" /><span>Explore repair services</span><ChevronRight aria-hidden="true" /></Link>
          </div>
          <span class="hero-decoration hero-decoration-one" aria-hidden="true"></span>
          <span class="hero-decoration hero-decoration-two" aria-hidden="true"></span>
        </div>
      </div>
      <div class="landing-search surface-card">
        <h2>Find a technician near you</h2>
        <form aria-label="Find a repair technician" @submit="search">
          <label><span><MapPin aria-hidden="true" />Location</span><input name="city" placeholder="City or municipality" :class="fieldClass" /></label>
          <label><span><Laptop aria-hidden="true" />Device type</span><select name="device_type" :value="device" :class="fieldClass" @change="(event: Event) => { device = (event.target as HTMLSelectElement).value; problem = ''; }"><option value="">Choose your device</option><option v-for="option in lookups.data?.device_types" :key="option.id" :value="option.id">{{ option.name }}</option></select></label>
          <label><span><Wrench aria-hidden="true" />Repair problem</span><select name="repair_category" :value="problem" :class="fieldClass" @change="(event: Event) => problem = (event.target as HTMLSelectElement).value"><option value="">What needs fixing?</option><option v-for="option in lookups.data?.repair_categories.filter(category => !device || !category.device_type_id || String(category.device_type_id) === device)" :key="option.id" :value="option.id">{{ option.name }}</option></select></label>
          <Button type="submit"><Search aria-hidden="true" />Search technicians</Button>
        </form>
        <p v-if="lookups.isError" class="mt-3 text-sm text-red-700">Device and service options could not load. You can still search by location.</p>
      </div>
    </div>
  </section>

  <section class="landing-container landing-section" aria-labelledby="why-erepair">
    <div class="landing-section-heading"><p class="landing-kicker">Repair made simpler</p><h2 id="why-erepair">Why choose eRepair?</h2><p>Local expertise, clear communication, and a more organized repair experience.</p></div>
    <div class="benefit-grid">
      <article v-for="benefit in benefits" :key="benefit.title" class="benefit-card surface-card"><span class="landing-icon"><component :is="benefit.icon" aria-hidden="true" /></span><h3>{{ benefit.title }}</h3><p>{{ benefit.text }}</p></article>
    </div>
  </section>

  <section class="landing-container" aria-labelledby="featured-heading">
    <div class="featured-section">
      <div class="landing-row-heading"><div><p class="landing-kicker"><Star class="size-4" aria-hidden="true" />Local professionals</p><h2 id="featured-heading">Meet your next repair technician</h2><p>Approved profiles currently accepting repair requests.</p></div><Button as-child variant="ghost"><Link to="/technicians">View all technicians<ArrowRight aria-hidden="true" /></Link></Button></div>
      <Loading v-if="featured.isPending" />
      <Failure v-else-if="featured.isError" :error="featured.error" :retry="() => featured.refetch()" />
      <div v-else-if="featuredProfiles.length" class="featured-grid"><TechnicianCard v-for="technician in featuredProfiles" :key="technician.id" :technician="technician" /></div>
      <Empty v-else title="The directory is growing" description="Approved technicians accepting new work will appear here." />
    </div>
  </section>

  <section class="landing-container landing-section" aria-labelledby="repair-steps-heading">
    <div class="landing-section-heading"><p class="landing-kicker">From search to pickup</p><h2 id="repair-steps-heading">How eRepair works</h2><p>Get your device repaired in a few simple steps.</p></div>
    <ol class="repair-steps">
      <li v-for="(step, index) in steps" :key="step.title"><span class="step-symbol"><component :is="step.icon" aria-hidden="true" /><span>{{ index + 1 }}</span></span><div><h3>{{ step.title }}</h3><p>{{ step.text }}</p></div><ArrowRight v-if="index < steps.length - 1" class="step-arrow" aria-hidden="true" /></li>
    </ol>
  </section>

  <section class="landing-container platform-section" aria-labelledby="platform-heading">
    <div class="platform-visual"><RepairJourneyCard /><div class="platform-phone" aria-hidden="true"><span></span><Search /><strong>Find your specialist.</strong><p>Stay connected through every repair.</p><div class="platform-phone-action">Your repair workspace</div></div></div>
    <div class="platform-copy"><p class="landing-eyebrow"><Laptop class="size-3.5" aria-hidden="true" />More than a technician finder</p><h2 id="platform-heading">A complete repair support platform</h2><p>Find the right technician and keep your repair organized, from the first inquiry to the final update.</p><ul><li v-for="item in ['Technician discovery & matching', 'Repair requests & progress tracking', 'Estimates & appointment coordination', 'Customer ratings & reviews']" :key="item"><CheckCircle2 aria-hidden="true" />{{ item }}</li></ul><Button as-child variant="outline"><Link to="/how-it-works">Explore how it works<ArrowRight aria-hidden="true" /></Link></Button></div>
  </section>

  <section class="landing-container landing-section">
    <div class="landing-cta" aria-label="For customers and technicians">
      <div><span class="cta-icon"><Search aria-hidden="true" /></span><div><h2>Need a device fixed?</h2><p>Find local repair expertise and follow your repair with confidence.</p><Button as-child variant="outline"><Link :to="user?.role === 'customer' ? '/dashboard' : '/technicians'">{{ user?.role === 'customer' ? 'Open customer workspace' : 'Find a technician' }}<ArrowRight aria-hidden="true" /></Link></Button></div></div>
      <div><span class="cta-icon"><Wrench aria-hidden="true" /></span><div><h2>Are you a technician?</h2><p>Build your profile, manage requests, and connect with customers in your area.</p><div class="flex flex-wrap gap-2"><Button as-child variant="outline"><Link :to="user ? '/dashboard' : '/register/technician'">{{ user?.role === 'technician' ? 'Open technician workspace' : user ? 'Open your workspace' : 'Join as a technician' }}<ArrowRight aria-hidden="true" /></Link></Button><a href="#technician-plans" class="cta-plans-link">View technician plans<ChevronRight aria-hidden="true" /></a></div></div></div>
    </div>
  </section>

  <section v-if="featuredProfiles.length" class="landing-container landing-section feedback-section" aria-labelledby="feedback-heading">
    <div class="landing-row-heading"><div><p class="landing-kicker">From completed repairs</p><h2 id="feedback-heading">What customers say</h2><p>Published feedback from customers of the technicians featured above.</p></div><MessageCircle class="feedback-heading-icon" aria-hidden="true" /></div>
    <Loading v-if="feedback.isPending" />
    <Failure v-else-if="feedback.isError" :error="feedback.error" :retry="() => feedback.refetch()" />
    <div v-else-if="feedback.data?.length" class="feedback-grid">
      <article v-for="review in feedback.data" :key="review.technicianId + '-' + review.id" class="surface-card feedback-card"><div class="feedback-customer"><Avatar :name="review.customer?.name ?? 'Customer'" size="sm" /><div><h3>{{ review.customer?.name ?? 'Customer' }}</h3><p>Completed repair review</p></div><span class="feedback-rating" :aria-label="review.rating + ' out of 5 stars'"><Star v-for="star in 5" :key="star" :class="{ filled: star <= review.rating }" aria-hidden="true" /></span></div><blockquote>{{ review.body }}</blockquote><Link :to="'/technicians/' + review.technicianId">Repair by {{ review.technicianName }}<ArrowRight aria-hidden="true" /></Link></article>
    </div>
    <p v-else class="feedback-empty">Reviews from completed repairs will appear here. You can also read feedback on individual technician profiles.</p>
  </section>

  <section id="repair-services" aria-label="Repair services" class="landing-container landing-section">
    <SectionHeading eyebrow="Made for your device" title="Find the skills your device needs" description="Explore supported devices, published service guides and repair specializations." />
    <ServiceHighlights :services="content.data?.services ?? []" />
    <div class="device-service-grid"><Link v-for="item in lookups.data?.device_types" :key="item.id" :to="'/technicians?device_type=' + item.id" class="surface-card device-service-card"><span class="landing-icon"><Laptop v-if="item.name.toLowerCase().includes('laptop')" aria-hidden="true" /><Smartphone v-else aria-hidden="true" /></span><div><h3>{{ item.name }} repair</h3><p>Explore specialists for your device</p></div><ArrowRight aria-hidden="true" /></Link></div>
    <div class="specialization-links"><Link v-for="item in lookups.data?.specializations" :key="item.id" :to="'/technicians?specialization=' + item.id">{{ item.name }}<ChevronRight aria-hidden="true" /></Link></div>
    <p v-if="lookups.isError" class="mt-4 text-sm text-muted-foreground">Service categories are unavailable. <Link class="font-semibold text-primary" to="/technicians">Open the technician directory</Link>.</p>
  </section>

  <TechnicianPlans />

  <section v-if="content.data?.announcements.length" class="landing-container landing-section"><SectionHeading eyebrow="From eRepair" title="Platform updates" /><div class="mt-6 grid gap-4 md:grid-cols-2"><article v-for="item in content.data.announcements" :key="item.title" class="surface-card p-6"><h3 class="font-semibold text-navy">{{ item.title }}</h3><p class="mt-3 whitespace-pre-wrap text-sm leading-7 text-muted-foreground">{{ item.content }}</p></article></div></section>

  <section id="faqs" class="faq-section">
    <div class="landing-container faq-grid"><div class="faq-intro"><p class="landing-kicker">A little guidance</p><h2>Frequently asked questions</h2><p>Have questions about eRepair? Start here.</p><div class="faq-illustration" aria-hidden="true"><span>?</span><MessageCircle /><i></i><i></i><i></i></div></div><div class="faq-list"><details v-for="item in content.data?.faqs.length ? content.data.faqs : fallbackFaqs" :key="item.question"><summary>{{ item.question }}<ChevronRight aria-hidden="true" /></summary><p>{{ item.answer }}</p></details></div></div>
  </section>
</div>
</template>

