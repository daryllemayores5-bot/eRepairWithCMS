import { createRouter, createWebHistory, type RouterHistory } from 'vue-router';
import SiteLayout from '@/layouts/SiteLayout.vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import ProtectedRoute from '@/routes/ProtectedRoute.vue';
export function createAppRouter(history: RouterHistory = createWebHistory()) {
  return createRouter({
    history,
    routes: [{
      path: '/',
      component: SiteLayout,
      children: [{
        path: '',
        component: () => import('@/pages/LandingPage.vue')
      }, {
        path: 'technicians',
        component: () => import('@/pages/DirectoryPage.vue')
      }, {
        path: 'technicians/:id',
        component: () => import('@/pages/TechnicianPage.vue')
      }, {
        path: 'compare',
        component: () => import('@/pages/ComparePage.vue')
      }, {
        path: 'how-it-works',
        component: () => import('@/pages/HowItWorksPage.vue')
      }, {
        path: 'setup',
        component: () => import('@/pages/SetupPage.vue')
      }, {
        path: 'pages/:slug',
        component: () => import('@/pages/ContentPage.vue'),
        props: true
      }, {
        path: 'install',
        redirect: '/'
      }, {
        path: 'login',
        component: () => import('@/pages/AuthPage.vue'),
        props: {
          mode: 'login'
        }
      }, {
        path: 'register',
        component: () => import('@/pages/AuthPage.vue'),
        props: {
          mode: 'customer'
        }
      }, {
        path: 'register/technician',
        component: () => import('@/pages/AuthPage.vue'),
        props: {
          mode: 'technician'
        }
      }, {
        path: 'forgot-password',
        component: () => import('@/pages/PasswordPage.vue')
      }, {
        path: 'reset-password',
        component: () => import('@/pages/PasswordPage.vue'),
        props: {
          reset: true
        }
      }, {
        path: 'dashboard',
        component: ProtectedRoute,
        children: [{
          path: '',
          component: DashboardLayout,
          children: [{
            path: '',
            component: () => import('@/pages/DashboardPage.vue')
          }, {
            path: 'subscription',
            component: () => import('@/pages/MySubscriptionPage.vue')
          }, {
            path: 'repairs',
            component: () => import('@/pages/RepairListPage.vue')
          }, {
            path: 'repairs/new',
            component: () => import('@/pages/CreateRepairPage.vue')
          }, {
            path: 'repairs/:id',
            component: () => import('@/pages/RepairDetailPage.vue')
          }, {
            path: 'active-repairs',
            component: () => import('@/pages/RepairListPage.vue'),
            props: {
              initialFilter: 'active'
            }
          }, {
            path: 'history',
            component: () => import('@/pages/RepairListPage.vue'),
            props: {
              initialFilter: 'history'
            }
          }, {
            path: 'availability',
            component: () => import('@/pages/AvailabilityPage.vue')
          }, {
            path: 'appointments',
            component: () => import('@/pages/AppointmentPage.vue')
          }, {
            path: 'notifications',
            component: () => import('@/pages/NotificationsPage.vue')
          }, {
            path: 'profile',
            component: () => import('@/pages/ProfilePage.vue')
          }, {
            path: 'reviews',
            component: () => import('@/pages/TechnicianReviewsPage.vue')
          }]
        }]
      }, {
        path: ':pathMatch(.*)*',
        component: () => import('@/pages/NotFoundPage.vue')
      }]
    }]
  });
}
