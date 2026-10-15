import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '../layouts/AppLayout.vue';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/Login.vue'),
        meta: { guest: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('../views/Register.vue'),
        meta: { guest: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../views/ForgotPassword.vue'),
        meta: { guest: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('../views/ResetPassword.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: AppLayout,
        children: [
            {
                path: '',
                name: 'devices.index',
                component: () => import('../views/DeviceShow.vue'),
                meta: { auth: true },
            },
            {
                path: 'flash',
                name: 'web-flasher',
                component: () => import('../views/WebFlasher.vue'),
            },
            {
                path: 'guide',
                name: 'user-guide',
                component: () => import('../views/UserGuide.vue'),
            },
            {
                path: 'admin/devices',
                name: 'admin.devices',
                component: () => import('../views/DevicesIndex.vue'),
                meta: { auth: true, admin: true },
            },
            {
                path: 'admin/users',
                name: 'admin.users',
                component: () => import('../views/AdminUsers.vue'),
                meta: { auth: true, admin: true },
            },
            {
                path: ':serial',
                name: 'devices.show',
                component: () => import('../views/DeviceShow.vue'),
                meta: { auth: true },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../views/NotFound.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Tải lại trang: có token nhưng chưa có user → khôi phục phiên trước khi quyết định.
    if (auth.isAuthenticated && !auth.user) {
        await auth.restore();
    }

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.admin && !auth.isAdmin) {
        return { name: 'devices.index' };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'devices.index' };
    }
});

export default router;
